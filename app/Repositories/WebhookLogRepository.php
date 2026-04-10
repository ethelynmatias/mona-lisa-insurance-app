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

        $record   = WebhookDiscoveredField::where('form_id', $formId)->first();
        $existing = $record?->fields ?? [];
        $newKeys  = array_values(array_diff($keys, $existing));

        if (empty($newKeys) && $record) {
            return; // Nothing new — skip the write
        }

        $merged = array_values(array_unique(array_merge($existing, $keys)));

        if ($record) {
            $record->update(['fields' => $merged]);
        } else {
            WebhookDiscoveredField::create([
                'form_id' => $formId,
                'fields'  => $merged,
            ]);
        }
    }

    public function getDiscoveredFields(string $formId): array
    {
        return WebhookDiscoveredField::where('form_id', $formId)
            ->value('fields') ?? [];
    }

    public function getUploadedFileIds(string $formId, string $entryId): array
    {
        return WebhookLog::where('form_id', $formId)
            ->where('entry_id', $entryId)
            ->whereNotNull('uploaded_file_ids')
            ->get()
            ->flatMap(fn ($log) => $log->uploaded_file_ids ?? [])
            ->unique()
            ->values()
            ->all();
    }

    public function getPreviousSyncedIds(string $formId, string $entryId, int $excludeLogId): array
    {
        $log = WebhookLog::where('form_id', $formId)
            ->where('entry_id', $entryId)
            ->where('id', '!=', $excludeLogId)
            ->whereNotNull('synced_nowcerts_ids')
            ->orderByDesc('created_at')
            ->first(['synced_nowcerts_ids']);

        return $log?->synced_nowcerts_ids ?? [];
    }
}
