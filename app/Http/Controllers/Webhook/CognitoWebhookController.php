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

        if (! in_array($eventType, ['entry.submitted', 'entry.updated'], true)) {
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
            // Extract file uploads before flattening — list arrays are lost after flatten
            $fileUploads = NowCertsFieldMapper::extractFileUploads($entry);

            $entry  = NowCertsFieldMapper::flattenEntry($entry);
            $mapper = new NowCertsFieldMapper($formId, $this->nowcerts, $this->mappings);

            Log::info('NowCerts sync started', array_merge($context, [
                'flattened_entry_keys' => array_keys($entry),
                'file_upload_fields'   => array_column($fileUploads, 'field'),
            ]));

            $syncedEntities     = [];
            $errors             = [];
            $insuredDatabaseId  = null;
            $allSyncedData      = [];

            // Sync primary entities first (Insured, Policy, Driver, Vehicle)
            foreach ($this->primaryEntitySyncMap($mapper) as $entity => $callbacks) {
                $data = $callbacks['map']($entry);

                Log::info("NowCerts mapped {$entity}", array_merge($context, ['data' => $data]));

                if (empty($data)) {
                    Log::warning("NowCerts {$entity} skipped — no mapped fields", $context);
                    continue;
                }

                try {
                    $response = $callbacks['push']($data);
                    Log::info("NowCerts {$entity} pushed", array_merge($context, ['response' => $response]));
                    $syncedEntities[]        = $entity;
                    $allSyncedData[$entity]  = $data;

                    // Capture insured database ID for property + document uploads
                    if ($entity === NowCertsEntity::Insured->value && ! $insuredDatabaseId) {
                        $insuredDatabaseId = $response['_insuredDatabaseId'] ?? null;
                    }
                } catch (Throwable $e) {
                    Log::error("NowCerts {$entity} failed", array_merge($context, ['error' => $e->getMessage()]));
                    $errors[] = "{$entity}: " . $e->getMessage();
                }
            }

            // Sync Property — requires insuredDatabaseId resolved above
            $propertyData = $this->buildPropertyData($mapper, $entry, $insuredDatabaseId);
            if (! empty($propertyData)) {
                $entityLabel = NowCertsEntity::Property->value;
                Log::info("NowCerts mapped {$entityLabel}", array_merge($context, ['data' => $propertyData]));
                try {
                    $response = $this->nowcerts->insertOrUpdateProperty($propertyData);
                    Log::info("NowCerts {$entityLabel} pushed", array_merge($context, ['response' => $response]));
                    $syncedEntities[]              = $entityLabel;
                    $allSyncedData[$entityLabel]   = $propertyData;
                } catch (Throwable $e) {
                    Log::error("NowCerts {$entityLabel} failed", array_merge($context, ['error' => $e->getMessage()]));
                    $errors[] = "{$entityLabel}: " . $e->getMessage();
                }
            }

            // Add note to insured with all synced field values
            if ($insuredDatabaseId && ! empty($allSyncedData)) {
                try {
                    /*
                    $action = match ($log->event_type) {
                        'entry.submitted' => 'New form submission synced',
                        'entry.updated'   => 'Form submission updated and re-synced',
                        default           => 'Webhook synced',
                    }; */

                    $noteLines = [
                        //$action,
                        //"Entities: " . implode(', ', $syncedEntities),
                        "Form: " . ($log->form_name ?? $formId),
                        "Entry ID: " . ($log->entry_id ?? 'N/A'),
                        "Synced at: " . now()->format('Y-m-d H:i:s'),
                        //"---",
                    ];

                    $excluded = ['Origin', 'origin', 'Action', 'action', 'Order', 'order', 'Entry', 'entry', 'Event', 'event'];
                    $excludedPrefixes = ['Entry.', 'Form.'];

                    $isExcluded = function (string $key) use ($excluded, $excludedPrefixes): bool {
                        if (in_array($key, $excluded, true)) return true;
                        foreach ($excludedPrefixes as $prefix) {
                            if (str_starts_with($key, $prefix)) return true;
                        }
                        return false;
                    };

                    // Webhook data — all non-null, non-empty scalar values except excluded keys/prefixes
                    $noteLines[] = "[Webhook Data]";
                    foreach ($entry as $key => $value) {
                        if ($isExcluded($key)) continue;
                        if (is_scalar($value) && $value !== '' && $value !== null) {
                            $noteLines[] = "  {$key}: {$value}";
                        }
                    }

                    // Synced entity data
                    foreach ($allSyncedData as $entity => $fields) {
                        $noteLines[] = "[{$entity}]";
                        foreach ($fields as $key => $value) {
                            if ($isExcluded($key)) continue;
                            if (is_scalar($value) && $value !== '' && $value !== null) {
                                $noteLines[] = "  {$key}: {$value}";
                            }
                        }
                    }

                    $this->nowcerts->insertNote([
                        'insured_database_id' => $insuredDatabaseId,
                        'subject'             => implode("\n", $noteLines),
                        'creator_name'        => 'Cognito Webhook',
                    ]);

                    Log::info('NowCerts note added to insured', array_merge($context, ['insuredDatabaseId' => $insuredDatabaseId]));
                } catch (Throwable $e) {
                    Log::warning('NowCerts note failed — non-blocking', array_merge($context, ['error' => $e->getMessage()]));
                }
            }

            // Upload documents — skip files already uploaded for this entry
            if ($insuredDatabaseId && ! empty($fileUploads)) {
                $uploadedIds = $this->webhookLogs->getUploadedFileIds($formId, $log->entry_id ?? '');
                $this->syncFileUploads($insuredDatabaseId, $fileUploads, $context, $log, $uploadedIds);
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
     * Upload Cognito file attachments to NowCerts for the given insured.
     * Skips files whose Cognito ID was already uploaded in a previous sync for this entry.
     */
    private function syncFileUploads(
        string $insuredDatabaseId,
        array $fileUploads,
        array $context,
        WebhookLog $log,
        array $alreadyUploadedIds = [],
    ): void {
        $newlyUploadedIds = [];

        foreach ($fileUploads as $upload) {
            $fieldLabel = $upload['field'];

            foreach ($upload['files'] as $file) {
                $cognitoFileId = $file['Id'] ?? null;

                // Skip if already uploaded in a previous sync for this entry
                if ($cognitoFileId && in_array($cognitoFileId, $alreadyUploadedIds, true)) {
                    Log::info('NowCerts document skipped — already uploaded', array_merge($context, [
                        'field'          => $fieldLabel,
                        'file'           => $file['Name'] ?? $cognitoFileId,
                        'cognitoFileId'  => $cognitoFileId,
                    ]));
                    continue;
                }

                $url         = $file['File'];
                $name        = $file['Name']        ?? basename($url);
                $contentType = $file['ContentType'] ?? 'application/octet-stream';

                try {
                    $this->nowcerts->uploadDocument($insuredDatabaseId, $url, $name, $contentType, $fieldLabel);

                    Log::info('NowCerts document uploaded', array_merge($context, [
                        'field' => $fieldLabel,
                        'file'  => $name,
                    ]));

                    if ($cognitoFileId) {
                        $newlyUploadedIds[] = $cognitoFileId;
                    }
                } catch (Throwable $e) {
                    Log::error('NowCerts document upload failed', array_merge($context, [
                        'field' => $fieldLabel,
                        'file'  => $name,
                        'error' => $e->getMessage(),
                    ]));
                }
            }
        }

        // Persist newly uploaded file IDs so future syncs can skip them
        if (! empty($newlyUploadedIds)) {
            $this->webhookLogs->update($log, [
                'uploaded_file_ids' => array_values(array_unique(
                    array_merge($alreadyUploadedIds, $newlyUploadedIds)
                )),
            ]);
        }
    }

    /**
     * Returns a map of primary entity name → [map callable, push callable].
     * Property is handled separately after insuredDatabaseId is resolved.
     */
    private function primaryEntitySyncMap(NowCertsFieldMapper $mapper): array
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
            // NowCertsEntity::Driver->value => [
            //     'map'  => fn (array $e) => $this->filterDriverData($mapper->mapDriver($e)),
            //     'push' => fn (array $d) => $this->nowcerts->insertDriver($d),
            // ],
            // NowCertsEntity::Vehicle->value => [
            //     'map'  => fn (array $e) => $this->filterVehicleData($mapper->mapVehicle($e)),
            //     'push' => fn (array $d) => $this->nowcerts->insertVehicle($d),
            // ],
        ];
    }

    /**
     * Map property fields, inject InsuredDatabaseId, and (on update) inject
     * the existing property's DatabaseId so NowCerts updates rather than inserts.
     */
    private function buildPropertyData(NowCertsFieldMapper $mapper, array $entry, ?string $insuredDatabaseId): array
    {
        $data = $mapper->mapProperty($entry);

        if (empty($data) || ! $insuredDatabaseId) {
            return $data;
        }

        $data['InsuredDatabaseId'] = $insuredDatabaseId;

        // Look up an existing property for this insured so we update instead of insert
        if (empty($data['DatabaseId'])) {
            try {
                $existing = $this->nowcerts->findProperties(['InsuredId' => $insuredDatabaseId]);
                $first    = is_array($existing) ? ($existing[0] ?? null) : null;

                if (! empty($first['id'])) {
                    $data['DatabaseId'] = $first['id'];
                }
            } catch (Throwable) {
                // No existing property found — will insert a new one
            }
        }

        return $data;
    }

    /**
     * Only proceed with driver sync if at least one driver-specific field is present.
     * Prevents accidental inserts when only shared fields (e.g. FirstName) are mapped.
     */
    private function filterDriverData(array $data): array
    {
        $driverFields = ['LicenseNumber', 'LicenseState', 'DateOfBirth', 'Gender', 'MaritalStatus'];

        foreach ($driverFields as $field) {
            if (! empty($data[$field])) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Only proceed with vehicle sync if at least one vehicle-specific field is present.
     */
    private function filterVehicleData(array $data): array
    {
        $vehicleFields = ['VIN', 'Year', 'Make', 'Model'];

        foreach ($vehicleFields as $field) {
            if (! empty($data[$field])) {
                return $data;
            }
        }

        return [];
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
