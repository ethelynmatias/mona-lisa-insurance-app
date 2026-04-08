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
}
