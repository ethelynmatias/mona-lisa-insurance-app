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
    /** Keys / prefixes excluded from sync notes */
    private const NOTE_EXCLUDED_KEYS     = ['Origin', 'origin', 'Action', 'action', 'Order', 'order', 'Entry', 'entry', 'Event', 'event'];
    private const NOTE_EXCLUDED_PREFIXES = ['Entry.', 'Form.'];

    public function __construct(
        private readonly NowCertsService                     $nowcerts,
        private readonly WebhookLogRepositoryInterface       $webhookLogs,
        private readonly FormFieldMappingRepositoryInterface $mappings,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // Public endpoints
    // ──────────────────────────────────────────────────────────────────────────

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

        // Persist discovered scalar field keys and upload field names independently of log history
        $this->webhookLogs->saveDiscoveredFields(
            $formId,
            array_keys(NowCertsFieldMapper::flattenEntry($payload)),
        );

        $uploadFieldNames = array_map(
            fn (array $u) => $u['field'] . '__upload',
            NowCertsFieldMapper::extractFileUploads($payload),
        );
        if (! empty($uploadFieldNames)) {
            $this->webhookLogs->saveDiscoveredFields($formId, $uploadFieldNames);
        }

        if (! in_array($eventType, ['entry.submitted', 'entry.updated'], true)) {
            $this->webhookLogs->update($log, ['sync_status' => SyncStatus::Skipped]);
            return response()->json(['ok' => true]);
        }

        $this->syncToNowCerts($log, $formId, $payload);

        return response()->json(['ok' => true]);
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

    // ──────────────────────────────────────────────────────────────────────────
    // Core sync orchestration
    // ──────────────────────────────────────────────────────────────────────────

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
            // Extract file uploads before flattening — list arrays are lost after flatten.
            // If the user has configured specific upload fields, restrict to those;
            // otherwise fall back to auto-detecting all file-upload list arrays.
            $configuredUploadFields = $this->mappings->getUploadFieldsForForm($formId);
            $fileUploads = NowCertsFieldMapper::extractFileUploads($entry);
            if (! empty($configuredUploadFields)) {
                $fileUploads = array_values(array_filter(
                    $fileUploads,
                    fn (array $u) => in_array($u['field'], $configuredUploadFields, true),
                ));
            }

            $entry  = NowCertsFieldMapper::flattenEntry($entry);
            $mapper = new NowCertsFieldMapper($formId, $this->nowcerts, $this->mappings);

            Log::info('NowCerts sync started', array_merge($context, [
                'flattened_entry_keys' => array_keys($entry),
                'file_upload_fields'   => array_column($fileUploads, 'field'),
                'is_rerun'             => $isRerun,
                'stored_ids'           => $storedIds,
            ]));

            $syncedEntities    = [];
            $errors            = [];
            $insuredDatabaseId = null;
            $allSyncedData     = [];

            // ── Step 1: Sync primary mapped entities (Insured, Policy) ────────
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
                    $syncedEntities[]       = $entity;
                    $allSyncedData[$entity] = $data;

                    if ($entity === NowCertsEntity::Insured->value && ! $insuredDatabaseId) {
                        $insuredDatabaseId = $response['_insuredDatabaseId'] ?? null;
                    }
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

            // ── Step 2: Occupant contact ──────────────────────────────────────
            if ($insuredDatabaseId) {
                $this->syncOccupantContact($insuredDatabaseId, $entry, $context, $isRerun, $storedIds);
            }

            // ── Step 3: Drivers & Vehicles (dynamic payload fields) ───────────
            // Prefer policyDatabaseId; fall back to insuredDatabaseId.
            // Both Zapier/InsertDriver and Zapier/InsertVehicle accept either identifier.
            $policyDatabaseId = $storedIds['policyDatabaseId'] ?? null;
            if ($policyDatabaseId || $insuredDatabaseId) {
                $this->syncDrivers($policyDatabaseId, $insuredDatabaseId, $entry, $context);
                $this->syncVehicles($policyDatabaseId, $insuredDatabaseId, $entry, $context);
            }

            // ── Step 4: Property ─────────────────────────────────────────────
            $propertyData = $this->buildPropertyData(
                $mapper,
                $entry,
                $insuredDatabaseId,
                $isRerun ? ($storedIds['propertyDatabaseId'] ?? null) : null,
            );
            if (! empty($propertyData)) {
                $entityLabel = NowCertsEntity::Property->value;
                Log::info("NowCerts mapped {$entityLabel}", array_merge($context, ['data' => $propertyData]));
                try {
                    $response = $this->nowcerts->zapierInsertProperty($propertyData);
                    Log::info("NowCerts {$entityLabel} pushed", array_merge($context, ['response' => $response]));
                    $syncedEntities[]             = $entityLabel;
                    $allSyncedData[$entityLabel]  = $propertyData;
                    if (! $isRerun) {
                        $storedIds['propertyDatabaseId'] = $response['databaseId']
                            ?? $response['database_id']
                            ?? $response['DatabaseId']
                            ?? $response['id']
                            ?? null;
                    }
                } catch (Throwable $e) {
                    Log::error("NowCerts {$entityLabel} failed", array_merge($context, ['error' => $e->getMessage()]));
                    $errors[] = "{$entityLabel}: " . $e->getMessage();
                }
            }

            // ── Step 5: Note (first sync only) ────────────────────────────────
            if (! $isRerun && $insuredDatabaseId && ! empty($allSyncedData)) {
                $this->insertSyncNote($insuredDatabaseId, $log, $formId, $entry, $allSyncedData, $context);
            }

            // ── Step 6: File uploads ──────────────────────────────────────────
            if ($insuredDatabaseId && ! empty($fileUploads)) {
                $uploadedIds = $this->webhookLogs->getUploadedFileIds($formId, $log->entry_id ?? '');
                $this->syncFileUploads($insuredDatabaseId, $fileUploads, $context, $log, $uploadedIds);
            }

            // ── Finalise ──────────────────────────────────────────────────────
            if (empty($syncedEntities) && empty($errors)) {
                Log::warning('NowCerts sync skipped — no field mappings configured', $context);
                $this->webhookLogs->update($log, ['sync_status' => SyncStatus::Skipped]);
                return;
            }

            Log::info('NowCerts sync finished', array_merge($context, [
                'synced_entities' => $syncedEntities,
                'errors'          => $errors,
            ]));

            if (! $isRerun && $insuredDatabaseId) {
                $storedIds['insuredDatabaseId'] = $insuredDatabaseId;
            }

            $updateData = [
                'sync_status'     => empty($errors) ? SyncStatus::Synced : SyncStatus::Failed,
                'sync_error'      => empty($errors) ? null : implode('; ', $errors),
                'synced_entities' => $syncedEntities ?: null,
                'synced_at'       => now(),
            ];

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

    // ──────────────────────────────────────────────────────────────────────────
    // Entity sync helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns the primary entity sync map (Insured, Policy).
     * Property, Drivers, and Vehicles are handled separately.
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
        ];
    }

    /**
     * Insert or update the occupant contact on the primary insured record.
     *
     * Reads NameOfOccupant.First / .Last from the flattened entry.
     * On first sync → inserts and stores the returned contactId.
     * On rerun with stored contactId → updates instead of inserting.
     */
    private function syncOccupantContact(string $insuredDatabaseId, array $entry, array $context, bool $isRerun, array &$storedIds): void
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
                $this->nowcerts->updateContact($insuredDatabaseId, $storedContactId, $contactData);
                Log::info('NowCerts occupant contact updated', array_merge($context, [
                    'insuredDatabaseId' => $insuredDatabaseId,
                    'contactId'         => $storedContactId,
                    'occupant'          => trim("{$firstName} {$lastName}"),
                ]));
            } else {
                $response  = $this->nowcerts->insertContact($insuredDatabaseId, $contactData);
                $contactId = $response['data']['database_id']
                    ?? $response['database_id']
                    ?? $response['DatabaseId']
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
     * Sync all occupant operators as drivers via Zapier/InsertDriver.
     *
     * Reads NameOfOccupantOperator(.First/.Last), DateOfBirthOccupant,
     * and DriversLicenseOccupant from the flattened entry, including
     * numbered variants (suffix 2–20).
     */
    private function syncDrivers(?string $policyDatabaseId, ?string $insuredDatabaseId, array $entry, array $context): void
    {
        foreach ($this->extractOccupantDrivers($entry) as $driver) {
            $data = array_filter([
                'policy_database_id'  => $policyDatabaseId,
                'insured_database_id' => $insuredDatabaseId,
                'first_name'          => $driver['first_name'],
                'last_name'           => $driver['last_name'],
                'date_of_birth'       => $this->formatDate($driver['date_of_birth']),
                'license_number'      => $driver['license_number'],
            ], fn ($v) => $v !== null && $v !== '');

            $label = trim(($driver['first_name'] ?? '') . ' ' . ($driver['last_name'] ?? ''));

            try {
                $response = $this->nowcerts->zapierInsertDriver($data);
                Log::info('NowCerts driver synced', array_merge($context, ['driver' => $label, 'response' => $response]));
            } catch (Throwable $e) {
                Log::warning('NowCerts driver sync failed — non-blocking', array_merge($context, ['driver' => $label, 'error' => $e->getMessage()]));
            }
        }
    }

    /**
     * Sync all non-empty Vehicle dynamic fields via Zapier/InsertVehicle.
     *
     * Reads Vehicle1, Vehicle2, … sub-objects from the flattened entry.
     * Skips vehicles where no identifying fields (year/make/model/vin) are present.
     */
    private function syncVehicles(?string $policyDatabaseId, ?string $insuredDatabaseId, array $entry, array $context): void
    {
        foreach ($this->extractVehiclesFromEntry($entry) as $vehicle) {
            $get = fn (string ...$keys) => collect($keys)
                ->map(fn ($k) => $vehicle[$k] ?? null)
                ->first(fn ($v) => $v !== null && $v !== '');

            $year = $get('Year', 'year');

            $data = array_filter([
                'policy_database_id'        => $policyDatabaseId,
                'insured_database_id'       => $insuredDatabaseId,
                'year'                      => $year !== null ? (int) $year : null,
                'make'                      => $get('Make', 'make'),
                'model'                     => $get('Model', 'model'),
                'vin'                       => $get('VIN', 'Vin', 'vin'),
                'type'                      => $get('Type', 'type', 'VehicleType', 'vehicle_type'),
                'type_of_use'               => $get('TypeOfUse', 'type_of_use', 'VehicleUse', 'Use'),
                'description'               => $get('Description', 'description'),
                'value'                     => $get('Value', 'value', 'CostNew', 'cost_new'),
                'estimated_annual_distance' => $get('EstimatedAnnualDistance', 'estimated_annual_distance', 'AnnualMileage'),
            ], fn ($v) => $v !== null && $v !== '');

            if (empty($data['year']) && empty($data['make']) && empty($data['model']) && empty($data['vin'])) {
                continue;
            }

            $label = trim(($data['year'] ?? '') . ' ' . ($data['make'] ?? '') . ' ' . ($data['model'] ?? ''));

            try {
                $response = $this->nowcerts->zapierInsertVehicle($data);
                Log::info('NowCerts vehicle synced', array_merge($context, ['vehicle' => $label, 'response' => $response]));
            } catch (Throwable $e) {
                Log::warning('NowCerts vehicle sync failed — non-blocking', array_merge($context, ['vehicle' => $label, 'error' => $e->getMessage()]));
            }
        }
    }

    /**
     * Upload Cognito file attachments to NowCerts for the given insured.
     * Skips files whose Cognito ID was already uploaded for this entry.
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

                if ($cognitoFileId && in_array($cognitoFileId, $alreadyUploadedIds, true)) {
                    Log::info('NowCerts document skipped — already uploaded', array_merge($context, [
                        'field'         => $fieldLabel,
                        'file'          => $file['Name'] ?? $cognitoFileId,
                        'cognitoFileId' => $cognitoFileId,
                    ]));
                    continue;
                }

                $url         = $file['File'];
                $name        = $file['Name']        ?? basename($url);
                $contentType = $file['ContentType'] ?? 'application/octet-stream';

                try {
                    $this->nowcerts->uploadDocument($insuredDatabaseId, $url, $name, $contentType, $fieldLabel);
                    Log::info('NowCerts document uploaded', array_merge($context, ['field' => $fieldLabel, 'file' => $name]));

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

        if (! empty($newlyUploadedIds)) {
            $this->webhookLogs->update($log, [
                'uploaded_file_ids' => array_values(array_unique(array_merge($alreadyUploadedIds, $newlyUploadedIds))),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Property helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Map property fields, inject insured_database_id, and (on update) inject
     * the existing property's database_id so NowCerts updates rather than inserts.
     */
    private function buildPropertyData(NowCertsFieldMapper $mapper, array $entry, ?string $insuredDatabaseId, ?string $storedPropertyId = null): array
    {
        $data = $mapper->mapProperty($entry);

        if (empty($data) || ! $insuredDatabaseId) {
            return $data;
        }

        $data['insured_database_id'] = $insuredDatabaseId;

        if ($storedPropertyId) {
            $data['database_id'] = $storedPropertyId;
            return $data;
        }

        if (empty($data['database_id'])) {
            try {
                $existing   = $this->nowcerts->findProperties(['InsuredId' => $insuredDatabaseId]);
                $first      = is_array($existing) ? ($existing[0] ?? null) : null;
                $existingId = $first['databaseId'] ?? $first['DatabaseId'] ?? $first['id'] ?? null;

                if ($existingId) {
                    $data['database_id'] = $existingId;
                }
            } catch (Throwable) {
                // No existing property — will insert a new one
            }
        }

        return $data;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Extraction helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Extract occupant operators from the flattened payload.
     *
     * Handles NameOfOccupantOperator[N].First/.Last, DateOfBirthOccupant[N],
     * and DriversLicenseOccupant[N] for suffix '' and '2'..'20'.
     */
    private function extractOccupantDrivers(array $entry): array
    {
        $drivers  = [];
        $suffixes = array_merge([''], array_map('strval', range(2, 20)));

        foreach ($suffixes as $suffix) {
            $firstName = $entry["NameOfOccupantOperator{$suffix}.First"] ?? null;
            $lastName  = $entry["NameOfOccupantOperator{$suffix}.Last"]  ?? null;

            if (empty($firstName) && empty($lastName)) {
                continue;
            }

            $drivers[] = [
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'date_of_birth'  => $entry["DateOfBirthOccupant{$suffix}"]     ?? null,
                'license_number' => $entry["DriversLicenseOccupant{$suffix}"]  ?? null,
            ];
        }

        return $drivers;
    }

    /**
     * Extract non-empty Vehicle sub-objects from the flattened payload.
     *
     * Groups all Vehicle\d+.* keys by their prefix (Vehicle1, Vehicle2, …).
     * Returns only groups that have at least one non-empty field.
     */
    private function extractVehiclesFromEntry(array $entry): array
    {
        $grouped = [];

        foreach ($entry as $key => $value) {
            if (! preg_match('/^(Vehicle\d+)\.(.+)$/', $key, $m)) {
                continue;
            }

            if ($value !== null && $value !== '') {
                $grouped[$m[1]][$m[2]] = $value;
            }
        }

        return array_values($grouped);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Note helper
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Insert a sync note on the insured record summarising the webhook data
     * and the fields pushed to each NowCerts entity.
     */
    private function insertSyncNote(
        string $insuredDatabaseId,
        WebhookLog $log,
        string $formId,
        array $entry,
        array $allSyncedData,
        array $context,
    ): void {
        try {
            $lines = [
                'Form: '     . ($log->form_name ?? $formId),
                'Entry ID: ' . ($log->entry_id  ?? 'N/A'),
                'Synced at: ' . now()->format('Y-m-d H:i:s'),
            ];

            $lines[] = '[Webhook Data]';
            foreach ($entry as $key => $value) {
                if ($this->isNoteExcluded($key)) {
                    continue;
                }
                if (is_scalar($value) && $value !== '' && $value !== null) {
                    $lines[] = "  {$key}: {$value}";
                }
            }

            foreach ($allSyncedData as $entity => $fields) {
                $lines[] = "[{$entity}]";
                foreach ($fields as $key => $value) {
                    if ($this->isNoteExcluded($key)) {
                        continue;
                    }
                    if (is_scalar($value) && $value !== '' && $value !== null) {
                        $lines[] = "  {$key}: {$value}";
                    }
                }
            }

            $this->nowcerts->insertNote([
                'insured_database_id' => $insuredDatabaseId,
                'subject'             => implode("\n", $lines),
                'creator_name'        => 'Cognito Webhook',
            ]);

            Log::info('NowCerts note added to insured', array_merge($context, ['insuredDatabaseId' => $insuredDatabaseId]));
        } catch (Throwable $e) {
            Log::warning('NowCerts note failed — non-blocking', array_merge($context, ['error' => $e->getMessage()]));
        }
    }

    /**
     * Returns true when the given key should be omitted from sync notes.
     */
    private function isNoteExcluded(string $key): bool
    {
        if (in_array($key, self::NOTE_EXCLUDED_KEYS, true)) {
            return true;
        }

        foreach (self::NOTE_EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Date formatting
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Normalize any recognizable date string to MM/DD/YYYY format expected by NowCerts.
     *
     * Tries explicit formats in priority order (m/d/Y, n/j/Y, Y-m-d, m-d-Y, n-j-Y)
     * before falling back to generic PHP DateTime parsing.
     * Returns null when $value is null, empty, or unparseable.
     */
    private function formatDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        foreach (['m/d/Y', 'n/j/Y', 'Y-m-d', 'm-d-Y', 'n-j-Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('m/d/Y');
            }
        }

        try {
            return (new \DateTime($value))->format('m/d/Y');
        } catch (\Throwable) {
            return null;
        }
    }
}
