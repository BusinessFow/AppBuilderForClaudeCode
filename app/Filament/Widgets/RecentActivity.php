<?php

namespace App\Filament\Widgets;

use App\Models\Activity;
use Filament\Widgets\Widget;

class RecentActivity extends Widget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = [
        'lg' => 2,
        'xl' => 3,
    ];
    
    protected string $view = 'filament.widgets.recent-activity';
    
    public function getActivities()
    {
        return Activity::getRecentActivities();
    }
}