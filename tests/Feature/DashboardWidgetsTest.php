<?php

namespace Tests\Feature;

use App\Filament\Widgets\OnboardingChecklist;
use App\Filament\Widgets\QuickActions;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\SystemHealth;
use App\Filament\Widgets\SystemLogs;
use App\Models\ClaudeSession;
use App\Models\ClaudeTodo;
use App\Models\Project;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_onboarding_checklist_widget_renders(): void
    {
        Setting::set('claude_api_key', 'test-key');
        
        Livewire::test(OnboardingChecklist::class)
            ->assertSee('Getting Started with AppBuilder')
            ->assertSee('Add API Key');
    }

    public function test_stats_overview_widget_shows_correct_data(): void
    {
        // Create test data
        Project::factory()->count(3)->create(['status' => 'active']);
        Project::factory()->count(2)->create(['status' => 'completed']);
        ClaudeTodo::factory()->count(5)->create(['status' => 'completed']);
        ClaudeTodo::factory()->count(3)->create(['status' => 'pending']);
        ClaudeSession::factory()->count(2)->create(['status' => 'running']);
        
        Livewire::test(StatsOverview::class)
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
        
        Livewire::test(QuickActions::class)
            ->assertSee('Quick Actions')
            ->assertSee('Add API Key')
            ->assertSee('Configure Claude API');
    }

    public function test_system_health_widget_checks_components(): void
    {
        Setting::set('claude_api_key', 'test-key');
        
        Livewire::test(SystemHealth::class)
            ->assertSee('System Health')
            ->assertSee('Claude API')
            ->assertSee('Database')
            ->assertSee('Storage')
            ->assertSee('PHP Version');
    }

    public function test_system_logs_widget_displays_logs(): void
    {
        // Create some logs
        SystemLog::info('Test info log', [], 'test');
        SystemLog::warning('Test warning log', [], 'test');
        SystemLog::error('Test error log', [], 'test');
        
        Livewire::test(SystemLogs::class)
            ->assertSee('System Logs')
            ->assertSee('Test info log')
            ->assertSee('Test warning log')
            ->assertSee('Test error log');
    }
}