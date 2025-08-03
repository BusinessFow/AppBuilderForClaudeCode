<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClaudeSession;
use App\Models\Project;
use App\Services\ClaudeProcessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaudeController extends Controller
{
    protected ClaudeProcessManager $claudeManager;

    public function __construct(ClaudeProcessManager $claudeManager)
    {
        $this->claudeManager = $claudeManager;
    }

    public function getSession(Project $project): JsonResponse
    {
        $session = $project->activeClaudeSession;
        
        if (!$session) {
            return response()->json([
                'status' => 'not_started',
                'session' => null,
            ]);
        }

        // Check if process is still running
        $isRunning = $this->claudeManager->isRunning($session);
        
        return response()->json([
            'status' => $session->status,
            'session' => [
                'id' => $session->id,
                'process_id' => $session->process_id,
                'is_running' => $isRunning,
                'last_activity' => $session->last_activity,
                'messages' => $session->conversation_history ?? [],
            ],
        ]);
    }

    public function sendCommand(Request $request, Project $project): JsonResponse
    {
        $request->validate([
            'command' => 'required|string',
        ]);

        $session = $project->activeClaudeSession;
        
        if (!$session || !$session->isRunning()) {
            return response()->json([
                'error' => 'No active Claude session',
            ], 400);
        }

        try {
            $this->claudeManager->sendCommand($session, $request->command);
            
            // Wait briefly for response
            sleep(1);
            
            // Get output
            $output = $this->claudeManager->getOutput($session);
            
            return response()->json([
                'success' => true,
                'output' => $output,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOutput(Project $project): JsonResponse
    {
        $session = $project->activeClaudeSession;
        
        if (!$session) {
            return response()->json([
                'output' => null,
                'is_running' => false,
            ]);
        }

        $output = $this->claudeManager->getOutput($session);
        $isRunning = $this->claudeManager->isRunning($session);
        
        return response()->json([
            'output' => $output,
            'is_running' => $isRunning,
            'last_activity' => $session->last_activity,
        ]);
    }

    public function streamOutput(Project $project): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->stream(function () use ($project) {
            echo "data: " . json_encode(['type' => 'connected']) . "\n\n";
            ob_flush();
            flush();
            
            $lastCheck = null;
            
            while (true) {
                $session = $project->activeClaudeSession()->first();
                
                if (!$session || !$this->claudeManager->isRunning($session)) {
                    echo "data: " . json_encode(['type' => 'disconnected']) . "\n\n";
                    break;
                }
                
                // Check for new output
                if (!$lastCheck || $session->last_activity > $lastCheck) {
                    $output = $this->claudeManager->getOutput($session);
                    if ($output) {
                        echo "data: " . json_encode([
                            'type' => 'output',
                            'content' => $output,
                            'timestamp' => now()->toIso8601String(),
                        ]) . "\n\n";
                    }
                    $lastCheck = $session->last_activity;
                }
                
                ob_flush();
                flush();
                
                // Check every second
                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}