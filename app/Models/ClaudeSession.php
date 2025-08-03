<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaudeSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'process_id',
        'status',
        'last_input',
        'last_output',
        'conversation_history',
        'started_at',
        'last_activity',
    ];

    protected $casts = [
        'conversation_history' => 'array',
        'started_at' => 'datetime',
        'last_activity' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function todos(): HasMany
    {
        return $this->hasMany(ClaudeTodo::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function addToHistory(string $role, string $content): void
    {
        $history = $this->conversation_history ?? [];
        $history[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toIso8601String(),
        ];
        
        // Keep only last 100 messages
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }
        
        $this->conversation_history = $history;
        $this->save();
    }
}