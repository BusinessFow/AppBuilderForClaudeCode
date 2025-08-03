<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Activity extends Model
{
    protected $table = 'activities';
    
    public $timestamps = false;
    
    protected $fillable = [
        'type',
        'description',
        'project_name',
        'created_at',
        'id',
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
    ];
    
    /**
     * Get recent activities from multiple tables
     */
    public static function getRecentActivities()
    {
        $sevenDaysAgo = now()->subDays(7)->toDateTimeString();
        
        // Use raw query to get activities
        $activities = DB::select("
            (SELECT 
                'project_created' as type,
                CONCAT('Created project: ', name) as description,
                name as project_name,
                created_at,
                id
            FROM projects 
            WHERE created_at >= ?)
            
            UNION ALL
            
            (SELECT 
                'task_created' as type,
                CONCAT('Added task: ', command) as description,
                (SELECT name FROM projects WHERE projects.id = claude_todos.project_id) as project_name,
                created_at,
                id
            FROM claude_todos 
            WHERE created_at >= ?)
            
            UNION ALL
            
            (SELECT 
                'task_completed' as type,
                CONCAT('Completed: ', command) as description,
                (SELECT name FROM projects WHERE projects.id = claude_todos.project_id) as project_name,
                completed_at as created_at,
                id
            FROM claude_todos 
            WHERE completed_at IS NOT NULL 
            AND completed_at >= ?)
            
            UNION ALL
            
            (SELECT 
                CASE WHEN status = 'running' THEN 'session_started' ELSE 'session_stopped' END as type,
                CASE WHEN status = 'running' THEN 'Claude session started' ELSE 'Claude session stopped' END as description,
                (SELECT name FROM projects WHERE projects.id = claude_sessions.project_id) as project_name,
                created_at,
                id
            FROM claude_sessions 
            WHERE created_at >= ?)
            
            ORDER BY created_at DESC
        ", [$sevenDaysAgo, $sevenDaysAgo, $sevenDaysAgo, $sevenDaysAgo]);
        
        return collect($activities);
    }
}