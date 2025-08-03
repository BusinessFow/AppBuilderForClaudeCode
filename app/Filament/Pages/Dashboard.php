<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OnboardingChecklist;
use App\Filament\Widgets\QuickActions;
use App\Filament\Widgets\RecentActivity;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\SystemHealth;
use App\Filament\Widgets\SystemLogs;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home';
    
    protected static ?string $title = 'Dashboard';
    
    protected static ?string $navigationLabel = 'Dashboard';
    
    protected ?string $heading = 'Welcome to AppBuilder';
    
    protected ?string $subheading = 'Your AI-powered development assistant';
    
    public function getColumns(): int | array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 4,
        ];
    }
    
    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            QuickActions::class,
            OnboardingChecklist::class,
            SystemHealth::class,
            RecentActivity::class,
            SystemLogs::class,
        ];
    }
}