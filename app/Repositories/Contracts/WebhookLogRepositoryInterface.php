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
     * Return flattened field keys discovered from stored webhook payloads for a form.
     * Used to populate the mapping UI with real field names.
     */
    public function getDiscoveredFields(string $formId): array;
}
