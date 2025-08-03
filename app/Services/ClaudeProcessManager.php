<?php

namespace App\Services;

use App\Jobs\ProcessClaudeTodos;
use App\Models\ClaudeSession;
use App\Models\Project;
use App\Models\SystemLog;
use App\Services\GitService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ClaudeProcessManager
{
    private array $processes = [];
    private array $streams = [];
    
    /**
     * Start Claude process for a project
     */
    public function startSession(Project $project): ClaudeSession
    {
        // Get or create session
        $session = $project->claudeSessions()->firstOrCreate(
            ['status' => 'idle'],
            [
                'process_id' => null,
                'conversation_history' => [],
            ]
        );
        
        if ($session->isRunning()) {
            return $session;
        }
        
        try {
            // Build the command
            $command = [
                'claude',
                'code',
                '--project-dir', $project->project_path,
            ];
            
            // Create the process
            $process = new Process($command);
            $process->setWorkingDirectory($project->project_path);
            $process->setTimeout(null); // No timeout for interactive process
            $process->setIdleTimeout(null);
            $process->setPty(true); // Enable pseudo-terminal for interactive mode
            
            // Start the process
            $process->start();
            
            // Store process reference
            $pid = $process->getPid();
            $this->processes[$session->id] = $process;
            
            // Update session
            $session->update([
                'process_id' => $pid,
                'status' => 'running',
                'started_at' => now(),
                'last_activity' => now(),
            ]);
            
            // Start monitoring the output
            $this->monitorProcess($session);
            
            // Dispatch job to process TODOs
            ProcessClaudeTodos::dispatch($session)->delay(now()->addSeconds(5));
            
            Log::info("Started Claude session for project {$project->id}", [
                'project_id' => $project->id,
                'session_id' => $session->id,
                'pid' => $pid,
            ]);
            
            return $session;
            
        } catch (\Exception $e) {
            $session->update([
                'status' => 'error',
                'last_output' => $e->getMessage(),
            ]);
            
            Log::error("Failed to start Claude session", [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Send command to Claude process
     */
    public function sendCommand(ClaudeSession $session, string $command): void
    {
        if (!$session->isRunning()) {
            throw new \Exception('Claude session is not running');
        }
        
        $process = $this->getProcess($session);
        if (!$process || !$process->isRunning()) {
            $session->update(['status' => 'stopped']);
            throw new \Exception('Claude process is not running');
        }
        
        try {
            // Send command to process stdin
            $process->getInput()->write($command . PHP_EOL);
            
            // Update session
            $session->update([
                'last_input' => $command,
                'last_activity' => now(),
            ]);
            
            // Add to history
            $session->addToHistory('user', $command);
            
            Log::info("Sent command to Claude", [
                'session_id' => $session->id,
                'command' => $command,
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to send command to Claude", [
                'session_id' => $session->id,
                'command' => $command,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Stop Claude process
     */
    public function stopSession(ClaudeSession $session): void
    {
        $process = $this->getProcess($session);
        
        if ($process && $process->isRunning()) {
            $process->stop(5); // 5 second timeout
            unset($this->processes[$session->id]);
        }
        
        $session->update([
            'status' => 'stopped',
            'process_id' => null,
        ]);
        
        // Handle session end (Git operations)
        $this->onSessionEnded($session);
        
        Log::info("Stopped Claude session", ['session_id' => $session->id]);
    }
    
    /**
     * Get output from Claude process
     */
    public function getOutput(ClaudeSession $session): ?string
    {
        $process = $this->getProcess($session);
        
        if (!$process) {
            return null;
        }
        
        $output = '';
        
        // Get incremental output
        $incrementalOutput = $process->getIncrementalOutput();
        if ($incrementalOutput) {
            $output .= $incrementalOutput;
        }
        
        // Get incremental error output
        $incrementalErrorOutput = $process->getIncrementalErrorOutput();
        if ($incrementalErrorOutput) {
            $output .= "\n[ERROR] " . $incrementalErrorOutput;
        }
        
        if ($output) {
            $session->update([
                'last_output' => $output,
                'last_activity' => now(),
            ]);
            
            // Add to history
            $session->addToHistory('assistant', $output);
        }
        
        return $output ?: null;
    }
    
    /**
     * Check if process is still running
     */
    public function isRunning(ClaudeSession $session): bool
    {
        $process = $this->getProcess($session);
        
        if (!$process || !$process->isRunning()) {
            if ($session->isRunning()) {
                $session->update(['status' => 'stopped']);
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Get process for session
     */
    private function getProcess(ClaudeSession $session): ?Process
    {
        return $this->processes[$session->id] ?? null;
    }
    
    /**
     * Monitor process output in background
     */
    private function monitorProcess(ClaudeSession $session): void
    {
        $process = $this->getProcess($session);
        if (!$process) {
            return;
        }
        
        // This would ideally be handled by a background job
        // For now, we'll just check periodically when getOutput is called
    }
    
    /**
     * Clean up stopped processes
     */
    public function cleanup(): void
    {
        foreach ($this->processes as $sessionId => $process) {
            if (!$process->isRunning()) {
                unset($this->processes[$sessionId]);
                
                $session = ClaudeSession::find($sessionId);
                if ($session && $session->isRunning()) {
                    $session->update(['status' => 'stopped']);
                }
            }
        }
    }
    
    /**
     * Handle task completion (for Git commits)
     */
    public function onTaskCompleted(ClaudeSession $session, string $taskDescription): void
    {
        $project = $session->project;
        
        if (!$project->git_enabled || $project->commit_frequency !== 'after_each_task') {
            return;
        }
        
        $gitService = app(GitService::class);
        $gitService->commit($project, $taskDescription);
    }
    
    /**
     * Handle TODO completion (for Git commits)
     */
    public function onTodoCompleted(ClaudeSession $session, string $todoCommand): void
    {
        $project = $session->project;
        
        if (!$project->git_enabled || $project->commit_frequency !== 'after_each_todo') {
            return;
        }
        
        $gitService = app(GitService::class);
        $gitService->commit($project, "Completed TODO: {$todoCommand}");
    }
    
    /**
     * Handle session end (for Git operations)
     */
    public function onSessionEnded(ClaudeSession $session): void
    {
        $project = $session->project;
        
        if (!$project->git_enabled) {
            return;
        }
        
        $gitService = app(GitService::class);
        
        // Commit if needed
        if ($project->commit_frequency === 'after_each_session') {
            $gitService->commit($project, "Session ended - " . now()->format('Y-m-d H:i:s'));
        }
        
        // Push if needed
        if ($project->auto_push && $project->push_frequency === 'after_each_session') {
            $gitService->push($project);
        }
    }
}