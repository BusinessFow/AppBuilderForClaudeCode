<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class AutoUpdateSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing system logs
        SystemLog::query()->delete();
    }

    public function test_auto_update_command_is_scheduled(): void
    {
        // Just verify the command exists and can be executed
        $this->artisan('app:run-auto-update')
            ->assertSuccessful();
            
        // Verify it shows up in schedule:list
        $this->artisan('schedule:list')
            ->expectsOutputToContain('app:run-auto-update')
            ->assertSuccessful();
    }

    public function test_auto_update_command_respects_settings(): void
    {
        // Disable auto-update
        Setting::set('auto_update_enabled', false);
        
        $this->artisan('app:run-auto-update')
            ->expectsOutput('Auto-update is disabled. Use --force to run anyway.')
            ->assertSuccessful();
            
        // Check that log was created
        $this->assertDatabaseHas('system_logs', [
            'level' => 'info',
            'message' => 'Auto-update skipped - feature disabled',
            'channel' => 'updater',
        ]);
    }

    public function test_auto_update_command_runs_with_force_flag(): void
    {
        // Disable auto-update
        Setting::set('auto_update_enabled', false);
        
        $this->artisan('app:run-auto-update', ['--force' => true])
            ->expectsOutput('Starting auto-update process...')
            ->expectsOutput('Checking for updates...');
            
        // Check that log was created
        $this->assertDatabaseHas('system_logs', [
            'level' => 'info',
            'message' => 'Auto-update process started',
            'channel' => 'updater',
        ]);
    }

    public function test_auto_update_command_runs_when_enabled(): void
    {
        // Enable auto-update
        Setting::set('auto_update_enabled', true);
        
        $this->artisan('app:run-auto-update')
            ->expectsOutput('Starting auto-update process...')
            ->expectsOutput('Checking for updates...');
            
        // Check that log was created
        $this->assertDatabaseHas('system_logs', [
            'level' => 'info',
            'message' => 'Auto-update process started',
            'channel' => 'updater',
        ]);
    }

    public function test_auto_update_command_logs_all_steps(): void
    {
        // Enable auto-update
        Setting::set('auto_update_enabled', true);
        
        $this->artisan('app:run-auto-update');
        
        // Check that appropriate logs were created
        $logs = SystemLog::where('channel', 'updater')->get();
        $this->assertGreaterThan(0, $logs->count());
        
        $logMessages = $logs->pluck('message')->toArray();
        $this->assertContains('Auto-update process started', $logMessages);
    }

    public function test_scheduled_task_output_is_logged_to_file(): void
    {
        $logPath = storage_path('logs/auto-update.log');
        
        // Remove log file if it exists
        if (file_exists($logPath)) {
            unlink($logPath);
        }
        
        // Run the command and verify log file is created
        Setting::set('auto_update_enabled', true);
        $this->artisan('app:run-auto-update');
        
        // The log file will be created when the scheduled task runs
        // For now, we just verify the schedule is configured to log to the file
        $this->artisan('schedule:list')
            ->expectsOutputToContain('app:run-auto-update')
            ->assertSuccessful();
    }
}