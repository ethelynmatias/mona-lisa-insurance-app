<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\NowCertsEntity;
use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Repositories\Contracts\FormFieldMappingRepositoryInterface;
use App\Repositories\Contracts\WebhookLogRepositoryInterface;
use App\Services\DatabaseLogger;
use App\Services\NowCertsFieldMapper;
use App\Services\NowCertsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CognitoWebhookController extends Controller
{
    private const NOTE_EXCLUDED_KEYS     = ['Origin', 'origin', 'Action', 'action', 'Order', 'order', 'Entry', 'entry', 'Event', 'event'];
    private const NOTE_EXCLUDED_PREFIXES = ['Entry.', 'Form.'];

    public function __construct(
        private readonly NowCertsService                     $nowcerts,
        private readonly WebhookLogRepositoryInterface       $webhookLogs,
        private readonly FormFieldMappingRepositoryInterface $mappings,
    ) {}

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

    public function clearAll(): RedirectResponse
    {
        $this->webhookLogs->truncateAll();

        return back()->with('success', 'Webhook history cleared.');
    }

    public function clearByForm(string $formId): RedirectResponse
    {
        $this->webhookLogs->deleteByForm($formId);

        return back()->with('success', 'Webhook history cleared for this form.');
    }

    public function deleteEntry(WebhookLog $log): RedirectResponse
    {
        $entryId = $log->entry_id;
        $formName = $log->form_name ?? $log->form_id;

        $log->delete();

        return back()->with('success', "Webhook entry deleted (Entry ID: {$entryId}, Form: {$formName}).");
    }

    private function syncToNowCerts(WebhookLog $log, string $formId, array $entry, bool $isRerun = false): void
    {
        $context = ['webhook_log_id' => $log->id, 'form_id' => $formId, 'entry_id' => $log->entry_id];
        if ($isRerun) {
            $storedIds = $log->synced_nowcerts_ids ?? [];
        } elseif ($log->event_type === 'entry.updated' && $log->entry_id) {
            $storedIds = $this->webhookLogs->getPreviousSyncedIds($formId, $log->entry_id, $log->id);
        } else {
            $storedIds = [];
        }

        try {
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

            DatabaseLogger::info('NowCerts sync started', array_merge($context, [
                'flattened_entry_keys' => array_keys($entry),
                'file_upload_fields'   => array_column($fileUploads, 'field'),
                'is_rerun'             => $isRerun,
                'stored_ids'           => $storedIds,
            ]));

            $syncedEntities    = [];
            $errors            = [];
            $insuredDatabaseId = null;
            $allSyncedData     = [];
            foreach ($this->primaryEntitySyncMap($mapper) as $entity => $callbacks) {
                $data = $callbacks['map']($entry);
                if ($isRerun) {
                    if ($entity === NowCertsEntity::Insured->value && ! empty($storedIds['insuredDatabaseId'])) {
                        $data['DatabaseId'] = $storedIds['insuredDatabaseId'];
                    }
                    if ($entity === NowCertsEntity::Policy->value && ! empty($storedIds['policyDatabaseId'])) {
                        $data['policyDatabaseId'] = $storedIds['policyDatabaseId'];
                    }
                }

                DatabaseLogger::info("NowCerts mapped {$entity}", array_merge($context, ['data' => $data]));

                if (empty($data)) {
                    DatabaseLogger::warning("NowCerts {$entity} skipped — no mapped fields", $context);
                    continue;
                }

                try {
                    $response = $callbacks['push']($data);
                    DatabaseLogger::info("NowCerts {$entity} pushed", array_merge($context, ['response' => $response]));
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
                    DatabaseLogger::error("NowCerts {$entity} failed", array_merge($context, ['error' => $e->getMessage()]));
                    $errors[] = "{$entity}: " . $e->getMessage();
                }
            }
            if (! $insuredDatabaseId && ! empty($storedIds['insuredDatabaseId'])) {
                $insuredDatabaseId = $storedIds['insuredDatabaseId'];
            }
            if ($insuredDatabaseId) {
                $this->syncContacts($entry, $mapper, $insuredDatabaseId, $context, $formId, $isRerun, $storedIds);
            }
            $policyDatabaseId = $storedIds['policyDatabaseId'] ?? null;
            if ($policyDatabaseId || $insuredDatabaseId) {
                $this->syncDrivers($policyDatabaseId, $insuredDatabaseId, $entry, $formId, $mapper, $context);
                $this->syncVehicles($policyDatabaseId, $insuredDatabaseId, $entry, $formId, $mapper, $context);
            }
            if ($insuredDatabaseId) {
                $this->syncProperties($entry, $mapper, $insuredDatabaseId, $context, $isRerun, $storedIds, $syncedEntities, $allSyncedData, $errors);
            }
            if ($insuredDatabaseId) {
                $this->syncGeneralLiabilityNotices($entry, $mapper, $insuredDatabaseId, $context);
            }
            $policyDatabaseId = $storedIds['policyDatabaseId'] ?? null;
            if ($policyDatabaseId) {
                $this->syncPolicyCoverages($entry, $mapper, $policyDatabaseId, $context);
            }
            if (! $isRerun && $insuredDatabaseId && ! empty($allSyncedData)) {
                $this->insertSyncNote($insuredDatabaseId, $log, $formId, $entry, $allSyncedData, $context);
            }
            if ($insuredDatabaseId && ! empty($fileUploads)) {
                $uploadedIds = $this->webhookLogs->getUploadedFileIds($formId, $log->entry_id ?? '');
                $this->syncFileUploads($insuredDatabaseId, $fileUploads, $context, $log, $uploadedIds);
            }
            if (empty($syncedEntities) && empty($errors)) {
                DatabaseLogger::warning('NowCerts sync skipped — no field mappings configured', $context);
                $this->webhookLogs->update($log, ['sync_status' => SyncStatus::Skipped]);
                return;
            }

            DatabaseLogger::info('NowCerts sync finished', array_merge($context, [
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
            DatabaseLogger::error('NowCerts sync exception', array_merge($context, ['error' => $e->getMessage()]));
            $this->webhookLogs->update($log, [
                'sync_status' => SyncStatus::Failed,
                'sync_error'  => $e->getMessage(),
            ]);
        }
    }

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

    private function syncContacts(array $entry, NowCertsFieldMapper $mapper, string $insuredDatabaseId, array $context, string $formId, bool $isRerun, array &$storedIds): void
    {
        $contacts = [];
        $seenContacts = [];

        $addContact = function (array $contact, string $source) use (&$contacts, &$seenContacts): void {
            $key = strtolower(trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')));
            if ($key === '' || isset($seenContacts[$key])) {
                return;
            }
            $seenContacts[$key] = true;
            $contact['_source'] = $source;
            $contacts[] = $contact;
        };
        $mappedContacts = $mapper->mapContacts($entry);
        foreach ($mappedContacts as $mapped) {
            if (!empty($mapped['first_name']) || !empty($mapped['last_name'])) {
                DatabaseLogger::info('NowCerts contact from UI mappings', array_merge($context, ['mapped_contact_data' => $mapped]));
                $addContact($mapped, 'UI Mappings');
            }
        }
        if (empty($mappedContacts)) {
            if ($formId === '13') {
                $legacyMapped = $mapper->mapContact($entry);
                if (!empty($legacyMapped)) {
                    DatabaseLogger::info('NowCerts contact from legacy Form 13 mapping', array_merge($context, ['legacy_contact_data' => $legacyMapped]));
                    $addContact($legacyMapped, 'Legacy Form 13');
                }
            }
        }
        $this->addAutoExtractedContacts($entry, $formId, $addContact, $context);
        foreach ($contacts as $index => $contact) {
            $source = $contact['_source'] ?? 'Unknown';
            unset($contact['_source']);
            $principalData = $this->formatContactDataForPrincipal($contact);
            $principalData['policy_database_id'] = $storedIds['policyDatabaseId'] ?? null;

            $label = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
            $label = $label ?: 'Contact #' . ($index + 1);

            try {
                $storedContactKey = "contactId_{$index}";
                $storedContactId = $storedIds[$storedContactKey] ?? null;

                if ($isRerun && $storedContactId) {
                    $this->nowcerts->updateContact($insuredDatabaseId, $storedContactId, $principalData);
                    DatabaseLogger::info('NowCerts contact updated', array_merge($context, [
                        'insuredDatabaseId' => $insuredDatabaseId,
                        'contactId' => $storedContactId,
                        'contact_name' => $label,
                        'source' => $source
                    ]));
                } else {
                    $response = $this->nowcerts->insertContact($insuredDatabaseId, $principalData);
                    $contactId = $response['data']['database_id']
                        ?? $response['database_id']
                        ?? $response['DatabaseId']
                        ?? $response['id']
                        ?? $response['Id']
                        ?? null;

                    if ($contactId) {
                        $storedIds[$storedContactKey] = $contactId;
                    }

                    DatabaseLogger::info('NowCerts contact added', array_merge($context, [
                        'insuredDatabaseId' => $insuredDatabaseId,
                        'contactId' => $contactId,
                        'contact_name' => $label,
                        'source' => $source
                    ]));
                }
            } catch (Throwable $e) {
                DatabaseLogger::warning('NowCerts contact sync failed — non-blocking', array_merge($context, [
                    'contact_name' => $label,
                    'source' => $source,
                    'contact_data' => $principalData,
                    'error' => $e->getMessage()
                ]));
            }
        }
    }

    private function addAutoExtractedContacts(array $entry, string $formId, callable $addContact, array $context): void
    {
        $firstName = $entry['NameOfOccupant.First'] ?? null;
        $lastName  = $entry['NameOfOccupant.Last']  ?? null;

        if (!empty($firstName) || !empty($lastName)) {
            $occupantContact = array_filter([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'birthday'   => $this->formatDate($entry['DateOfBirthOccupant'] ?? null),
            ], fn ($v) => $v !== null && $v !== '');

            if (!empty($occupantContact)) {
                $addContact($occupantContact, 'Auto-extracted Occupant');
            }
        }
        if ($formId === '17') {
            $coFirstName = $entry['CoapplicantsName.First'] ?? null;
            $coLastName  = $entry['CoapplicantsName.Last']  ?? null;

            if (!empty($coFirstName) || !empty($coLastName)) {
                $coApplicantContact = array_filter([
                    'first_name' => $coFirstName,
                    'last_name'  => $coLastName,
                ], fn ($v) => $v !== null && $v !== '');

                if (!empty($coApplicantContact)) {
                    $addContact($coApplicantContact, 'Auto-extracted Co-applicant');
                }
            }
        }
    }

    /**
     * Insert or update the occupant contact on the primary insured record.
     *
     * Reads NameOfOccupant.First / .Last from the flattened entry.
     * On first sync → inserts and stores the returned contactId.
     * On rerun with stored contactId → updates instead of inserting.
     *
     * @deprecated Use syncContacts() for dynamic multi-contact support
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
                DatabaseLogger::info('NowCerts occupant contact updated', array_merge($context, [
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

                DatabaseLogger::info('NowCerts occupant contact added', array_merge($context, [
                    'insuredDatabaseId' => $insuredDatabaseId,
                    'contactId'         => $contactId,
                    'occupant'          => trim("{$firstName} {$lastName}"),
                ]));
            }
        } catch (Throwable $e) {
            DatabaseLogger::warning('NowCerts occupant contact failed — non-blocking', array_merge($context, [
                'error' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Form 13 — Multi-Car Quote Form.
     * Syncs Contact entity mapped fields as a principal contact on the insured.
     * Uses the Contact entity mappings configured in the UI.
     *
     * Stored under storedIds['mappedContactId'] to prevent duplicate inserts on rerun.
     *
     * @deprecated Use syncContacts() for dynamic multi-contact support
     */
    private function syncMappedContact(string $insuredDatabaseId, array $entry, NowCertsFieldMapper $mapper, array $context, bool $isRerun, array &$storedIds): void
    {
        $contactData = $mapper->mapContact($entry);

        if (empty($contactData)) {
            return; // No Contact entity mappings configured
        }

        // Convert Contact entity field names to InsertPrincipal API format if needed
        $principalData = $this->formatContactDataForPrincipal($contactData);

        try {
            $storedContactId = $storedIds['mappedContactId'] ?? null;

            if ($isRerun && $storedContactId) {
                $this->nowcerts->updateContact($insuredDatabaseId, $storedContactId, $principalData);
                DatabaseLogger::info('NowCerts mapped contact updated', array_merge($context, [
                    'insuredDatabaseId' => $insuredDatabaseId,
                    'contactId'         => $storedContactId,
                    'contact_data'      => $principalData,
                ]));
            } else {
                $response  = $this->nowcerts->insertContact($insuredDatabaseId, $principalData);
                $contactId = $response['data']['database_id']
                    ?? $response['database_id']
                    ?? $response['DatabaseId']
                    ?? $response['id']
                    ?? $response['Id']
                    ?? null;

                if ($contactId) {
                    $storedIds['mappedContactId'] = $contactId;
                }

                DatabaseLogger::info('NowCerts mapped contact added', array_merge($context, [
                    'insuredDatabaseId' => $insuredDatabaseId,
                    'contactId'         => $contactId,
                    'contact_data'      => $principalData,
                ]));
            }
        } catch (Throwable $e) {
            DatabaseLogger::warning('NowCerts mapped contact failed — non-blocking', array_merge($context, [
                'error' => $e->getMessage(),
                'contact_data' => $principalData,
            ]));
        }
    }

    /**
     * Format Contact entity data for InsertPrincipal API.
     * Maps Contact field names to the expected Principal field names.
     */
    private function formatContactDataForPrincipal(array $contactData): array
    {
        // Map Contact fields to Principal API field names
        $fieldMap = [
            'database_id' => 'database_id',
            'first_name' => 'first_name',
            'middle_name' => 'middle_name',
            'last_name' => 'last_name',
            'description' => 'description',
            'type' => 'type',
            'personal_email' => 'personal_email',
            'business_email' => 'business_email',
            'home_phone' => 'home_phone',
            'office_phone' => 'office_phone',
            'cell_phone' => 'cell_phone',
            'personal_fax' => 'personal_fax',
            'business_fax' => 'business_fax',
            'ssn' => 'ssn',
            'birthday' => 'birthday',
            'marital_status' => 'marital_status',
            'gender' => 'gender',
            'is_driver' => 'is_driver',
            'dl_number' => 'dl_number',
            'dl_state' => 'dl_state',
            'match_record_base_on_name' => 'match_record_base_on_name',
            'is_primary' => 'is_primary',
        ];

        $result = [];
        foreach ($contactData as $contactField => $value) {
            $principalField = $fieldMap[$contactField] ?? $contactField;
            $result[$principalField] = $value;
        }

        return array_filter($result, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Form 17 — Personal Article Floaters Application.
     * Syncs CoapplicantsName as a second principal contact on the insured.
     *
     * Stored under storedIds['coApplicantContactId'] to prevent duplicate inserts on rerun.
     *
     * @deprecated Use syncContacts() for dynamic multi-contact support
     */
    private function syncForm17CoApplicant(string $insuredDatabaseId, array $entry, array $context, bool $isRerun, array &$storedIds): void
    {
        $firstName = $entry['CoapplicantsName.First'] ?? null;
        $lastName  = $entry['CoapplicantsName.Last']  ?? null;

        if (empty($firstName) && empty($lastName)) {
            return;
        }

        $contactData = array_filter([
            'first_name'         => $firstName,
            'last_name'          => $lastName,
            'policy_database_id' => $storedIds['policyDatabaseId'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $storedContactId = $storedIds['coApplicantContactId'] ?? null;

            if ($isRerun && $storedContactId) {
                $this->nowcerts->updateContact($insuredDatabaseId, $storedContactId, $contactData);
                DatabaseLogger::info('NowCerts co-applicant contact updated', array_merge($context, [
                    'insuredDatabaseId' => $insuredDatabaseId,
                    'contactId'         => $storedContactId,
                    'coApplicant'       => trim("{$firstName} {$lastName}"),
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
                    $storedIds['coApplicantContactId'] = $contactId;
                }

                DatabaseLogger::info('NowCerts co-applicant contact added', array_merge($context, [
                    'insuredDatabaseId' => $insuredDatabaseId,
                    'contactId'         => $contactId,
                    'coApplicant'       => trim("{$firstName} {$lastName}"),
                ]));
            }
        } catch (Throwable $e) {
            DatabaseLogger::warning('NowCerts co-applicant contact failed — non-blocking', array_merge($context, [
                'error' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Sync drivers via Zapier/InsertDriver.
     * Uses dynamic mapping to extract multiple drivers from field mappings,
     * then appends auto-extracted numbered variants for backward compatibility.
     * Drivers are deduplicated by normalized first+last name combination.
     */
    private function syncDrivers(?string $policyDatabaseId, ?string $insuredDatabaseId, array $entry, string $formId, NowCertsFieldMapper $mapper, array $context): void
    {
        $drivers    = [];
        $seenNames  = [];

        $addDriver = function (array $driver) use (&$drivers, &$seenNames): void {
            $key = strtolower(trim(($driver['first_name'] ?? '') . ' ' . ($driver['last_name'] ?? '')));
            if ($key === '' || isset($seenNames[$key])) {
                return;
            }
            $seenNames[$key] = true;
            $drivers[] = $driver;
        };

        // Multiple drivers from UI-configured mappings (Driver entity) - NEW DYNAMIC APPROACH
        $mappedDrivers = $mapper->mapDrivers($entry);
        foreach ($mappedDrivers as $mapped) {
            if (!empty($mapped['first_name']) || !empty($mapped['last_name'])) {
                DatabaseLogger::info('NowCerts driver from UI mappings', array_merge($context, ['mapped_driver_data' => $mapped]));
                $addDriver($mapped);
            }
        }

        // Fallback to legacy single driver mapping for backward compatibility
        if (empty($mappedDrivers)) {
            $legacyMapped = $mapper->mapDriver($entry);
            if (!empty($legacyMapped['first_name']) || !empty($legacyMapped['last_name'])) {
                DatabaseLogger::info('NowCerts driver from legacy mapping', array_merge($context, ['legacy_driver_data' => $legacyMapped]));
                $addDriver($legacyMapped, 'Legacy Mapping');
            }
        }

        // Numbered / form-specific drivers (auto-extracted for backward compatibility)
        $extracted = match ($formId) {
            '13'    => $this->extractForm13Drivers($entry),
            default => $this->extractOccupantDrivers($entry),
        };
        foreach ($extracted as $driver) {
            $addDriver($driver, 'Auto-extracted');
        }

        foreach ($drivers as $driver) {
            $data = array_filter([
                'policy_database_id'  => $policyDatabaseId,
                'insured_database_id' => $insuredDatabaseId,
                'first_name'          => $driver['first_name']     ?? null,
                'last_name'           => $driver['last_name']      ?? null,
                'middle_name'         => $driver['middle_name']    ?? null,
                'date_of_birth'       => $this->formatDate($driver['date_of_birth'] ?? null),
                'gender'              => $driver['gender']         ?? null,
                'marital_status'      => $driver['marital_status'] ?? null,
                'license_number'      => $driver['license_number'] ?? null,
                'license_state'       => $driver['license_state']  ?? null,
                'email'               => $driver['email']          ?? null,
                'phone'               => $driver['phone']          ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            $label = trim(($driver['first_name'] ?? '') . ' ' . ($driver['last_name'] ?? ''));

            try {
                $response = $this->nowcerts->zapierInsertDriver($data);
                DatabaseLogger::info('NowCerts driver synced', array_merge($context, ['driver' => $label, 'response' => $response]));
            } catch (Throwable $e) {
                DatabaseLogger::warning('NowCerts driver sync failed — non-blocking', array_merge($context, ['driver' => $label, 'error' => $e->getMessage()]));
            }
        }
    }

    /**
     * Sync vehicles via Zapier/InsertVehicle.
     * Uses dynamic mapping to extract multiple vehicles from field mappings,
     * then appends auto-extracted numbered variants for backward compatibility.
     * Vehicles are deduplicated by VIN or year+make+model combination.
     */
    private function syncVehicles(?string $policyDatabaseId, ?string $insuredDatabaseId, array $entry, string $formId, NowCertsFieldMapper $mapper, array $context): void
    {
        $vehicles   = [];
        $seenVins   = [];

        $addVehicle = function (array $vehicle) use (&$vehicles, &$seenVins): void {
            $vin   = strtolower(trim($vehicle['vin'] ?? $vehicle['VIN'] ?? $vehicle['Vin'] ?? ''));
            $year  = $vehicle['year']  ?? $vehicle['Year']  ?? '';
            $make  = $vehicle['make']  ?? $vehicle['Make']  ?? '';
            $model = $vehicle['model'] ?? $vehicle['Model'] ?? '';
            $key = $vin !== ''
                ? $vin
                : strtolower(trim("{$year} {$make} {$model}"));
            if ($key === '' || isset($seenVins[$key])) {
                return;
            }
            $seenVins[$key] = true;
            $vehicles[] = $vehicle;
        };

        // Multiple vehicles from UI-configured mappings (Vehicle entity) - NEW DYNAMIC APPROACH
        $mappedVehicles = $mapper->mapVehicles($entry);
        foreach ($mappedVehicles as $mapped) {
            if (!empty($mapped['year']) || !empty($mapped['make']) || !empty($mapped['vin'])) {
                DatabaseLogger::info('NowCerts vehicle from UI mappings', array_merge($context, ['mapped_vehicle_data' => $mapped]));
                $addVehicle($mapped);
            }
        }

        // Fallback to legacy single vehicle mapping for backward compatibility
        if (empty($mappedVehicles)) {
            $legacyMapped = $mapper->mapVehicle($entry);
            if (!empty($legacyMapped['year']) || !empty($legacyMapped['make']) || !empty($legacyMapped['vin'])) {
                DatabaseLogger::info('NowCerts vehicle from legacy mapping', array_merge($context, ['legacy_vehicle_data' => $legacyMapped]));
                $addVehicle($legacyMapped);
            }
        }

        // Numbered / form-specific vehicles (auto-extracted for backward compatibility)
        $extracted = match ($formId) {
            '13'    => $this->extractForm13Vehicles($entry),
            '16'    => $this->extractForm16Vehicles($entry),
            default => $this->extractVehiclesFromEntry($entry),
        };
        foreach ($extracted as $vehicle) {
            $addVehicle($vehicle);
        }

        foreach ($vehicles as $vehicle) {
            $get = fn (string ...$keys) => collect($keys)
                ->map(fn ($k) => $vehicle[$k] ?? null)
                ->first(fn ($v) => $v !== null && $v !== '');

            $year = $get('year', 'Year');

            $data = array_filter([
                'policy_database_id'        => $policyDatabaseId,
                'insured_database_id'       => $insuredDatabaseId,
                'year'                      => $year !== null ? (int) $year : null,
                'make'                      => $get('make', 'Make'),
                'model'                     => $get('model', 'Model'),
                'vin'                       => $get('vin', 'VIN', 'Vin'),
                'type'                      => $get('type', 'Type', 'VehicleType', 'vehicle_type'),
                'type_of_use'               => $get('type_of_use', 'TypeOfUse', 'VehicleUse', 'Use'),
                'description'               => $get('description', 'Description'),
                'value'                     => $get('value', 'Value', 'CostNew', 'cost_new'),
                'estimated_annual_distance' => $get('estimated_annual_distance', 'EstimatedAnnualDistance', 'AnnualMileage'),
            ], fn ($v) => $v !== null && $v !== '');

            if (empty($data['year']) && empty($data['make']) && empty($data['model']) && empty($data['vin'])) {
                continue;
            }

            $label = trim(($data['year'] ?? '') . ' ' . ($data['make'] ?? '') . ' ' . ($data['model'] ?? ''));

            try {
                $response = $this->nowcerts->zapierInsertVehicle($data);
                DatabaseLogger::info('NowCerts vehicle synced', array_merge($context, ['vehicle' => $label, 'response' => $response]));
            } catch (Throwable $e) {
                DatabaseLogger::warning('NowCerts vehicle sync failed — non-blocking', array_merge($context, ['vehicle' => $label, 'error' => $e->getMessage()]));
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
                    DatabaseLogger::info('NowCerts document skipped — already uploaded', array_merge($context, [
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
                    DatabaseLogger::info('NowCerts document uploaded', array_merge($context, ['field' => $fieldLabel, 'file' => $name]));

                    if ($cognitoFileId) {
                        $newlyUploadedIds[] = $cognitoFileId;
                    }
                } catch (Throwable $e) {
                    DatabaseLogger::error('NowCerts document upload failed', array_merge($context, [
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

    /**
     * Sync General Liability Notices using field mappings.
     * Uses dynamic mapping to extract multiple notices from field mappings,
     * with fallback to legacy single notice mapping for backward compatibility.
     * Notices are deduplicated by claim number or description combination.
     */
    private function syncGeneralLiabilityNotices(array $entry, NowCertsFieldMapper $mapper, string $insuredDatabaseId, array $context): void
    {
        try {
            $notices = [];
            $seenNotices = [];

            $addNotice = function (array $notice, string $source) use (&$notices, &$seenNotices): void {
                // Create a unique key based on claim_number or description for deduplication
                $key = strtolower(trim(
                    ($notice['claim_number'] ?? '') . ' ' .
                    ($notice['description_of_occurrence'] ?? '') . ' ' .
                    ($notice['description_of_loss'] ?? '')
                ));

                if ($key === '' || isset($seenNotices[$key])) {
                    return;
                }
                $seenNotices[$key] = true;
                $notice['_source'] = $source; // Track source for logging
                $notices[] = $notice;
            };

            // Multiple notices from UI-configured mappings (GeneralLiabilityNotice entity) - NEW DYNAMIC APPROACH
            $mappedNotices = $mapper->mapGeneralLiabilityNotices($entry);
            foreach ($mappedNotices as $mapped) {
                if (!empty($mapped)) {
                    DatabaseLogger::info('NowCerts GeneralLiabilityNotice from UI mappings', array_merge($context, ['mapped_notice_data' => $mapped]));
                    $addNotice($mapped, 'UI Mappings');
                }
            }

            // Fallback to legacy single notice mapping for backward compatibility
            if (empty($mappedNotices)) {
                $legacyNotice = $mapper->mapGeneralLiabilityNotice($entry);
                if (!empty($legacyNotice)) {
                    DatabaseLogger::info('NowCerts GeneralLiabilityNotice from legacy mapping', array_merge($context, ['legacy_notice_data' => $legacyNotice]));
                    $addNotice($legacyNotice, 'Legacy Mapping');
                }
            }

            foreach ($notices as $index => $notice) {
                // Ensure insured_database_id is set
                $notice['insured_database_id'] = $insuredDatabaseId;

                // Remove the source tracking field before sending to API
                $source = $notice['_source'] ?? 'Unknown';
                unset($notice['_source']);

                $noticeLabel = trim(($notice['claim_number'] ?? '') . ' ' . ($notice['description_of_occurrence'] ?? ''));
                $noticeLabel = $noticeLabel ?: 'Notice #' . ($index + 1);

                DatabaseLogger::info("NowCerts mapped GeneralLiabilityNotice ({$source})", array_merge($context, [
                    'data' => $notice,
                    'notice_label' => $noticeLabel
                ]));

                try {
                    $response = $this->nowcerts->insertGeneralLiabilityNotice($notice);
                    DatabaseLogger::info('NowCerts GeneralLiabilityNotice synced', array_merge($context, [
                        'notice_data' => $notice,
                        'notice_label' => $noticeLabel,
                        'source' => $source,
                        'response' => $response
                    ]));
                } catch (Throwable $e) {
                    DatabaseLogger::warning('NowCerts GeneralLiabilityNotice sync failed — non-blocking', array_merge($context, [
                        'notice_data' => $notice,
                        'notice_label' => $noticeLabel,
                        'source' => $source,
                        'error' => $e->getMessage()
                    ]));
                }
            }
        } catch (Throwable $e) {
            DatabaseLogger::warning('NowCerts GeneralLiabilityNotices processing failed — non-blocking', array_merge($context, [
                'error' => $e->getMessage()
            ]));
        }
    }

    /**
     * Sync Policy Coverages using field mappings.
     * Uses dynamic mapping to extract multiple coverage records from field mappings,
     * with fallback to legacy single coverage mapping for backward compatibility.
     * Coverages are restructured into the complex nested API format.
     */
    private function syncPolicyCoverages(array $entry, NowCertsFieldMapper $mapper, string $policyDatabaseId, array $context): void
    {
        try {
            $coverages = [];
            $seenCoverages = [];

            $addCoverage = function (array $coverage, string $source) use (&$coverages, &$seenCoverages): void {
                // Create a unique key based on coverage type or description for deduplication
                $key = strtolower(trim(
                    ($coverage['lineOfBusinessDatabaseId'] ?? '') . ' ' .
                    ($coverage['cargo_deductible'] ?? '') . ' ' .
                    ($coverage['generalLiability_limitEachOccurrence'] ?? '') . ' ' .
                    ($coverage['autoMobileLiability_limitCombinedSingle'] ?? '')
                ));

                if ($key === '' || isset($seenCoverages[$key])) {
                    return;
                }
                $seenCoverages[$key] = true;
                $coverage['_source'] = $source; // Track source for logging
                $coverages[] = $coverage;
            };

            // Multiple coverages from UI-configured mappings (PolicyCoverage entity) - NEW DYNAMIC APPROACH
            $mappedCoverages = $mapper->mapPolicyCoverages($entry);
            foreach ($mappedCoverages as $mapped) {
                if (!empty($mapped)) {
                    DatabaseLogger::info('NowCerts PolicyCoverage from UI mappings', array_merge($context, ['mapped_coverage_data' => $mapped]));
                    $addCoverage($mapped, 'UI Mappings');
                }
            }

            // Fallback to legacy single coverage mapping for backward compatibility
            if (empty($mappedCoverages)) {
                $legacyCoverage = $mapper->mapPolicyCoverage($entry);
                if (!empty($legacyCoverage)) {
                    DatabaseLogger::info('NowCerts PolicyCoverage from legacy mapping', array_merge($context, ['legacy_coverage_data' => $legacyCoverage]));
                    $addCoverage($legacyCoverage, 'Legacy Mapping');
                }
            }

            if (empty($coverages)) {
                return; // No policy coverage mappings configured
            }

            // Transform flat mapped data into the complex nested API structure
            $policyCoverages = [];
            foreach ($coverages as $index => $coverage) {
                $source = $coverage['_source'] ?? 'Unknown';
                unset($coverage['_source']);

                $transformedCoverage = $this->transformToPolicyCoverageApiFormat($coverage);
                $coverageLabel = $this->generateCoverageLabel($transformedCoverage, $index);

                DatabaseLogger::info("NowCerts mapped PolicyCoverage ({$source})", array_merge($context, [
                    'coverage_data' => $transformedCoverage,
                    'coverage_label' => $coverageLabel
                ]));

                $policyCoverages[] = $transformedCoverage;
            }

            // Send to API
            $apiData = [
                'policyDatabaseId' => $policyDatabaseId,
                'policyCoverages' => $policyCoverages
            ];

            try {
                $response = $this->nowcerts->insertPolicyCoverage($apiData);
                DatabaseLogger::info('NowCerts PolicyCoverages synced', array_merge($context, [
                    'coverages_count' => count($policyCoverages),
                    'policy_database_id' => $policyDatabaseId,
                    'response' => $response
                ]));
            } catch (Throwable $e) {
                DatabaseLogger::warning('NowCerts PolicyCoverage sync failed — non-blocking', array_merge($context, [
                    'coverages_count' => count($policyCoverages),
                    'policy_database_id' => $policyDatabaseId,
                    'coverage_data' => $apiData,
                    'error' => $e->getMessage()
                ]));
            }
        } catch (Throwable $e) {
            DatabaseLogger::warning('NowCerts PolicyCoverages processing failed — non-blocking', array_merge($context, [
                'error' => $e->getMessage()
            ]));
        }
    }

    /**
     * Transform flat mapped coverage data into the complex nested API format.
     */
    private function transformToPolicyCoverageApiFormat(array $flatData): array
    {
        $coverage = [];

        // Set basic identifiers
        if (!empty($flatData['lineOfBusinessDatabaseId'])) {
            $coverage['lineOfBusinessDatabaseId'] = $flatData['lineOfBusinessDatabaseId'];
        }

        // Transform nested structures
        $nestedSections = [
            'cargo' => 'cargo_',
            'physicalDamage' => 'physicalDamage_',
            'generalLiability' => 'generalLiability_',
            'autoMobileLiability' => 'autoMobileLiability_',
            'floodCoveragePrimary' => 'floodCoveragePrimary_',
            'floodCoverageExcess' => 'floodCoverageExcess_',
            'workerCompensationAndEmployersLiability' => 'workerCompensation_',
            'other' => 'other_',
            'other2' => 'other2_',
            'other3' => 'other3_',
            'other4' => 'other4_',
            'homeOwnerCoverage' => 'homeOwnerCoverage_',
            'acord27' => 'acord27_'
        ];

        foreach ($nestedSections as $sectionName => $prefix) {
            $section = [];
            foreach ($flatData as $key => $value) {
                if (strpos($key, $prefix) === 0) {
                    $fieldName = substr($key, strlen($prefix));
                    $section[$fieldName] = $this->convertApiValue($value);
                }
            }
            if (!empty($section)) {
                $coverage[$sectionName] = $section;
            }
        }

        // Handle custom coverages (special case)
        $customCoverages = [];
        foreach ($flatData as $key => $value) {
            if (strpos($key, 'customCoverages_') === 0) {
                $fieldName = substr($key, strlen('customCoverages_'));
                $customCoverages[$fieldName] = $value;
            }
        }
        if (!empty($customCoverages)) {
            $coverage['customCoverages'] = [$customCoverages]; // Array of coverage objects
        }

        return array_filter($coverage, fn($v) => !empty($v));
    }

    /**
     * Convert values to appropriate API types.
     */
    private function convertApiValue($value)
    {
        if ($value === 'true' || $value === '1') {
            return true;
        }
        if ($value === 'false' || $value === '0') {
            return false;
        }
        if (is_numeric($value)) {
            return is_float($value + 0) ? (float)$value : (int)$value;
        }
        return $value;
    }

    /**
     * Generate a label for logging purposes.
     */
    private function generateCoverageLabel(array $coverage, int $index): string
    {
        $parts = [];

        if (!empty($coverage['cargo']['deductible'])) {
            $parts[] = 'Cargo';
        }
        if (!empty($coverage['generalLiability']['limitEachOccurrence'])) {
            $parts[] = 'General Liability';
        }
        if (!empty($coverage['autoMobileLiability']['limitCombinedSingle'])) {
            $parts[] = 'Auto Liability';
        }
        if (!empty($coverage['homeOwnerCoverage']['dwellingLimit'])) {
            $parts[] = 'Home Owner';
        }

        return !empty($parts) ? implode(', ', $parts) : 'Coverage #' . ($index + 1);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Property helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Sync properties via Zapier/InsertProperty.
     * Uses dynamic mapping to extract multiple properties from field mappings,
     * with fallback to legacy single property mapping for backward compatibility.
     * Properties are deduplicated by address or other key identifiers.
     */
    private function syncProperties(array $entry, NowCertsFieldMapper $mapper, string $insuredDatabaseId, array $context, bool $isRerun, array &$storedIds, array &$syncedEntities, array &$allSyncedData, array &$errors): void
    {
        $properties = [];
        $seenProperties = [];

        $addProperty = function (array $property, string $source) use (&$properties, &$seenProperties, $context): void {
            // Create a unique key based on address or description for deduplication
            $key = strtolower(trim(
                ($property['street'] ?? '') . ' ' .
                ($property['city'] ?? '') . ' ' .
                ($property['state'] ?? '') . ' ' .
                ($property['zip'] ?? '') . ' ' .
                ($property['description'] ?? '')
            ));

            if ($key === '' || isset($seenProperties[$key])) {
                return;
            }
            $seenProperties[$key] = true;
            $property['_source'] = $source; // Track source for logging
            $properties[] = $property;
        };

        // Multiple properties from UI-configured mappings (Property entity) - NEW DYNAMIC APPROACH
        $mappedProperties = $mapper->mapProperties($entry);
        foreach ($mappedProperties as $mapped) {
            if (!empty($mapped)) {
                DatabaseLogger::info('NowCerts property from UI mappings', array_merge($context, ['mapped_property_data' => $mapped]));
                $addProperty($mapped, 'UI Mappings');
            }
        }

        // Fallback to legacy single property mapping for backward compatibility
        if (empty($mappedProperties)) {
            $legacyPropertyData = $this->buildPropertyData($mapper, $entry, $insuredDatabaseId, $isRerun ? ($storedIds['propertyDatabaseId'] ?? null) : null);
            if (!empty($legacyPropertyData)) {
                DatabaseLogger::info('NowCerts property from legacy mapping', array_merge($context, ['legacy_property_data' => $legacyPropertyData]));
                $addProperty($legacyPropertyData, 'Legacy Mapping');
            }
        }

        foreach ($properties as $index => $property) {
            // Ensure insured_database_id is set
            $property['insured_database_id'] = $insuredDatabaseId;

            // Remove the source tracking field before sending to API
            $source = $property['_source'] ?? 'Unknown';
            unset($property['_source']);

            $entityLabel = NowCertsEntity::Property->value;
            $propertyLabel = trim(($property['street'] ?? '') . ' ' . ($property['city'] ?? '') . ' ' . ($property['state'] ?? ''));
            $propertyLabel = $propertyLabel ?: 'Property #' . ($index + 1);

            DatabaseLogger::info("NowCerts mapped {$entityLabel} ({$source})", array_merge($context, [
                'data' => $property,
                'property_label' => $propertyLabel
            ]));

            try {
                $response = $this->nowcerts->zapierInsertProperty($property);
                DatabaseLogger::info("NowCerts {$entityLabel} pushed", array_merge($context, [
                    'response' => $response,
                    'property_label' => $propertyLabel
                ]));

                $syncedEntities[] = $entityLabel;
                $allSyncedData[$entityLabel . '_' . $index] = $property;

                // Store the first property's database ID for legacy compatibility
                if ($index === 0 && !$isRerun) {
                    $storedIds['propertyDatabaseId'] = $response['databaseId']
                        ?? $response['database_id']
                        ?? $response['DatabaseId']
                        ?? $response['id']
                        ?? null;
                }
            } catch (Throwable $e) {
                DatabaseLogger::error("NowCerts {$entityLabel} failed", array_merge($context, [
                    'error' => $e->getMessage(),
                    'property_label' => $propertyLabel,
                    'property_data' => $property
                ]));
                $errors[] = "{$entityLabel} ({$propertyLabel}): " . $e->getMessage();
            }
        }
    }

    /**
     * Map property fields, inject insured_database_id, and (on update) inject
     * the existing property's database_id so NowCerts updates rather than inserts.
     *
     * @deprecated Use syncProperties() for dynamic multi-property support
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
    // Form 13 — Multi-Car Quote Form extractors
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Extract drivers from Form 13 (Multi-Car Quote Form).
     *
     * Driver fields use a numbered suffix pattern:
     *   Name.First / Name.Last        + DateOfBirth, Gender, LicenseNumber, MaritalStatus
     *   Name2.First / Name2.Last      + DateOfBirth2, Gender2, LicenseNumber2, MaritalStatus2
     *   Name3 (plain string)          + DateOfBirth3, Gender3, LicenseNumber3
     *   … up to suffix 10
     */
    private function extractForm13Drivers(array $entry): array
    {
        $drivers  = [];
        $suffixes = array_map('strval', range(2, 10)); // Start from Name2, not Name

        foreach ($suffixes as $suffix) {
            // Name can be an object (flattened to Name[N].First / .Last) or a plain string
            $firstName = $entry["Name{$suffix}.First"] ?? null;
            $lastName  = $entry["Name{$suffix}.Last"]  ?? null;

            if (empty($firstName) && empty($lastName)) {
                // Fall back to plain string: "John Smith"
                $plain = $entry["Name{$suffix}"] ?? null;
                if (empty($plain) || ! is_string($plain)) {
                    continue;
                }
                $parts     = explode(' ', trim($plain), 2);
                $firstName = $parts[0] ?? null;
                $lastName  = $parts[1] ?? null;
            }

            if (empty($firstName) && empty($lastName)) {
                continue;
            }

            $drivers[] = [
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'date_of_birth'  => $entry["DateOfBirth{$suffix}"]  ?? null,
                'gender'         => $entry["Gender{$suffix}"]        ?? null,
                'license_number' => $entry["LicenseNumber{$suffix}"] ?? null,
                'marital_status' => $entry["MaritalStatus{$suffix}"] ?? null,
            ];
        }

        return $drivers;
    }

    /**
     * Extract vehicles from Form 13 (Multi-Car Quote Form).
     *
     * Vehicle fields use flat numbered-suffix keys:
     *   YearOfVehicle / YearOfVehicle2  …
     *   MakeAndModel  / MakeAndModel2   … (combined "Make Model" string)
     *   VehicleIDNumberVINForRatingAccuracy / VehicleIDNumberVINForRatingAccuracy2 …
     *   AnnualMileage / AnnualMileage2  …
     */
    private function extractForm13Vehicles(array $entry): array
    {
        $vehicles = [];
        $suffixes = array_merge([''], array_map('strval', range(2, 10)));

        foreach ($suffixes as $suffix) {
            $year      = $entry["YearOfVehicle{$suffix}"]                          ?? null;
            $makeModel = $entry["MakeAndModel{$suffix}"]                            ?? null;
            $vin       = $entry["VehicleIDNumberVINForRatingAccuracy{$suffix}"]     ?? null;
            $mileage   = $entry["AnnualMileage{$suffix}"]                           ?? null;

            if (empty($year) && empty($makeModel) && empty($vin)) {
                continue;
            }

            // MakeAndModel is a combined "Make Model" string — split on first space
            $make  = null;
            $model = null;
            if (! empty($makeModel)) {
                $parts = explode(' ', trim($makeModel), 2);
                $make  = $parts[0] ?? null;
                $model = $parts[1] ?? null;
            }

            $vehicles[] = array_filter([
                'year'                      => $year !== null ? (int) $year : null,
                'make'                      => $make,
                'model'                     => $model,
                'vin'                       => $vin,
                'estimated_annual_distance' => $mileage,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $vehicles;
    }

    /**
     * Extract vehicles AND watercraft from Form 16 (Personal Umbrella).
     *
     * Combines:
     * - Vehicle\d+.* patterns (traditional vehicles)
     * - Watercraft\d+.* patterns (watercraft treated as vehicles)
     *
     * Returns all as "vehicles" since NowCerts treats watercraft as vehicles.
     */
    private function extractForm16Vehicles(array $entry): array
    {
        $vehicles = [];

        // Extract traditional Vehicle\d+.* patterns
        $vehicleGroups = [];
        foreach ($entry as $key => $value) {
            if (! preg_match('/^(Vehicle\d+)\.(.+)$/', $key, $m)) {
                continue;
            }
            if ($value !== null && $value !== '') {
                $vehicleGroups[$m[1]][$m[2]] = $value;
            }
        }

        // Extract Watercraft\d+.* patterns and convert to vehicle format
        $watercraftGroups = [];
        foreach ($entry as $key => $value) {
            if (! preg_match('/^(Watercraft\d+)\.(.+)$/', $key, $m)) {
                continue;
            }
            if ($value !== null && $value !== '') {
                $watercraftGroups[$m[1]][$m[2]] = $value;
            }
        }

        // Add vehicle groups
        foreach ($vehicleGroups as $group => $data) {
            $vehicles[] = $data;
        }

        // Convert watercraft to vehicle format and add them
        foreach ($watercraftGroups as $group => $data) {
            $convertedVehicle = [];

            // Map watercraft fields to vehicle fields
            foreach ($data as $field => $value) {
                switch ($field) {
                    case 'TypeManufacturerModel':
                        // Split "Type Manufacturer Model" into make/model
                        $parts = explode(' ', trim($value), 3);
                        $convertedVehicle['VehicleType'] = $parts[0] ?? null;
                        $convertedVehicle['Make'] = $parts[1] ?? null;
                        $convertedVehicle['Model'] = isset($parts[2]) ? trim($parts[2]) : null;
                        break;
                    case 'Year':
                        $convertedVehicle['Year'] = $value;
                        break;
                    case 'LengthinFeet':
                        $convertedVehicle['Length'] = $value;
                        break;
                    case 'Horsepower':
                        $convertedVehicle['Horsepower'] = $value;
                        break;
                    case 'MaxSpeed':
                        $convertedVehicle['MaxSpeed'] = $value;
                        break;
                    case 'UnderlyingCarrier':
                        $convertedVehicle['UnderlyingCarrier'] = $value;
                        break;
                    case 'UnderlyingLiabilityLimit':
                        $convertedVehicle['UnderlyingLiabilityLimit'] = $value;
                        break;
                    default:
                        // Pass through any other fields as-is
                        $convertedVehicle[$field] = $value;
                        break;
                }
            }

            // Mark as watercraft origin for identification and ensure proper deduplication
            $convertedVehicle['_watercraft_source'] = $group;

            // Ensure watercraft have proper year/make/model for deduplication
            // Use VehicleType as part of make if make is missing
            if (empty($convertedVehicle['Make']) && !empty($convertedVehicle['VehicleType'])) {
                $convertedVehicle['Make'] = $convertedVehicle['VehicleType'];
            }

            if (!empty($convertedVehicle)) {
                $vehicles[] = $convertedVehicle;
            }
        }

        return $vehicles;
    }

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

            DatabaseLogger::info('NowCerts note added to insured', array_merge($context, ['insuredDatabaseId' => $insuredDatabaseId]));
        } catch (Throwable $e) {
            DatabaseLogger::warning('NowCerts note failed — non-blocking', array_merge($context, ['error' => $e->getMessage()]));
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
