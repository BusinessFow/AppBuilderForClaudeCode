<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaudeTodo extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'claude_session_id',
        'command',
        'description',
        'status',
        'priority',
        'sort_order',
        'completed_by_claude',
        'result',
        'started_at',
        'completed_at',
        'executed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'executed_at' => 'datetime',
        'completed_by_claude' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClaudeSession::class, 'claude_session_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('priority', 'desc')->orderBy('created_at', 'asc');
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $result = null): void
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error = null): void
    {
        $this->update([
            'status' => 'failed',
            'result' => $error,
            'completed_at' => now(),
        ]);
    }
}