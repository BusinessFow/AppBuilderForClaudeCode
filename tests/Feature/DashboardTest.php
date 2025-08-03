<?php

namespace Tests\Feature;

use App\Models\ClaudeSession;
use App\Models\ClaudeTodo;
use App\Models\Project;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create();
    }

    public function test_dashboard_page_loads_successfully(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Dashboard');
    }

    public function test_onboarding_checklist_widget_displays(): void
    {
        // Set some settings
        Setting::set('claude_api_key', 'test-key');
        
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Getting Started with AppBuilder')
            ->assertSee('Set up API Key');
    }

    public function test_stats_overview_widget_displays_correct_counts(): void
    {
        // Create test data
        Project::factory()->count(3)->create(['status' => 'active']);
        Project::factory()->count(2)->create(['status' => 'completed']);
        ClaudeTodo::factory()->count(5)->create(['status' => 'completed']);
        ClaudeTodo::factory()->count(3)->create(['status' => 'pending']);
        ClaudeSession::factory()->count(2)->create(['status' => 'running']);
        
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Total Projects')
            ->assertSee('5') // Total projects
            ->assertSee('Tasks Completed')
            ->assertSee('5/8') // 5 completed out of 8 total
            ->assertSee('Active Claude Sessions')
            ->assertSee('2'); // Active sessions
    }

    public function test_quick_actions_widget_shows_api_key_warning(): void
    {
        // Ensure no API key is set
        Setting::where('key', 'claude_api_key')->delete();
        
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Quick Actions')
            ->assertSee('Add API Key')
            ->assertSee('Configure Claude API');
    }

    public function test_quick_actions_widget_shows_recent_projects(): void
    {
        // Set API key
        Setting::set('claude_api_key', 'test-key');
        
        // Create recent projects
        $project1 = Project::factory()->create(['name' => 'Test Project 1', 'status' => 'active']);
        $project2 = Project::factory()->create(['name' => 'Test Project 2', 'status' => 'active']);
        
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Quick Actions')
            ->assertSee('Open Test Project 1')
            ->assertSee('Open Test Project 2');
    }

    public function test_system_health_widget_checks_all_components(): void
    {
        // Set API key
        Setting::set('claude_api_key', 'test-key');
        
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('System Health')
            ->assertSee('Claude API')
            ->assertSee('Database')
            ->assertSee('Storage')
            ->assertSee('PHP Version');
    }

    public function test_recent_activity_widget_shows_activities(): void
    {
        // Create some activities
        $project = Project::factory()->create(['name' => 'Activity Test']);
        ClaudeTodo::factory()->create([
            'project_id' => $project->id,
            'command' => 'Test Task',
            'created_at' => now()->subHours(1),
        ]);
        ClaudeSession::factory()->create([
            'project_id' => $project->id,
            'status' => 'running',
            'created_at' => now()->subMinutes(30),
        ]);
        
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Recent Activity');
    }

    public function test_system_logs_widget_displays_logs(): void
    {
        // Create some logs
        SystemLog::info('Test info log', [], 'test');
        SystemLog::warning('Test warning log', [], 'test');
        SystemLog::error('Test error log', [], 'test');
        
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('System Logs')
            ->assertSee('Test info log')
            ->assertSee('Test warning log')
            ->assertSee('Test error log');
    }

    public function test_dashboard_handles_no_data_gracefully(): void
    {
        // Ensure no data exists
        Project::query()->delete();
        ClaudeTodo::query()->delete();
        ClaudeSession::query()->delete();
        SystemLog::query()->delete();
        
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Dashboard')
            ->assertSee('0'); // Should see zeros in stats
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_all_widgets_load_without_errors(): void
    {
        // Create comprehensive test data
        Setting::set('claude_api_key', 'test-key');
        $project = Project::factory()->create();
        ClaudeTodo::factory()->count(3)->create(['project_id' => $project->id]);
        ClaudeSession::factory()->create(['project_id' => $project->id]);
        SystemLog::info('Widget test log', [], 'test');
        
        $response = $this->actingAs($this->admin)->get('/admin');
        
        $response->assertSuccessful();
        $response->assertSessionHasNoErrors();
        
        // Check that all widgets are present
        $response->assertSee('Getting Started with AppBuilder');
        $response->assertSee('Quick Actions');
        $response->assertSee('System Health');
        $response->assertSee('Recent Activity');
        $response->assertSee('System Logs');
        $response->assertSee('Total Projects');
    }
}