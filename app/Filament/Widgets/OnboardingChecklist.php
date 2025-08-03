<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Setting;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class OnboardingChecklist extends Widget
{
    protected static ?int $sort = -3;
    
    protected int | string | array $columnSpan = 'full';
    
    protected string $view = 'filament.widgets.onboarding-checklist';
    
    public function getChecklistItems(): Collection
    {
        return collect([
            [
                'label' => 'Configure Claude API Key',
                'description' => 'Add your Anthropic API key to start using Claude',
                'completed' => $this->hasClaudeApiKey(),
                'action' => '/admin/settings/manage',
                'action_label' => 'Add API Key',
                'icon' => 'heroicon-o-key',
            ],
            [
                'label' => 'Create Your First Project',
                'description' => 'Set up your first project to start building with Claude',
                'completed' => $this->hasProject(),
                'action' => '/admin/projects/create',
                'action_label' => 'Create Project',
                'icon' => 'heroicon-o-folder-plus',
            ],
            [
                'label' => 'Configure General Settings',
                'description' => 'Set up application name and other preferences',
                'completed' => $this->hasGeneralSettings(),
                'action' => '/admin/settings/manage',
                'action_label' => 'Configure Settings',
                'icon' => 'heroicon-o-cog-6-tooth',
            ],
            [
                'label' => 'Enable Features',
                'description' => 'Choose which features you want to enable',
                'completed' => $this->hasFeaturesConfigured(),
                'action' => '/admin/settings/manage',
                'action_label' => 'Manage Features',
                'icon' => 'heroicon-o-puzzle-piece',
            ],
            [
                'label' => 'Start Your First Claude Chat',
                'description' => 'Open Claude chat in your project and start building',
                'completed' => $this->hasClaudeSession(),
                'action' => $this->hasProject() ? '/admin/projects/' . Project::first()?->id . '/claude' : null,
                'action_label' => 'Start Chat',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'disabled' => !$this->hasClaudeApiKey() || !$this->hasProject(),
            ],
        ]);
    }
    
    public function getCompletedCount(): int
    {
        return $this->getChecklistItems()->where('completed', true)->count();
    }
    
    public function getTotalCount(): int
    {
        return $this->getChecklistItems()->count();
    }
    
    public function getProgress(): int
    {
        $total = $this->getTotalCount();
        if ($total === 0) {
            return 0;
        }
        
        return (int) (($this->getCompletedCount() / $total) * 100);
    }
    
    protected function hasClaudeApiKey(): bool
    {
        $apiKey = Setting::get('claude_api_key', '');
        return !empty($apiKey) && strlen($apiKey) > 10;
    }
    
    protected function hasProject(): bool
    {
        return Project::exists();
    }
    
    protected function hasClaudeSession(): bool
    {
        return \App\Models\ClaudeSession::exists();
    }
    
    protected function hasGeneralSettings(): bool
    {
        $appName = Setting::get('app_name', '');
        return !empty($appName) && $appName !== 'AppBuilder for Claude Code';
    }
    
    protected function hasFeaturesConfigured(): bool
    {
        // Check if at least one feature setting exists and has been configured
        $features = [
            'feature_git_integration',
            'feature_web_scraping',
            'feature_ai_analysis',
            'feature_project_templates',
            'feature_code_generation',
            'feature_collaboration',
        ];
        
        foreach ($features as $feature) {
            if (Setting::where('key', $feature)->exists()) {
                return true;
            }
        }
        
        return false;
    }
}