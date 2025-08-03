<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Setting;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class QuickActions extends Widget
{
    protected static ?int $sort = 0;
    
    protected int | string | array $columnSpan = [
        'md' => 2,
        'lg' => 2,
        'xl' => 2,
    ];
    
    protected string $view = 'filament.widgets.quick-actions';
    
    public function getActions(): Collection
    {
        $hasApiKey = !empty(Setting::get('claude_api_key', ''));
        $projects = Project::where('status', 'active')->orderBy('updated_at', 'desc')->limit(3)->get();
        
        $actions = collect([
            [
                'label' => 'New Project',
                'description' => 'Create a new project',
                'icon' => 'heroicon-o-folder-plus',
                'url' => '/admin/projects/create',
                'color' => 'primary',
                'disabled' => false,
            ],
        ]);
        
        if (!$hasApiKey) {
            $actions->push([
                'label' => 'Add API Key',
                'description' => 'Configure Claude API',
                'icon' => 'heroicon-o-key',
                'url' => '/admin/settings/manage',
                'color' => 'danger',
                'disabled' => false,
            ]);
        }
        
        // Add quick access to recent projects
        foreach ($projects as $project) {
            $actions->push([
                'label' => 'Open ' . $project->name,
                'description' => 'Continue working on this project',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'url' => '/admin/projects/' . $project->id . '/claude',
                'color' => 'success',
                'disabled' => !$hasApiKey,
            ]);
        }
        
        $actions->push([
            'label' => 'View All Projects',
            'description' => 'Browse all your projects',
            'icon' => 'heroicon-o-rectangle-stack',
            'url' => '/admin/projects',
            'color' => 'gray',
            'disabled' => false,
        ]);
        
        $actions->push([
            'label' => 'Settings',
            'description' => 'Configure application',
            'icon' => 'heroicon-o-cog-6-tooth',
            'url' => '/admin/settings/manage',
            'color' => 'gray',
            'disabled' => false,
        ]);
        
        return $actions;
    }
}