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
            // Create screen session name
            $screenName = "claude_session_{$session->id}";
            
            // Kill any existing screen session with same name
            exec("screen -S {$screenName} -X quit 2>/dev/null");
            
            // Create communication directory
            $communicationDir = storage_path("app/claude-sessions/{$session->id}");
            if (!file_exists($communicationDir)) {
                mkdir($communicationDir, 0755, true);
            }
            
            // Create log file
            $logFile = "{$communicationDir}/claude.log";
            
            // Create named pipe for input
            $inputPipe = "{$communicationDir}/input.pipe";
            if (file_exists($inputPipe)) {
                unlink($inputPipe);
            }
            posix_mkfifo($inputPipe, 0666);
            
            // Build command to run Claude in screen session with named pipe
            $claudeCommand = sprintf(
                'export PATH="/usr/local/bin:$PATH" && cd %s && echo "Working directory: $(pwd)" && (tail -f %s | /usr/local/bin/claude chat --no-color 2>&1 | tee %s)',
                escapeshellarg($project->project_path),
                escapeshellarg($inputPipe),
                escapeshellarg($logFile)
            );
            
            // Start screen session with Claude
            $screenCommand = sprintf(
                'screen -dmS %s bash -c %s',
                escapeshellarg($screenName),
                escapeshellarg($claudeCommand)
            );
            
            exec($screenCommand, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \Exception("Failed to start screen session");
            }
            
            // Wait a moment for screen to start
            sleep(1);
            
            // Get the PID of the screen session - different approach
            exec("screen -ls | grep {$screenName}", $screenOutput);
            $pid = null;
            
            if (!empty($screenOutput[0])) {
                // Parse screen output: "12345.claude_session_1  (Detached)"
                if (preg_match('/^\s*(\d+)\./', $screenOutput[0], $matches)) {
                    $pid = $matches[1];
                }
            }
            
            // If still no PID, use the screen name as identifier
            if (!$pid) {
                $pid = $screenName;
            }
            
            Log::info("Screen session PID detection", [
                'screen_output' => $screenOutput,
                'extracted_pid' => $pid
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
            
            // Send command via screen
            if ($session->process_id) {
                // Build screen session name
                $screenName = "claude_session_{$session->id}";
                
                // Check if screen session exists
                exec("screen -ls | grep {$screenName}", $screenCheck, $returnCode);
                
                if (empty($screenCheck)) {
                    // Screen session not found
                    $session->update(['status' => 'stopped', 'process_id' => null]);
                    throw new \Exception('Claude screen session is not running');
                }
                
                // Send command via named pipe
                $communicationDir = storage_path("app/claude-sessions/{$session->id}");
                $inputPipe = "{$communicationDir}/input.pipe";
                
                if (!file_exists($inputPipe)) {
                    throw new \Exception('Input pipe does not exist');
                }
                
                // Write to named pipe
                $pipe = fopen($inputPipe, 'w');
                if (!$pipe) {
                    throw new \Exception('Could not open input pipe for writing');
                }
                
                fwrite($pipe, $command . "\n");
                fclose($pipe);
                
                Log::info("Sent command via named pipe", [
                    'session_id' => $session->id,
                    'command' => $command,
                    'pipe_path' => $inputPipe
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
        // Kill screen session
        if ($session->id) {
            $screenName = "claude_session_{$session->id}";
            exec("screen -S {$screenName} -X quit 2>/dev/null");
            
            Log::info("Stopped screen session", [
                'session_id' => $session->id,
                'screen_name' => $screenName
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
        // Check if screen session is running
        if ($session->process_id) {
            // If process_id starts with "claude_session_", it's a screen name
            if (str_starts_with($session->process_id, 'claude_session_')) {
                $screenName = $session->process_id;
            } else {
                $screenName = "claude_session_{$session->id}";
            }
            
            exec("screen -ls | grep {$screenName}", $screenCheck);
            
            if (!empty($screenCheck)) {
                // Screen session exists
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