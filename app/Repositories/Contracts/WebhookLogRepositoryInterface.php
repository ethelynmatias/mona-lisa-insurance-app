<?php

namespace App\Repositories\Contracts;

use App\Enums\SyncStatus;
use App\Models\WebhookLog;

interface WebhookLogRepositoryInterface
{
    public function create(array $data): WebhookLog;

    public function update(WebhookLog $log, array $data): void;

    public function latestForDashboard(int $limit = 500): array;

    public function latestForForm(string $formId, int $limit = 500): array;

    public function truncateAll(): void;

    public function deleteByForm(string $formId): void;

    /**
     * Persist flattened field keys discovered from a webhook payload.
     * Stored independently of log history so clearing logs does not lose field keys.
     */
    public function saveDiscoveredFields(string $formId, array $keys): void;

    /**
     * Return persisted discovered field keys for a form.
     */
    public function getDiscoveredFields(string $formId): array;

    /**
     * Return all Cognito file IDs that have already been uploaded for a given entry.
     */
    public function getUploadedFileIds(string $formId, string $entryId): array;

    /**
     * Return the synced_nowcerts_ids from the most recent successful sync
     * for the same form + entry, excluding the current log record.
     */
    public function getPreviousSyncedIds(string $formId, string $entryId, int $excludeLogId): array;
}
