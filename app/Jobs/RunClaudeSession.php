<?php

namespace App\Jobs;

use App\Models\ClaudeSession;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RunClaudeSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    
    protected ClaudeSession $session;
    protected Project $project;

    /**
     * Create a new job instance.
     */
    public function __construct(ClaudeSession $session, Project $project)
    {
        $this->session = $session;
        $this->project = $project;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting Claude session job", [
            'session_id' => $this->session->id,
            'project_id' => $this->project->id
        ]);
        
        // Create communication directory
        $communicationDir = storage_path("app/claude-sessions/{$this->session->id}");
        if (!file_exists($communicationDir)) {
            mkdir($communicationDir, 0755, true);
        }
        
        $inputFile = "{$communicationDir}/input.fifo";
        $outputFile = "{$communicationDir}/output.txt";
        
        // Create named pipe for input
        if (file_exists($inputFile)) {
            unlink($inputFile);
        }
        posix_mkfifo($inputFile, 0666);
        
        // Clear output file
        file_put_contents($outputFile, '');
        
        // Build command to run Claude with input/output redirection
        $command = sprintf(
            'claude chat < %s > %s 2>&1',
            escapeshellarg($inputFile),
            escapeshellarg($outputFile)
        );
        
        $process = Process::fromShellCommandline($command);
        $process->setWorkingDirectory($this->project->project_path);
        $process->setTimeout(null);
        $process->setIdleTimeout(300); // 5 minutes idle timeout
        
        // Start the process
        $process->start();
        
        // Get PID and update session
        $pid = $process->getPid();
        $this->session->update([
            'process_id' => $pid,
            'status' => 'running',
            'started_at' => now(),
            'last_activity' => now(),
        ]);
        
        Log::info("Claude process started", [
            'session_id' => $this->session->id,
            'pid' => $pid
        ]);
        
        // Wait for process to finish
        $process->wait();
        
        // Process ended
        $this->session->update([
            'status' => 'stopped',
            'process_id' => null,
        ]);
        
        Log::info("Claude process ended", [
            'session_id' => $this->session->id
        ]);
    }
}
