<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\GitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGitCommit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Project $project
    ) {}

    public function handle(): void
    {
        if (!$this->project->git_enabled || $this->project->commit_frequency !== 'time_based') {
            return;
        }

        $gitService = app(GitService::class);
        
        // Create commit with timestamp
        $message = "Auto-commit at " . now()->format('Y-m-d H:i:s');
        $success = $gitService->commit($this->project, $message);
        
        if ($success) {
            Log::info('Time-based auto-commit created', [
                'project_id' => $this->project->id,
            ]);
        }
        
        // Schedule next commit if still time-based
        if ($this->project->commit_frequency === 'time_based' && $this->project->commit_time_interval) {
            self::dispatch($this->project)
                ->delay(now()->addMinutes($this->project->commit_time_interval));
        }
    }
}