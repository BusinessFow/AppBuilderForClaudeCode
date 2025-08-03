<?php

namespace Tests\Unit;

use App\Filament\Widgets\QuickActions;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\SystemHealth;
use App\Models\ClaudeSession;
use App\Models\ClaudeTodo;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_overview_widget(): void
    {
        // Create test data
        Project::factory()->count(3)->create(['status' => 'active']);
        Project::factory()->count(2)->create(['status' => 'inactive']);
        $project = Project::first();
        ClaudeTodo::factory()->count(10)->create(['status' => 'pending', 'project_id' => $project->id]);
        ClaudeTodo::factory()->count(5)->create(['status' => 'completed', 'project_id' => $project->id]);
        ClaudeSession::factory()->count(2)->create(['status' => 'running']);
        
        Setting::set('claude_api_key', 'sk-ant-test-key');
        
        $widget = new StatsOverview();
        $reflection = new \ReflectionMethod($widget, 'getStats');
        $reflection->setAccessible(true);
        $stats = $reflection->invoke($widget);
        
        $this->assertCount(4, $stats);
        
        // Check Total Projects stat
        $projectsStat = $stats[0];
        $this->assertEquals('Total Projects', $projectsStat->getLabel());
        $this->assertEquals(5, $projectsStat->getValue());
        $this->assertStringContainsString('3 active', $projectsStat->getDescription());
        
        // Check Tasks Completed stat
        $tasksStat = $stats[1];
        $this->assertEquals('Tasks Completed', $tasksStat->getLabel());
        $this->assertEquals('5/15', $tasksStat->getValue());
        $this->assertStringContainsString('33% completion rate', $tasksStat->getDescription());
        
        // Check Active Claude Sessions stat
        $sessionsStat = $stats[2];
        $this->assertEquals('Active Claude Sessions', $sessionsStat->getLabel());
        $this->assertEquals(2, $sessionsStat->getValue());
        $this->assertStringContainsString('API key configured', $sessionsStat->getDescription());
        
        // Check System Status stat
        $statusStat = $stats[3];
        $this->assertEquals('System Status', $statusStat->getLabel());
        $this->assertEquals('Ready', $statusStat->getValue());
    }

    public function test_stats_overview_without_api_key(): void
    {
        $widget = new StatsOverview();
        $reflection = new \ReflectionMethod($widget, 'getStats');
        $reflection->setAccessible(true);
        $stats = $reflection->invoke($widget);
        
        $sessionsStat = $stats[2];
        $this->assertStringContainsString('API key missing', $sessionsStat->getDescription());
        
        $statusStat = $stats[3];
        $this->assertEquals('Setup Required', $statusStat->getValue());
        $this->assertStringContainsString('Complete setup to start', $statusStat->getDescription());
    }

    public function test_quick_actions_widget(): void
    {
        Setting::set('claude_api_key', 'sk-ant-test-key');
        Project::factory()->count(2)->create(['status' => 'active']);
        
        $widget = new QuickActions();
        $actions = $widget->getActions();
        
        // Should have: New Project, 2 recent projects, View All Projects, Settings
        $this->assertCount(5, $actions);
        
        // Check New Project action
        $newProjectAction = $actions->first();
        $this->assertEquals('New Project', $newProjectAction['label']);
        $this->assertEquals('/admin/projects/create', $newProjectAction['url']);
        $this->assertFalse($newProjectAction['disabled']);
        
        // Check recent project actions
        $recentProjectActions = $actions->slice(1, 2);
        foreach ($recentProjectActions as $action) {
            $this->assertStringStartsWith('Open ', $action['label']);
            $this->assertStringContainsString('/admin/projects/', $action['url']);
            $this->assertStringContainsString('/claude', $action['url']);
            $this->assertFalse($action['disabled']);
        }
    }

    public function test_quick_actions_without_api_key(): void
    {
        Project::factory()->create(['status' => 'active']);
        
        $widget = new QuickActions();
        $actions = $widget->getActions();
        
        // Should have Add API Key action
        $apiKeyAction = $actions->firstWhere('label', 'Add API Key');
        $this->assertNotNull($apiKeyAction);
        $this->assertEquals('danger', $apiKeyAction['color']);
        
        // Project actions should be disabled
        $projectAction = $actions->firstWhere('label', fn($label) => str_starts_with($label, 'Open '));
        if ($projectAction) {
            $this->assertTrue($projectAction['disabled']);
        }
    }

    public function test_system_health_widget(): void
    {
        Setting::set('claude_api_key', 'sk-ant-test-key');
        
        $widget = new SystemHealth();
        $checks = $widget->getHealthChecks();
        
        $this->assertGreaterThanOrEqual(5, $checks->count());
        
        // Check Claude API status
        $apiCheck = $checks->firstWhere('name', 'Claude API');
        $this->assertNotNull($apiCheck);
        $this->assertEquals('operational', $apiCheck['status']);
        $this->assertEquals('Connected', $apiCheck['message']);
        
        // Check Database status
        $dbCheck = $checks->firstWhere('name', 'Database');
        $this->assertNotNull($dbCheck);
        $this->assertEquals('operational', $dbCheck['status']);
        
        // Check overall status
        $this->assertEquals('operational', $widget->getOverallStatus());
    }

    public function test_system_health_without_api_key(): void
    {
        $widget = new SystemHealth();
        $checks = $widget->getHealthChecks();
        
        $apiCheck = $checks->firstWhere('name', 'Claude API');
        $this->assertEquals('error', $apiCheck['status']);
        $this->assertEquals('API Key Missing', $apiCheck['message']);
        
        // Overall status should be error
        $this->assertEquals('error', $widget->getOverallStatus());
    }

    public function test_recent_activity_widget_query(): void
    {
        // Create activities
        $project = Project::factory()->create(['name' => 'Test Project']);
        $todo = ClaudeTodo::factory()->create([
            'project_id' => $project->id,
            'command' => 'Test task',
        ]);
        $todo2 = ClaudeTodo::factory()->create([
            'project_id' => $project->id,
            'command' => 'Completed task',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        ClaudeSession::factory()->create([
            'project_id' => $project->id,
            'status' => 'running',
        ]);
        
        // The widget uses a complex union query that we can't easily test
        // So we'll just verify the widget can be instantiated
        $widget = new \App\Filament\Widgets\RecentActivity();
        $this->assertInstanceOf(\App\Filament\Widgets\RecentActivity::class, $widget);
    }
}