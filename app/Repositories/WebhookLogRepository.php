<?php

namespace App\Repositories;

use App\Models\WebhookLog;
use App\Repositories\Contracts\WebhookLogRepositoryInterface;
use App\Services\NowCertsFieldMapper;

class WebhookLogRepository implements WebhookLogRepositoryInterface
{
    private const COLUMNS = [
        'id', 'form_id', 'form_name', 'event_type', 'entry_id',
        'status', 'payload', 'sync_status', 'sync_error',
        'synced_entities', 'synced_at', 'created_at',
    ];

    public function create(array $data): WebhookLog
    {
        return WebhookLog::create($data);
    }

    public function update(WebhookLog $log, array $data): void
    {
        $log->update($data);
    }

    public function latestForDashboard(int $limit = 500): array
    {
        return WebhookLog::orderByDesc('created_at')
            ->limit($limit)
            ->get(self::COLUMNS)
            ->toArray();
    }

    public function latestForForm(string $formId, int $limit = 500): array
    {
        return WebhookLog::where('form_id', $formId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(self::COLUMNS)
            ->toArray();
    }

    public function truncateAll(): void
    {
        WebhookLog::truncate();
    }

    public function deleteByForm(string $formId): void
    {
        WebhookLog::where('form_id', $formId)->delete();
    }

    public function getDiscoveredFields(string $formId): array
    {
        $keys = [];

        WebhookLog::where('form_id', $formId)
            ->whereNotNull('payload')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['payload'])
            ->each(function ($log) use (&$keys) {
                $flattened = NowCertsFieldMapper::flattenEntry($log->payload ?? []);
                foreach (array_keys($flattened) as $key) {
                    $keys[$key] = true;
                }
            });

        return array_keys($keys);
    }
}
