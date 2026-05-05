<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AppLog extends Model
{
    protected $fillable = [
        'level',
        'channel',
        'message',
        'context',
        'logged_at',
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'request_id',
        'form_id',
        'webhook_log_id',
    ];

    protected $casts = [
        'context'    => 'array',
        'logged_at'  => 'datetime:Y-m-d H:i:s',
    ];

    public function scopeLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    public function scopeChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    public function scopeFormId(Builder $query, string $formId): Builder
    {
        return $query->where('form_id', $formId);
    }

    public function scopeWebhookLogId(Builder $query, string $webhookLogId): Builder
    {
        return $query->where('webhook_log_id', $webhookLogId);
    }

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('logged_at', '>=', now()->subHours($hours));
    }
}