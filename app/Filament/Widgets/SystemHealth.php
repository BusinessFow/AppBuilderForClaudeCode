<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use App\Services\ClaudeProcessManager;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class SystemHealth extends Widget
{
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];
    
    protected string $view = 'filament.widgets.system-health';
    
    public function getHealthChecks(): Collection
    {
        $checks = collect();
        
        // API Key Check
        $hasApiKey = !empty(Setting::get('claude_api_key', ''));
        $checks->push([
            'name' => 'Claude API',
            'status' => $hasApiKey ? 'operational' : 'error',
            'message' => $hasApiKey ? 'Connected' : 'API Key Missing',
            'icon' => $hasApiKey ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle',
        ]);
        
        // Claude CLI Check
        try {
            $claudeCommand = 'claude --version 2>&1';
            exec($claudeCommand, $output, $returnCode);
            $claudeInstalled = $returnCode === 0;
            $checks->push([
                'name' => 'Claude CLI',
                'status' => $claudeInstalled ? 'operational' : 'warning',
                'message' => $claudeInstalled ? 'Installed' : 'Not Installed',
                'icon' => $claudeInstalled ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle',
            ]);
        } catch (\Exception $e) {
            $checks->push([
                'name' => 'Claude CLI',
                'status' => 'error',
                'message' => 'Check Failed',
                'icon' => 'heroicon-o-x-circle',
            ]);
        }
        
        // Database Check
        try {
            \DB::connection()->getPdo();
            $checks->push([
                'name' => 'Database',
                'status' => 'operational',
                'message' => 'Connected',
                'icon' => 'heroicon-o-check-circle',
            ]);
        } catch (\Exception $e) {
            $checks->push([
                'name' => 'Database',
                'status' => 'error',
                'message' => 'Connection Failed',
                'icon' => 'heroicon-o-x-circle',
            ]);
        }
        
        // Storage Check
        $storageWritable = is_writable(storage_path());
        $checks->push([
            'name' => 'Storage',
            'status' => $storageWritable ? 'operational' : 'error',
            'message' => $storageWritable ? 'Writable' : 'Not Writable',
            'icon' => $storageWritable ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle',
        ]);
        
        // PHP Version Check
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.2.0', '>=');
        $checks->push([
            'name' => 'PHP Version',
            'status' => $phpOk ? 'operational' : 'warning',
            'message' => $phpVersion,
            'icon' => $phpOk ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle',
        ]);
        
        return $checks;
    }
    
    public function getOverallStatus(): string
    {
        $checks = $this->getHealthChecks();
        
        if ($checks->contains('status', 'error')) {
            return 'error';
        }
        
        if ($checks->contains('status', 'warning')) {
            return 'warning';
        }
        
        return 'operational';
    }
}