<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\NowCertsEntity;
use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Repositories\Contracts\FormFieldMappingRepositoryInterface;
use App\Repositories\Contracts\WebhookLogRepositoryInterface;
use App\Services\NowCertsFieldMapper;
use App\Services\NowCertsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CognitoWebhookController extends Controller
{
    public function __construct(
        private readonly NowCertsService                     $nowcerts,
        private readonly WebhookLogRepositoryInterface       $webhookLogs,
        private readonly FormFieldMappingRepositoryInterface $mappings,
    ) {}

    /**
     * Receive an incoming Cognito Forms webhook, log it, then push to NowCerts.
     *
     * POST /webhook/cognito?form_id=1&event=entry.submitted
     */
    public function receive(Request $request): JsonResponse
    {
        $payload   = $request->all();
        $formId    = $request->query('form_id') ?? ($payload['FormId']    ?? ($payload['form_id']    ?? 'unknown'));
        $formName  = $request->query('form_name') ?? ($payload['FormName'] ?? ($payload['form_name'] ?? null));
        $eventType = $request->query('event')    ?? ($payload['EventType'] ?? 'entry.submitted');
        $entryId   = $payload['Id'] ?? ($payload['EntryId'] ?? ($payload['entry_id'] ?? null));

        $log = $this->webhookLogs->create([
            'form_id'     => $formId,
            'form_name'   => $formName,
            'event_type'  => $eventType,
            'entry_id'    => $entryId,
            'status'      => 'received',
            'payload'     => $payload ?: null,
            'sync_status' => SyncStatus::Pending,
        ]);

        // Persist discovered field keys independently of log history
        $this->webhookLogs->saveDiscoveredFields(
            $formId,
            array_keys(NowCertsFieldMapper::flattenEntry($payload)),
        );

        if ($eventType === 'entry.deleted') {
            $this->webhookLogs->update($log, ['sync_status' => SyncStatus::Skipped]);
            return response()->json(['ok' => true]);
        }

        $this->syncToNowCerts($log, $formId, $payload);

        return response()->json(['ok' => true]);
    }

    /**
     * Map the Cognito payload to NowCerts entities and call the API.
     * Updates the log record with the outcome.
     */
    private function syncToNowCerts(WebhookLog $log, string $formId, array $entry): void
    {
        $context = ['webhook_log_id' => $log->id, 'form_id' => $formId, 'entry_id' => $log->entry_id];

        try {
            $entry  = NowCertsFieldMapper::flattenEntry($entry);
            $mapper = new NowCertsFieldMapper($formId, $this->nowcerts, $this->mappings);

            Log::info('NowCerts sync started', array_merge($context, [
                'flattened_entry_keys' => array_keys($entry),
            ]));

            $syncedEntities = [];
            $errors         = [];

            // Sync primary entry (all entities)
            foreach ($this->entitySyncMap($mapper) as $entity => $callbacks) {
                $data = $callbacks['map']($entry);

                Log::info("NowCerts mapped {$entity}", array_merge($context, ['data' => $data]));

                if (empty($data)) {
                    Log::warning("NowCerts {$entity} skipped — no mapped fields", $context);
                    continue;
                }

                try {
                    $response = $callbacks['push']($data);
                    Log::info("NowCerts {$entity} pushed", array_merge($context, ['response' => $response]));
                    $syncedEntities[] = $entity;
                } catch (Throwable $e) {
                    Log::error("NowCerts {$entity} failed", array_merge($context, ['error' => $e->getMessage()]));
                    $errors[] = "{$entity}: " . $e->getMessage();
                }
            }


            if (empty($syncedEntities) && empty($errors)) {
                Log::warning('NowCerts sync skipped — no field mappings configured', $context);
                $this->webhookLogs->update($log, ['sync_status' => SyncStatus::Skipped]);
                return;
            }

            Log::info('NowCerts sync finished', array_merge($context, [
                'synced_entities' => $syncedEntities,
                'errors'          => $errors,
            ]));

            $this->webhookLogs->update($log, [
                'sync_status'     => empty($errors) ? SyncStatus::Synced : SyncStatus::Failed,
                'sync_error'      => empty($errors) ? null : implode('; ', $errors),
                'synced_entities' => $syncedEntities ?: null,
                'synced_at'       => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('NowCerts sync exception', array_merge($context, ['error' => $e->getMessage()]));
            $this->webhookLogs->update($log, [
                'sync_status' => SyncStatus::Failed,
                'sync_error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Returns a map of entity name → [map callable, push callable].
     * Add new entities here without touching the sync loop.
     */
    private function entitySyncMap(NowCertsFieldMapper $mapper): array
    {
        return [
            NowCertsEntity::Insured->value => [
                'map'  => fn (array $e) => $mapper->mapInsured($e),
                'push' => fn (array $d) => $this->nowcerts->syncInsured($d),
            ],
            NowCertsEntity::Policy->value => [
                'map'  => fn (array $e) => $mapper->mapPolicy($e),
                'push' => fn (array $d) => $this->nowcerts->upsertPolicy($d),
            ],
            NowCertsEntity::Driver->value => [
                'map'  => fn (array $e) => $mapper->mapDriver($e),
                'push' => fn (array $d) => $this->nowcerts->insertDriver($d),
            ],
            NowCertsEntity::Vehicle->value => [
                'map'  => fn (array $e) => $mapper->mapVehicle($e),
                'push' => fn (array $d) => $this->nowcerts->insertVehicle($d),
            ],
        ];
    }

    /**
     * Rerun the NowCerts sync for a specific webhook log entry.
     */
    public function rerunSync(WebhookLog $log): RedirectResponse
    {
        if ($log->event_type === 'entry.deleted') {
            return back()->with('error', 'Delete events cannot be synced to NowCerts.');
        }

        if (empty($log->payload)) {
            return back()->with('error', 'No payload stored for this event — cannot rerun.');
        }

        $this->webhookLogs->update($log, [
            'sync_status'     => SyncStatus::Pending,
            'sync_error'      => null,
            'synced_entities' => null,
            'synced_at'       => null,
        ]);

        $this->syncToNowCerts($log, $log->form_id, $log->payload);

        $log->refresh();

        return match ($log->sync_status) {
            SyncStatus::Synced  => back()->with('success', 'Sync completed. Entities pushed: ' . implode(', ', $log->synced_entities ?? [])),
            SyncStatus::Skipped => back()->with('success', 'No field mappings configured for this form — sync skipped.'),
            default             => back()->with('error', 'Sync failed: ' . ($log->sync_error ?? 'Unknown error.')),
        };
    }

    /**
     * Clear all webhook history.
     */
    public function clearAll(): RedirectResponse
    {
        $this->webhookLogs->truncateAll();

        return back()->with('success', 'Webhook history cleared.');
    }

    /**
     * Clear webhook history for a specific form.
     */
    public function clearByForm(string $formId): RedirectResponse
    {
        $this->webhookLogs->deleteByForm($formId);

        return back()->with('success', 'Webhook history cleared for this form.');
    }
}
