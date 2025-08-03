<?php

namespace Tests\Unit;

use App\Filament\Widgets\OnboardingChecklist;
use App\Models\ClaudeSession;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingChecklistWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected OnboardingChecklist $widget;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->widget = new OnboardingChecklist();
    }

    public function test_checklist_items_structure(): void
    {
        $items = $this->widget->getChecklistItems();

        $this->assertCount(5, $items);
        
        foreach ($items as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('completed', $item);
            $this->assertArrayHasKey('action', $item);
            $this->assertArrayHasKey('action_label', $item);
            $this->assertArrayHasKey('icon', $item);
        }
    }

    public function test_api_key_check(): void
    {
        $items = $this->widget->getChecklistItems();
        $apiKeyItem = $items->firstWhere('label', 'Configure Claude API Key');
        
        // Without API key
        $this->assertFalse($apiKeyItem['completed']);
        
        // With API key
        Setting::set('claude_api_key', 'sk-ant-test-key-123456789');
        
        $widget = new OnboardingChecklist();
        $items = $widget->getChecklistItems();
        $apiKeyItem = $items->firstWhere('label', 'Configure Claude API Key');
        
        $this->assertTrue($apiKeyItem['completed']);
    }

    public function test_project_check(): void
    {
        $items = $this->widget->getChecklistItems();
        $projectItem = $items->firstWhere('label', 'Create Your First Project');
        
        // Without project
        $this->assertFalse($projectItem['completed']);
        
        // With project
        Project::factory()->create();
        
        $widget = new OnboardingChecklist();
        $items = $widget->getChecklistItems();
        $projectItem = $items->firstWhere('label', 'Create Your First Project');
        
        $this->assertTrue($projectItem['completed']);
    }

    public function test_general_settings_check(): void
    {
        $items = $this->widget->getChecklistItems();
        $settingsItem = $items->firstWhere('label', 'Configure General Settings');
        
        // With default app name
        $this->assertFalse($settingsItem['completed']);
        
        // With custom app name
        Setting::set('app_name', 'My Custom App');
        
        $widget = new OnboardingChecklist();
        $items = $widget->getChecklistItems();
        $settingsItem = $items->firstWhere('label', 'Configure General Settings');
        
        $this->assertTrue($settingsItem['completed']);
    }

    public function test_features_configured_check(): void
    {
        $items = $this->widget->getChecklistItems();
        $featuresItem = $items->firstWhere('label', 'Enable Features');
        
        // Without any feature settings
        $this->assertFalse($featuresItem['completed']);
        
        // With feature settings
        Setting::create([
            'key' => 'feature_git_integration',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'features',
        ]);
        
        $widget = new OnboardingChecklist();
        $items = $widget->getChecklistItems();
        $featuresItem = $items->firstWhere('label', 'Enable Features');
        
        $this->assertTrue($featuresItem['completed']);
    }

    public function test_claude_session_check(): void
    {
        $items = $this->widget->getChecklistItems();
        $chatItem = $items->firstWhere('label', 'Start Your First Claude Chat');
        
        // Without session
        $this->assertFalse($chatItem['completed']);
        
        // With session
        $project = Project::factory()->create();
        ClaudeSession::factory()->create(['project_id' => $project->id]);
        
        $widget = new OnboardingChecklist();
        $items = $widget->getChecklistItems();
        $chatItem = $items->firstWhere('label', 'Start Your First Claude Chat');
        
        $this->assertTrue($chatItem['completed']);
    }

    public function test_progress_calculation(): void
    {
        // No items completed
        $this->assertEquals(0, $this->widget->getProgress());
        $this->assertEquals(0, $this->widget->getCompletedCount());
        $this->assertEquals(5, $this->widget->getTotalCount());
        
        // Some items completed
        Setting::set('claude_api_key', 'sk-ant-test-key-123456789');
        Project::factory()->create();
        
        $widget = new OnboardingChecklist();
        $this->assertEquals(40, $widget->getProgress()); // 2 out of 5 = 40%
        $this->assertEquals(2, $widget->getCompletedCount());
        
        // All items completed
        Setting::set('app_name', 'My App');
        Setting::create([
            'key' => 'feature_git_integration',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'features',
        ]);
        ClaudeSession::factory()->create(['project_id' => Project::first()->id]);
        
        $widget = new OnboardingChecklist();
        $this->assertEquals(100, $widget->getProgress());
        $this->assertEquals(5, $widget->getCompletedCount());
    }

    public function test_chat_button_disabled_without_prerequisites(): void
    {
        $items = $this->widget->getChecklistItems();
        $chatItem = $items->firstWhere('label', 'Start Your First Claude Chat');
        
        // Should be disabled without API key or project
        $this->assertTrue($chatItem['disabled']);
        $this->assertNull($chatItem['action']);
        
        // With API key but no project
        Setting::set('claude_api_key', 'sk-ant-test-key-123456789');
        $widget = new OnboardingChecklist();
        $items = $widget->getChecklistItems();
        $chatItem = $items->firstWhere('label', 'Start Your First Claude Chat');
        
        $this->assertTrue($chatItem['disabled']);
        
        // With both API key and project
        $project = Project::factory()->create();
        $widget = new OnboardingChecklist();
        $items = $widget->getChecklistItems();
        $chatItem = $items->firstWhere('label', 'Start Your First Claude Chat');
        
        $this->assertFalse($chatItem['disabled']);
        $this->assertStringContainsString('/admin/projects/' . $project->id . '/claude', $chatItem['action']);
    }
}