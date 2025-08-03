<?php

namespace App\Observers;

use App\Jobs\ProcessGitCommit;
use App\Models\Project;
use App\Models\SystemLog;
use App\Services\GitService;

class ProjectObserver
{
    protected GitService $gitService;

    public function __construct(GitService $gitService)
    {
        $this->gitService = $gitService;
    }

    /**
     * Handle the Project "created" event.
     */
    public function created(Project $project): void
    {
        // Log project creation
        SystemLog::info("Project created: {$project->name}", [
            'project_id' => $project->id,
            'project_type' => $project->project_type,
            'framework' => $project->framework,
        ], 'projects');

        // Initialize Git repository if enabled
        if ($project->git_enabled) {
            try {
                $this->gitService->initializeRepository($project);
                SystemLog::success("Git repository initialized for project: {$project->name}", [
                    'project_id' => $project->id,
                ], 'git');
            } catch (\Exception $e) {
                SystemLog::error("Failed to initialize Git repository: {$e->getMessage()}", [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ], 'git');
            }
            
            // Schedule time-based commits if configured
            if ($project->commit_frequency === 'time_based' && $project->commit_time_interval) {
                ProcessGitCommit::dispatch($project)
                    ->delay(now()->addMinutes($project->commit_time_interval));
            }
        }
    }

    /**
     * Handle the Project "updated" event.
     */
    public function updated(Project $project): void
    {
        // If Git was just enabled, initialize repository
        if ($project->git_enabled && $project->wasChanged('git_enabled')) {
            $this->gitService->initializeRepository($project);
        }

        // If remote URL changed, update it
        if ($project->git_enabled && $project->wasChanged('git_remote_url')) {
            $this->gitService->addRemote($project);
        }

        // If user config changed, update it
        if ($project->git_enabled && ($project->wasChanged('git_username') || $project->wasChanged('git_email'))) {
            $this->gitService->configureUser($project);
        }

        // Handle time-based commit scheduling
        if ($project->wasChanged('commit_frequency') || $project->wasChanged('commit_time_interval')) {
            if ($project->commit_frequency === 'time_based' && $project->commit_time_interval) {
                // Cancel existing jobs and schedule new one
                ProcessGitCommit::dispatch($project)
                    ->delay(now()->addMinutes($project->commit_time_interval));
            }
        }
    }
}