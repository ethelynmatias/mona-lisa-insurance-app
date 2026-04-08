<?php

namespace App\Repositories;

use App\Models\WebhookDiscoveredField;
use App\Models\WebhookLog;
use App\Repositories\Contracts\WebhookLogRepositoryInterface;

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

    public function saveDiscoveredFields(string $formId, array $keys): void
    {
        if (empty($keys)) {
            return;
        }

        $record = WebhookDiscoveredField::where('form_id', $formId)->first();

        if ($record) {
            // Merge incoming keys with existing ones — never removes, so saved mappings stay valid
            $merged = array_values(array_unique(array_merge($record->fields ?? [], $keys)));
            $record->update(['fields' => $merged]);
        } else {
            WebhookDiscoveredField::create([
                'form_id' => $formId,
                'fields'  => array_values(array_unique($keys)),
            ]);
        }
    }

    public function getDiscoveredFields(string $formId): array
    {
        return WebhookDiscoveredField::where('form_id', $formId)
            ->value('fields') ?? [];
    }
}
