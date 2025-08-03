<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_loads_successfully(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get('/admin');
        
        $response->assertStatus(200);
        $response->assertSee('Dashboard');
        $response->assertSee('AppBuilder for Claude');
    }

    public function test_system_logs_are_created(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create some logs
        SystemLog::info('Test info log', [], 'test');
        SystemLog::success('Test success log', [], 'test');
        SystemLog::warning('Test warning log', [], 'test');
        SystemLog::error('Test error log', [], 'test');
        
        // Verify logs were created
        $this->assertDatabaseHas('system_logs', [
            'level' => 'info',
            'message' => 'Test info log',
        ]);
        
        $this->assertDatabaseHas('system_logs', [
            'level' => 'success',
            'message' => 'Test success log',
        ]);
        
        $this->assertDatabaseHas('system_logs', [
            'level' => 'warning',
            'message' => 'Test warning log',
        ]);
        
        $this->assertDatabaseHas('system_logs', [
            'level' => 'error',
            'message' => 'Test error log',
        ]);
        
        $this->assertEquals(4, SystemLog::count());
    }

    public function test_system_logs_capture_context(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        SystemLog::info('Test with context', [
            'key' => 'value',
            'number' => 123,
        ], 'test');
        
        $log = SystemLog::first();
        
        $this->assertEquals('info', $log->level);
        $this->assertEquals('test', $log->channel);
        $this->assertEquals('Test with context', $log->message);
        $this->assertEquals(['key' => 'value', 'number' => 123], $log->context);
        $this->assertEquals($user->id, $log->user_id);
    }
}