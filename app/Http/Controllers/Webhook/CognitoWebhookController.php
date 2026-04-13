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
     *
     * @param bool $isRerun  When true: inject stored NowCerts IDs (no lookup) and skip note.
     */
    private function syncToNowCerts(WebhookLog $log, string $formId, array $entry, bool $isRerun = false): void
    {
        $context = ['webhook_log_id' => $log->id, 'form_id' => $formId, 'entry_id' => $log->entry_id];

        // Stored IDs from a previous successful sync — used on rerun to avoid duplicate inserts.
        // For entry.updated events (new log), inherit IDs from the most recent prior sync of
        // the same entry so contacts and records are updated rather than duplicated.
        if ($isRerun) {
            $storedIds = $log->synced_nowcerts_ids ?? [];
        } elseif ($log->event_type === 'entry.updated' && $log->entry_id) {
            $storedIds = $this->webhookLogs->getPreviousSyncedIds($formId, $log->entry_id, $log->id);
        } else {
            $storedIds = [];
        }

        try {
            // Extract file uploads before flattening — list arrays are lost after flatten
            $fileUploads = NowCertsFieldMapper::extractFileUploads($entry);

            $entry  = NowCertsFieldMapper::flattenEntry($entry);
            $mapper = new NowCertsFieldMapper($formId, $this->nowcerts, $this->mappings);

            Log::info('NowCerts sync started', array_merge($context, [
                'flattened_entry_keys' => array_keys($entry),
                'file_upload_fields'   => array_column($fileUploads, 'field'),
                'is_rerun'             => $isRerun,
                'stored_ids'           => $storedIds,
            ]));

            $syncedEntities     = [];
            $errors             = [];
            $insuredDatabaseId  = null;
            $allSyncedData      = [];

            // Sync primary entities first (Insured, Policy, Driver, Vehicle)
            foreach ($this->primaryEntitySyncMap($mapper) as $entity => $callbacks) {
                $data = $callbacks['map']($entry);

                // On rerun: inject stored NowCerts IDs so API updates instead of inserting
                if ($isRerun) {
                    if ($entity === NowCertsEntity::Insured->value && ! empty($storedIds['insuredDatabaseId'])) {
                        $data['DatabaseId'] = $storedIds['insuredDatabaseId'];
                    }
                    if ($entity === NowCertsEntity::Policy->value && ! empty($storedIds['policyDatabaseId'])) {
                        $data['policyDatabaseId'] = $storedIds['policyDatabaseId'];
                    }
                }

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
                    // On initial sync: capture policy ID for future reruns
                    if ($entity === NowCertsEntity::Policy->value && ! $isRerun) {
                        $storedIds['policyDatabaseId'] = $response['policyDatabaseId']
                            ?? $response['DatabaseId']
                            ?? $response['databaseId']
                            ?? null;
                    }
                } catch (Throwable $e) {
                    Log::error("NowCerts {$entity} failed", array_merge($context, ['error' => $e->getMessage()]));
                    $errors[] = "{$entity}: " . $e->getMessage();
                }
            }

            // Resolve insuredDatabaseId from stored ID if sync didn't return it (rerun scenario)
            if (! $insuredDatabaseId && ! empty($storedIds['insuredDatabaseId'])) {
                $insuredDatabaseId = $storedIds['insuredDatabaseId'];
            }

            // Add or update occupant contact on the primary insured if present
            if ($insuredDatabaseId) {
                $this->syncOccupantContact($insuredDatabaseId, $entry, $context, $isRerun, $storedIds);
            }

            // Sync Property — requires insuredDatabaseId resolved above
            $propertyData = $this->buildPropertyData($mapper, $entry, $insuredDatabaseId, $isRerun ? ($storedIds['propertyDatabaseId'] ?? null) : null);
            if (! empty($propertyData)) {
                $entityLabel = NowCertsEntity::Property->value;
                Log::info("NowCerts mapped {$entityLabel}", array_merge($context, ['data' => $propertyData]));
                try {
                    $response = $this->nowcerts->insertOrUpdateProperty($propertyData);
                    Log::info("NowCerts {$entityLabel} pushed", array_merge($context, ['response' => $response]));
                    $syncedEntities[]              = $entityLabel;
                    $allSyncedData[$entityLabel]   = $propertyData;
                    // Capture property ID for future reruns
                    if (! $isRerun) {
                        $storedIds['propertyDatabaseId'] = $response['DatabaseId']
                            ?? $response['databaseId']
                            ?? $response['id']
                            ?? null;
                    }
                } catch (Throwable $e) {
                    Log::error("NowCerts {$entityLabel} failed", array_merge($context, ['error' => $e->getMessage()]));
                    $errors[] = "{$entityLabel}: " . $e->getMessage();
                }
            }

            // Add note to insured — skipped on rerun to prevent duplicate notes
            if (! $isRerun && $insuredDatabaseId && ! empty($allSyncedData)) {
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

            // Store resolved NowCerts IDs on first successful sync for use in reruns
            if (! $isRerun && $insuredDatabaseId) {
                $storedIds['insuredDatabaseId'] = $insuredDatabaseId;
            }

            $updateData = [
                'sync_status'     => empty($errors) ? SyncStatus::Synced : SyncStatus::Failed,
                'sync_error'      => empty($errors) ? null : implode('; ', $errors),
                'synced_entities' => $syncedEntities ?: null,
                'synced_at'       => now(),
            ];

            // Persist NowCerts IDs after first sync; preserve on rerun
            if (! empty($storedIds)) {
                $updateData['synced_nowcerts_ids'] = $storedIds;
            }

            $this->webhookLogs->update($log, $updateData);
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
     * If the webhook entry contains occupant name fields, insert or update
     * the occupant as a contact on the primary insured record in NowCerts.
     *
     * - First sync: POST /clients/{id}/contacts — stores returned contact ID in $storedIds
     * - Rerun: PUT /clients/{id}/contacts/{contactId} using the stored ID
     *
     * Looks for flattened keys: NameOfOccupant.First / NameOfOccupant.Last
     * Also captures DateOfBirthOccupant if present.
     */
    private function syncOccupantContact(string $insuredDatabaseId, array $entry, array $context, bool $isRerun = false, array &$storedIds = []): void
    {
        $firstName = $entry['NameOfOccupant.First'] ?? null;
        $lastName  = $entry['NameOfOccupant.Last']  ?? null;

        if (empty($firstName) && empty($lastName)) {
            return;
        }

        $contactData = array_filter([
            'first_name'         => $firstName,
            'last_name'          => $lastName,
            'birthday'           => $this->formatDate($entry['DateOfBirthOccupant'] ?? null),
            'policy_database_id' => $storedIds['policyDatabaseId'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $storedContactId = $storedIds['contactId'] ?? null;

            if ($isRerun && $storedContactId) {
                // Update existing contact
                $this->nowcerts->updateContact($insuredDatabaseId, $storedContactId, $contactData);
                Log::info('NowCerts occupant contact updated', array_merge($context, [
                    'insuredDatabaseId' => $insuredDatabaseId,
                    'contactId'         => $storedContactId,
                    'occupant'          => trim("{$firstName} {$lastName}"),
                ]));
            } else {
                // Insert new contact and capture returned ID
                $response = $this->nowcerts->insertContact($insuredDatabaseId, $contactData);
                $contactId = $response['data']['database_id']
                    ?? $response['DatabaseId']
                    ?? $response['database_id']
                    ?? $response['id']
                    ?? $response['Id']
                    ?? null;
                if ($contactId) {
                    $storedIds['contactId'] = $contactId;
                }
                Log::info('NowCerts occupant contact added', array_merge($context, [
                    'insuredDatabaseId' => $insuredDatabaseId,
                    'contactId'         => $contactId,
                    'occupant'          => trim("{$firstName} {$lastName}"),
                ]));
            }
        } catch (Throwable $e) {
            Log::warning('NowCerts occupant contact failed — non-blocking', array_merge($context, [
                'error' => $e->getMessage(),
            ]));
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
    private function buildPropertyData(NowCertsFieldMapper $mapper, array $entry, ?string $insuredDatabaseId, ?string $storedPropertyId = null): array
    {
        $data = $mapper->mapProperty($entry);

        if (empty($data) || ! $insuredDatabaseId) {
            return $data;
        }

        $data['InsuredDatabaseId'] = $insuredDatabaseId;

        // On rerun: use stored property ID directly — no API lookup needed
        if ($storedPropertyId) {
            $data['DatabaseId'] = $storedPropertyId;
            return $data;
        }

        // On first sync: look up an existing property for this insured so we update instead of insert
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
     * Normalize any recognizable date string to MM/DD/YYYY format expected by NowCerts.
     *
     * Tries explicit formats in priority order before falling back to generic parsing:
     *   - m/d/Y   → MM/DD/YYYY  (already correct, e.g. 04/28/1993 or 4/8/1993)
     *   - Y-m-d   → ISO 8601    (e.g. 1993-04-28)
     *   - m-d-Y   → MM-DD-YYYY  (e.g. 04-28-1993)
     *
     * Returns null when $value is null, empty, or unparseable.
     */
    private function formatDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $formats = ['m/d/Y', 'n/j/Y', 'Y-m-d', 'm-d-Y', 'n-j-Y'];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('m/d/Y');
            }
        }

        // Last resort — let PHP try to parse it
        try {
            return (new \DateTime($value))->format('m/d/Y');
        } catch (\Throwable) {
            return null;
        }
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

        $this->syncToNowCerts($log, $log->form_id, $log->payload, isRerun: true);

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
