<?php

namespace App\Services;

use App\Jobs\ProcessClaudeTodos;
use App\Jobs\RunClaudeSession;
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
        // Stop any existing running sessions first
        $existingSession = $project->claudeSessions()->where('status', 'running')->first();
        if ($existingSession) {
            $this->stopSession($existingSession);
        }
        
        // Create new session
        $session = $project->claudeSessions()->create([
            'status' => 'idle',
            'process_id' => null,
            'conversation_history' => [],
        ]);
        
        try {
            // Use the shell script to manage screen sessions
            $scriptPath = base_path('scripts/claude-screen-manager.sh');
            $screenName = "claude_{$project->id}";
            
            // Start screen session using the script
            $command = sprintf(
                '%s start %d %s %d 2>&1',
                escapeshellarg($scriptPath),
                $project->id,
                escapeshellarg($project->project_path),
                $session->id
            );
            
            // Execute with sudo if needed (configure sudoers for www-data user)
            // For now, run without sudo - configure permissions properly
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \Exception("Failed to start screen session: " . implode("\n", $output));
            }
            
            // The script returns PID on success
            $pid = isset($output[0]) ? trim($output[0]) : null;
            
            if (!$pid || $pid === '') {
                // Fallback: use screen name as identifier
                $pid = $screenName;
            }
            
            Log::info("Started Claude using script", [
                'command' => $command,
                'output' => $output,
                'pid' => $pid,
                'return_code' => $returnCode
            ]);
            
            // Update session
            $session->update([
                'process_id' => $pid,
                'status' => 'running',
                'started_at' => now(),
                'last_activity' => now(),
            ]);
            
            Log::info("Started Claude in screen session", [
                'session_id' => $session->id,
                'screen_name' => $screenName,
                'pid' => $pid
            ]);
            
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
            throw new \Exception('Claude session is not running (status: ' . $session->status . ')');
        }
        
        try {
            // First try to get the process from memory
            $process = $this->getProcess($session);
            
            Log::info("Checking process before sending command", [
                'session_id' => $session->id,
                'has_process' => $process !== null,
                'process_running' => $process ? $process->isRunning() : false,
                'pid' => $session->process_id
            ]);
            
            // Send command via script
            if ($session->process_id) {
                $scriptPath = base_path('scripts/claude-screen-manager.sh');
                
                // Send command using the script
                $sendCommand = sprintf(
                    '%s send %d %s %d 2>&1',
                    escapeshellarg($scriptPath),
                    $session->project_id,
                    escapeshellarg($command),
                    $session->id
                );
                
                exec($sendCommand, $output, $returnCode);
                
                if ($returnCode !== 0) {
                    // Check if session is still running
                    $statusCommand = sprintf('%s status %d', escapeshellarg($scriptPath), $session->project_id);
                    exec($statusCommand, $statusOutput);
                    
                    if (isset($statusOutput[0]) && $statusOutput[0] === 'stopped') {
                        $session->update(['status' => 'stopped', 'process_id' => null]);
                        throw new \Exception('Claude screen session is not running');
                    }
                    
                    throw new \Exception('Failed to send command: ' . implode("\n", $output));
                }
                
                Log::info("Sent command via script", [
                    'session_id' => $session->id,
                    'command' => $command,
                    'output' => $output
                ]);
            } else {
                throw new \Exception('No process ID for Claude session');
            }
            
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
        // Stop screen session using script
        if ($session->project_id) {
            $scriptPath = base_path('scripts/claude-screen-manager.sh');
            
            $command = sprintf(
                '%s stop %d 2>&1',
                escapeshellarg($scriptPath),
                $session->project_id
            );
            
            exec($command, $output);
            
            Log::info("Stopped screen session", [
                'session_id' => $session->id,
                'project_id' => $session->project_id,
                'output' => $output
            ]);
        }
        
        // Clean up any process in memory
        if (isset($this->processes[$session->id])) {
            $process = $this->processes[$session->id];
            if ($process && $process->isRunning()) {
                $process->stop(5);
            }
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
        $output = '';
        
        // Read from log file created by screen session
        if ($session->process_id) {
            $communicationDir = storage_path("app/claude-sessions/{$session->id}");
            $logFile = "{$communicationDir}/claude.log";
            
            if (file_exists($logFile)) {
                // Read new content from log file
                $lastPosition = $session->output_position ?? 0;
                $currentSize = filesize($logFile);
                
                if ($currentSize > $lastPosition) {
                    $handle = fopen($logFile, 'r');
                    fseek($handle, $lastPosition);
                    $newOutput = fread($handle, $currentSize - $lastPosition);
                    fclose($handle);
                    
                    if ($newOutput) {
                        $output .= $newOutput;
                        // Save the new position
                        $session->update(['output_position' => $currentSize]);
                    }
                }
            }
        }
        
        if ($output) {
            $session->update([
                'last_output' => $output,
                'last_activity' => now(),
            ]);
            
            // Don't add to history here - it will be added in refreshOutput
            // to avoid duplicates
        }
        
        return $output ?: null;
    }
    
    /**
     * Check if process is still running
     */
    public function isRunning(ClaudeSession $session): bool
    {
        // Check if screen session is running using script
        if ($session->process_id && $session->project_id) {
            $scriptPath = base_path('scripts/claude-screen-manager.sh');
            
            $command = sprintf(
                '%s status %d',
                escapeshellarg($scriptPath),
                $session->project_id
            );
            
            exec($command, $output);
            
            if (isset($output[0]) && str_starts_with($output[0], 'running:')) {
                return true;
            }
        }
        
        // Process is not running, update status if needed
        if ($session->isRunning()) {
            $session->update(['status' => 'stopped', 'process_id' => null]);
        }
        
        return false;
    }
    
    /**
     * Get process for session
     */
    private function getProcess(ClaudeSession $session): ?Process
    {
        // First check if we have it in memory
        if (isset($this->processes[$session->id])) {
            return $this->processes[$session->id];
        }
        
        // If not, try to reconnect using PID
        if ($session->process_id) {
            // Check if process is still running using PID
            $pid = $session->process_id;
            
            // Check if process exists
            $checkCommand = PHP_OS_FAMILY === 'Windows' 
                ? "tasklist /FI \"PID eq {$pid}\" 2>NUL | find \"{$pid}\" >NUL"
                : "ps -p {$pid} > /dev/null 2>&1";
            
            exec($checkCommand, $output, $returnCode);
            
            if ($returnCode === 0) {
                // Process is running but we can't reattach to it with Symfony Process
                // We need to use a different approach - write to a named pipe or use a socket
                // For now, we'll return null and handle it differently
                Log::warning("Claude process is running but cannot reattach", [
                    'session_id' => $session->id,
                    'pid' => $pid
                ]);
                
                // Mark that the process exists even if we can't control it
                return null; // This will cause issues with sending commands
            } else {
                // Process is not running, update session
                $session->update(['status' => 'stopped', 'process_id' => null]);
            }
        }
        
        return null;
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