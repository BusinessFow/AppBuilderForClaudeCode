<?php

namespace App\Filament\Widgets;

use App\Models\ClaudeSession;
use App\Models\ClaudeTodo;
use App\Models\Project;
use App\Models\Setting;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -2;
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getStats(): array
    {
        $projectsCount = Project::count();
        $activeProjectsCount = Project::where('status', 'active')->count();
        $completedTasksCount = ClaudeTodo::where('status', 'completed')->count();
        $totalTasksCount = ClaudeTodo::count();
        $activeSessionsCount = ClaudeSession::where('status', 'running')->count();
        
        $hasApiKey = !empty(Setting::get('claude_api_key', ''));
        
        return [
            Stat::make('Total Projects', $projectsCount)
                ->description($activeProjectsCount . ' active')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($projectsCount > 0 ? 'success' : 'gray')
                ->chart($this->getProjectsChart()),
                
            Stat::make('Tasks Completed', $completedTasksCount . '/' . $totalTasksCount)
                ->description($totalTasksCount > 0 ? round(($completedTasksCount / $totalTasksCount) * 100) . '% completion rate' : 'No tasks yet')
                ->descriptionIcon($totalTasksCount > 0 ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($completedTasksCount > 0 ? 'success' : 'warning'),
                
            Stat::make('Active Claude Sessions', $activeSessionsCount)
                ->description($hasApiKey ? 'API key configured' : 'API key missing')
                ->descriptionIcon($hasApiKey ? 'heroicon-m-check-badge' : 'heroicon-m-exclamation-triangle')
                ->color($hasApiKey ? 'primary' : 'danger'),
                
            Stat::make('System Status', $hasApiKey ? 'Ready' : 'Setup Required')
                ->description($hasApiKey ? 'All systems operational' : 'Complete setup to start')
                ->descriptionIcon($hasApiKey ? 'heroicon-m-rocket-launch' : 'heroicon-m-cog-6-tooth')
                ->color($hasApiKey ? 'success' : 'warning')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'wire:click' => "\$dispatch('open-modal', { id: 'system-status' })",
                ]),
        ];
    }
    
    protected function getProjectsChart(): array
    {
        // Simple chart data for the last 7 days
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Project::whereDate('created_at', $date)->count();
            $data[] = $count;
        }
        
        return $data;
    }
}
