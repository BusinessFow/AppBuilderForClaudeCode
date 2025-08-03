<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'channel',
        'message',
        'context',
        'user_id',
        'project_id',
        'ip_address',
        'user_agent',
        'url',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function log(string $level, string $message, array $context = [], string $channel = 'general'): self
    {
        return self::create([
            'level' => $level,
            'channel' => $channel,
            'message' => $message,
            'context' => $context,
            'user_id' => auth()->id(),
            'project_id' => $context['project_id'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);
    }

    public static function info(string $message, array $context = [], string $channel = 'general'): self
    {
        return self::log('info', $message, $context, $channel);
    }

    public static function warning(string $message, array $context = [], string $channel = 'general'): self
    {
        return self::log('warning', $message, $context, $channel);
    }

    public static function error(string $message, array $context = [], string $channel = 'general'): self
    {
        return self::log('error', $message, $context, $channel);
    }

    public static function debug(string $message, array $context = [], string $channel = 'general'): self
    {
        return self::log('debug', $message, $context, $channel);
    }

    public static function success(string $message, array $context = [], string $channel = 'general'): self
    {
        return self::log('success', $message, $context, $channel);
    }
}
