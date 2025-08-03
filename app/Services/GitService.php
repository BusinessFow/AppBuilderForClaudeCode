<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class GitService
{
    /**
     * Initialize Git repository for a project
     */
    public function initializeRepository(Project $project): bool
    {
        if (!$project->git_enabled) {
            return false;
        }

        try {
            // Check if already initialized
            $checkProcess = new Process(['git', 'status'], $project->project_path);
            $checkProcess->run();
            
            if ($checkProcess->isSuccessful()) {
                Log::info('Git repository already initialized', ['project_id' => $project->id]);
                return true;
            }

            // Initialize repository
            $initProcess = new Process(['git', 'init'], $project->project_path);
            $initProcess->run();

            if (!$initProcess->isSuccessful()) {
                throw new ProcessFailedException($initProcess);
            }

            // Configure user
            $this->configureUser($project);

            // Add remote if provided
            if ($project->git_remote_url) {
                $this->addRemote($project);
            }

            // Create initial branch
            if ($project->git_branch && $project->git_branch !== 'main' && $project->git_branch !== 'master') {
                $branchProcess = new Process(['git', 'checkout', '-b', $project->git_branch], $project->project_path);
                $branchProcess->run();
            }

            Log::info('Git repository initialized successfully', ['project_id' => $project->id]);
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to initialize Git repository', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Configure Git user for the project
     */
    public function configureUser(Project $project): void
    {
        if ($project->git_username) {
            $process = new Process(['git', 'config', 'user.name', $project->git_username], $project->project_path);
            $process->run();
        }

        if ($project->git_email) {
            $process = new Process(['git', 'config', 'user.email', $project->git_email], $project->project_path);
            $process->run();
        }
    }

    /**
     * Add remote repository
     */
    public function addRemote(Project $project): bool
    {
        if (!$project->git_remote_url) {
            return false;
        }

        try {
            // Check if remote already exists
            $checkProcess = new Process(['git', 'remote', 'get-url', 'origin'], $project->project_path);
            $checkProcess->run();

            if ($checkProcess->isSuccessful()) {
                // Update existing remote
                $process = new Process(['git', 'remote', 'set-url', 'origin', $project->git_remote_url], $project->project_path);
            } else {
                // Add new remote
                $process = new Process(['git', 'remote', 'add', 'origin', $project->git_remote_url], $project->project_path);
            }

            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to add Git remote', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create a commit
     */
    public function commit(Project $project, string $message, array $files = []): bool
    {
        if (!$project->git_enabled) {
            return false;
        }

        try {
            // Run tests if required
            if ($project->commit_with_tests && !empty($project->test_commands)) {
                foreach ($project->test_commands as $testCommand) {
                    $testProcess = new Process(explode(' ', $testCommand), $project->project_path);
                    $testProcess->setTimeout(300); // 5 minute timeout
                    $testProcess->run();

                    if (!$testProcess->isSuccessful()) {
                        Log::warning('Tests failed, skipping commit', [
                            'project_id' => $project->id,
                            'command' => $testCommand,
                            'output' => $testProcess->getErrorOutput(),
                        ]);
                        return false;
                    }
                }
            }

            // Add files
            if (empty($files)) {
                // Add all changes
                $addProcess = new Process(['git', 'add', '-A'], $project->project_path);
            } else {
                // Add specific files
                $addProcess = new Process(array_merge(['git', 'add'], $files), $project->project_path);
            }
            $addProcess->run();

            if (!$addProcess->isSuccessful()) {
                throw new ProcessFailedException($addProcess);
            }

            // Format commit message
            $formattedMessage = $this->formatCommitMessage($project, $message);

            // Create commit
            $commitProcess = new Process(['git', 'commit', '-m', $formattedMessage], $project->project_path);
            $commitProcess->run();

            if (!$commitProcess->isSuccessful()) {
                // Check if there's nothing to commit
                if (strpos($commitProcess->getErrorOutput(), 'nothing to commit') !== false) {
                    Log::info('Nothing to commit', ['project_id' => $project->id]);
                    return true;
                }
                throw new ProcessFailedException($commitProcess);
            }

            Log::info('Commit created successfully', [
                'project_id' => $project->id,
                'message' => $formattedMessage,
            ]);

            // Auto push if enabled
            if ($project->auto_push && $project->push_frequency === 'after_each_commit') {
                $this->push($project);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to create commit', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Push to remote repository
     */
    public function push(Project $project, bool $force = null): bool
    {
        if (!$project->git_enabled || !$project->git_remote_url) {
            return false;
        }

        try {
            $force = $force ?? $project->push_force;
            
            $command = ['git', 'push', 'origin', $project->git_branch ?? 'HEAD'];
            if ($force) {
                $command[] = '--force';
            }

            $process = new Process($command, $project->project_path);
            $process->setTimeout(120); // 2 minute timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            Log::info('Pushed to remote successfully', ['project_id' => $project->id]);
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to push to remote', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create a feature branch
     */
    public function createFeatureBranch(Project $project, string $taskName): ?string
    {
        if (!$project->git_enabled || !$project->create_feature_branches) {
            return null;
        }

        try {
            $branchName = $this->formatBranchName($project, $taskName);
            
            $process = new Process(['git', 'checkout', '-b', $branchName], $project->project_path);
            $process->run();

            if (!$process->isSuccessful()) {
                // Try to checkout existing branch
                $checkoutProcess = new Process(['git', 'checkout', $branchName], $project->project_path);
                $checkoutProcess->run();
                
                if (!$checkoutProcess->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
            }

            Log::info('Created/switched to feature branch', [
                'project_id' => $project->id,
                'branch' => $branchName,
            ]);

            return $branchName;

        } catch (\Exception $e) {
            Log::error('Failed to create feature branch', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Format commit message based on template
     */
    private function formatCommitMessage(Project $project, string $message): string
    {
        if (!$project->commit_message_template) {
            return $message;
        }

        $replacements = [
            '{description}' => $message,
            '{timestamp}' => now()->format('Y-m-d H:i:s'),
            '{type}' => 'feat', // Could be determined from message
            '{task_id}' => 'TASK-' . rand(1000, 9999), // Could be linked to actual task
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $project->commit_message_template
        );
    }

    /**
     * Format branch name based on pattern
     */
    private function formatBranchName(Project $project, string $taskName): string
    {
        if (!$project->branch_naming_pattern) {
            return 'feature/' . $this->slugify($taskName);
        }

        $replacements = [
            '{task}' => $this->slugify($taskName),
            '{type}' => 'feature',
            '{date}' => now()->format('Ymd'),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $project->branch_naming_pattern
        );
    }

    /**
     * Convert string to slug
     */
    private function slugify(string $text): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    }
}