<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'form_id',
        'form_name',
        'event_type',
        'entry_id',
        'status',
        'payload',
        'sync_status',
        'sync_error',
        'synced_entities',
        'synced_at',
        'uploaded_file_ids',
    ];

    protected function casts(): array
    {
        return [
            'payload'           => 'array',
            'synced_entities'   => 'array',
            'uploaded_file_ids' => 'array',
            'synced_at'         => 'datetime',
            'sync_status'       => SyncStatus::class,
        ];
    }
}
