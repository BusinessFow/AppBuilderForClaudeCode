<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_project_creation_is_logged(): void
    {
        $project = Project::factory()->create([
            'name' => 'Test Project',
            'project_type' => 'web',
            'framework' => 'Laravel',
        ]);
        
        // Check that a log entry was created
        $this->assertDatabaseHas('system_logs', [
            'level' => 'info',
            'channel' => 'projects',
            'message' => 'Project created: Test Project',
        ]);
        
        $log = SystemLog::where('message', 'Project created: Test Project')->first();
        $this->assertNotNull($log);
        $this->assertEquals($project->id, $log->context['project_id']);
        $this->assertEquals('web', $log->context['project_type']);
        $this->assertEquals('Laravel', $log->context['framework']);
    }

    public function test_logs_include_request_information(): void
    {
        // Simulate a request
        $this->withHeaders([
            'User-Agent' => 'Test Browser 1.0',
        ]);
        
        SystemLog::info('Test log with request info', [], 'test');
        
        $log = SystemLog::latest()->first();
        
        $this->assertEquals('Test Browser 1.0', $log->user_agent);
        $this->assertEquals('127.0.0.1', $log->ip_address);
        $this->assertStringContainsString('http', $log->url);
    }

    public function test_different_log_levels(): void
    {
        $levels = [
            'debug' => 'Debug message',
            'info' => 'Info message',
            'success' => 'Success message',
            'warning' => 'Warning message',
            'error' => 'Error message',
        ];
        
        foreach ($levels as $level => $message) {
            SystemLog::$level($message, [], 'test');
        }
        
        foreach ($levels as $level => $message) {
            $this->assertDatabaseHas('system_logs', [
                'level' => $level,
                'message' => $message,
                'channel' => 'test',
            ]);
        }
        
        $this->assertEquals(5, SystemLog::count());
    }

    public function test_logs_can_be_filtered_by_channel(): void
    {
        SystemLog::info('System log', [], 'system');
        SystemLog::info('Project log', [], 'projects');
        SystemLog::info('Claude log', [], 'claude');
        SystemLog::info('API log', [], 'api');
        
        $this->assertEquals(1, SystemLog::where('channel', 'system')->count());
        $this->assertEquals(1, SystemLog::where('channel', 'projects')->count());
        $this->assertEquals(1, SystemLog::where('channel', 'claude')->count());
        $this->assertEquals(1, SystemLog::where('channel', 'api')->count());
    }

    public function test_logs_can_be_filtered_by_date(): void
    {
        // Create logs with different dates
        SystemLog::factory()->create([
            'created_at' => now()->subDays(2),
        ]);
        
        SystemLog::factory()->create([
            'created_at' => now()->subDay(),
        ]);
        
        SystemLog::factory()->create([
            'created_at' => now(),
        ]);
        
        // Test filtering by today
        $todayLogs = SystemLog::whereDate('created_at', today())->count();
        $this->assertEquals(1, $todayLogs);
        
        // Test filtering by last 24 hours
        $last24Hours = SystemLog::where('created_at', '>=', now()->subDay())->count();
        $this->assertEquals(2, $last24Hours);
    }
}