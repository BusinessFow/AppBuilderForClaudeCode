<?php

namespace App\Jobs;

use App\Models\ClaudeSession;
use App\Models\ClaudeTodo;
use App\Services\ClaudeProcessManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessClaudeTodos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ClaudeSession $session
    ) {}

    public function handle(): void
    {
        // Check if session is still running
        if (!$session->isRunning()) {
            return;
        }

        $manager = app(ClaudeProcessManager::class);

        // Check if Claude process is actually running
        if (!$manager->isRunning($this->session)) {
            $this->session->update(['status' => 'stopped']);
            return;
        }

        // Get next pending TODO for this project
        $todo = $this->session->project->pendingTodos()
            ->byPriority()
            ->first();

        if (!$todo) {
            // No pending todos, check again in 10 seconds
            self::dispatch($this->session)->delay(now()->addSeconds(10));
            return;
        }

        try {
            // Mark as processing
            $todo->markAsProcessing();

            // Send command to Claude
            $manager->sendCommand($this->session, $todo->command);

            // Wait for response (with timeout)
            $maxWaitTime = 30; // 30 seconds max
            $startTime = time();
            $output = null;

            while ((time() - $startTime) < $maxWaitTime) {
                sleep(1);
                $output = $manager->getOutput($this->session);
                
                if ($output) {
                    break;
                }
            }

            if ($output) {
                $todo->markAsCompleted($output);
                
                // Trigger Git commit if needed
                $manager->onTodoCompleted($this->session, $todo->command);
                
                Log::info('Completed Claude TODO', [
                    'todo_id' => $todo->id,
                    'command' => $todo->command,
                ]);
            } else {
                $todo->markAsFailed('No response from Claude within timeout');
                Log::warning('Claude TODO timed out', [
                    'todo_id' => $todo->id,
                    'command' => $todo->command,
                ]);
            }

        } catch (\Exception $e) {
            $todo->markAsFailed($e->getMessage());
            Log::error('Failed to process Claude TODO', [
                'todo_id' => $todo->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Schedule next check
        self::dispatch($this->session)->delay(now()->addSeconds(2));
    }
}