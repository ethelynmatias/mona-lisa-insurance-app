<?php

namespace App\Services;

use App\Enums\AirConditioningType;
use App\Enums\ConstructionType;
use App\Enums\CostValueType;
use App\Enums\DwellStyleType;
use App\Enums\ExteriorWallType;
use App\Enums\DwellUseType;
use App\Enums\FloodPolicyType;
use App\Enums\FoundationType;
use App\Enums\GarageType;
use App\Enums\OccupancyType;
use App\Enums\HeatSourcePrimaryType;
use App\Enums\NowCertsEntity;
use App\Enums\ResidenceType;
use App\Enums\RoofMaterialType;
use App\Traits\HandlesHttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NowCertsService
{
    use HandlesHttpResponse;

    private string $baseUrl;
    private string $username;
    private string $password;
    private int    $timeout;

    public function __construct()
    {
        $this->username = config('nowcerts.username');
        $this->password = config('nowcerts.password');
        $this->baseUrl  = rtrim(config('nowcerts.base_url'), '/') . '/';
        $this->timeout  = config('nowcerts.timeout', 30);

        if (empty($this->username) || empty($this->password)) {
            throw new RuntimeException('NowCerts credentials are not configured. Set NOWCERTS_USERNAME and NOWCERTS_PASSWORD in your .env file.');
        }
    }

    public function getToken(): array
    {
        $cached = Cache::get('nowcerts_tokens');
        if ($cached) {
            return $cached;
        }

        $response = Http::acceptJson()
            ->post("{$this->baseUrl}/token", [
                'userName' => $this->username,
                'password' => $this->password,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Momentum token request failed: ' . $response->body());
        }

        $data   = $response->json();
        $tokens = [
            'accessToken'  => $data['accessToken']  ?? throw new RuntimeException('Token not found in response.'),
            'refreshToken' => $data['refreshToken'] ?? throw new RuntimeException('Refresh token not found in response.'),
        ];

        $this->storeTokens($tokens);

        return $tokens;
    }

    public function refreshToken(string $accessToken, string $refreshToken): array
    {
        $response = Http::acceptJson()
            ->post("{$this->baseUrl}token/refresh", [
                'accessToken'  => $accessToken,
                'refreshToken' => $refreshToken,
            ]);

        if ($response->failed()) {
            throw new \Exception('Token refresh failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getInsureds(string $databaseId): array
    {
        return $this->request('GET', "api/InsuredDetailList({$databaseId})");
    }

    public function syncInsured(array $data): array
    {
        // If database_id was already injected (from stored IDs on entry.updated), trust it
        $databaseId = $this->validId($data['database_id'] ?? $data['DatabaseId'] ?? null);

        if (! $databaseId) {
            $existing = $this->findExistingInsured($data);

            if ($existing) {
                $databaseId = $this->validId(
                    $existing['insuredDatabaseId']
                    ?? $existing['DatabaseId']
                    ?? $existing['databaseId']
                    ?? $existing['database_id']
                    ?? null
                );

                if ($databaseId) {
                    $data['database_id'] = $databaseId;

                    Log::info('NowCerts existing insured found — will update', [
                        'insuredDatabaseId' => $databaseId,
                        'name'              => trim(($existing['firstName'] ?? $existing['FirstName'] ?? '') . ' ' . ($existing['lastName'] ?? $existing['LastName'] ?? '')),
                    ]);
                }
            }
        }

        $result = $this->upsertInsured($data);

        if (! $databaseId) {
            $databaseId = $this->validId(
                $result['DatabaseId']
                ?? $result['databaseId']
                ?? $result['database_id']
                ?? $result['insuredDatabaseId']
                ?? null
            );

            if (! $databaseId) {
                $found      = $this->findExistingInsured($data);
                $databaseId = $found
                    ? $this->validId($found['insuredDatabaseId'] ?? $found['DatabaseId'] ?? $found['databaseId'] ?? $found['database_id'] ?? null)
                    : null;
            }
        }

        $result['_insuredDatabaseId'] = $databaseId;

        return $result;
    }

    private function validId(mixed $id): ?string
    {
        if (empty($id) || $id === '00000000-0000-0000-0000-000000000000') {
            return null;
        }
        return (string) $id;
    }

    private function upsertInsured(array $data): array
    {
        // Insured/Insert handles both insert and update (via databaseId).
        // Zapier/InsertProspect was insert-only and ignored database_id.
        // return $this->request('POST', 'Zapier/InsertProspect', $data);
        return $this->request('POST', 'Insured/Insert', $this->toInsuredPayload($data));
    }

    /**
     * Convert snake_case insured field names to the camelCase fields
     * expected by the Insured/Insert endpoint. Uses Str::camel() for most
     * fields, with explicit overrides for schema-specific names.
     */
    private function toInsuredPayload(array $data): array
    {
        $overrides = [
            'database_id'              => 'databaseId',
            'email'                    => 'eMail',
            'email2'                   => 'eMail2',
            'email3'                   => 'eMail3',
            'phone_number'             => 'phone',
            'zip_code'                 => 'zipCode',
            'address_line_1'           => 'addressLine1',
            'address_line_2'           => 'addressLine2',
            'co_insured_first_name'    => 'coInsured_FirstName',
            'co_insured_last_name'     => 'coInsured_LastName',
            'co_insured_middle_name'   => 'coInsured_MiddleName',
            'co_insured_date_of_birth' => 'coInsured_DateOfBirth',
        ];

        $result = [];
        foreach ($data as $key => $value) {
            $result[$overrides[$key] ?? \Illuminate\Support\Str::camel($key)] = $value;
        }

        return $result;
    }

    private function findExistingInsured(array $data): ?array
    {
        // NowCerts mapped data uses 'EMail'; fallback to common variants
        $email = $data['EMail'] ?? $data['email'] ?? $data['Email'] ?? null;

        if ($email) {
            try {
                $results = $this->request('GET', 'Customers/GetCustomers', ['Email' => $email]);
                $items   = $results['value'] ?? (array_is_list($results) ? $results : []);

                if (! empty($items)) {
                    return $items[0];
                }
            } catch (\Throwable) {
                // fall through to name lookup
            }
        }

        $firstName = $data['FirstName'] ?? $data['first_name'] ?? '';
        $lastName  = $data['LastName']  ?? $data['last_name']  ?? '';

        if ($firstName || $lastName) {
            try {
                $results = $this->request('GET', 'Customers/GetCustomers', ['Name' => trim("{$firstName} {$lastName}")]);
                $items   = $results['value'] ?? (array_is_list($results) ? $results : []);

                if (! empty($items)) {
                    return $items[0];
                }
            } catch (\Throwable) {
                // no match
            }
        }

        return null;
    }

    public function upsertPolicy(array $payload): array
    {
        return $this->request('POST', 'Zapier/InsertPolicy', $payload);
    }

    public function insertContact(string $insuredDatabaseId, array $payload): array
    {

        $payload['insured_database_id'] = $insuredDatabaseId;

        return $this->request('POST', 'Zapier/InsertPrincipal', $payload);
    }

    public function updateContact(string $insuredDatabaseId, string $contactDatabaseId, array $payload): array
    {
        $payload['insured_database_id'] = $insuredDatabaseId;
        $payload['databaseId']        = $contactDatabaseId;

        return $this->request('POST', 'Zapier/InsertPrincipal', $payload);
    }

    public function insertNote(array $data): array
    {
        if (is_array($data['subject'] ?? null)) {
            $data['subject'] = implode("\n&emsp; • &emsp;", array_map(
                fn ($k, $v) => "<b>[{$k}]</b>=>{$v}",
                array_keys($data['subject']),
                array_values($data['subject']),
            ));
        }

        return $this->request('POST', 'Zapier/InsertNote', $data);
    }

    public function uploadDocument(
        string $insuredDatabaseId,
        string $fileUrl,
        string $fileName,
        string $contentType,
        string $fieldLabel = '',
    ): array {
        Log::info('NowCerts downloading file for upload', [
            'url'  => $fileUrl,
            'name' => $fileName,
        ]);

        $fileContent = Http::timeout($this->timeout)
            ->get($fileUrl)
            ->body();

        $folderId = $this->getInsuredDocumentsFolderId($insuredDatabaseId);

        $tokens = $this->resolveTokens();

        $query = http_build_query(array_filter([
            'insuredId'              => $insuredDatabaseId,
            'creatorName'            => 'Webhook',
            'isInsuredVisibleFolder' => 'true',
            'folderId'               => $folderId,
        ], fn ($v) => $v !== null));

        $multipart = [
            [
                'name'     => 'file',
                'contents' => $fileContent,
                'filename' => $fileName,
                'headers'  => ['Content-Type' => $contentType],
            ],
        ];

        $endpoint = 'Insured/UploadInsuredFile?' . $query;

        Log::info('NowCerts API request', [
            'method'     => 'PUT',
            'endpoint'   => $endpoint,
            'insured_id' => $insuredDatabaseId,
            'folder_id'  => $folderId,
            'file_name'  => $fileName,
        ]);

        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withToken($tokens['accessToken'])
            ->withHeaders(['Accept' => 'application/json'])
            ->send('PUT', $endpoint, ['multipart' => $multipart]);

        if ($response->status() === 401) {
            $tokens = $this->refreshToken(
                $tokens['accessToken'],
                $tokens['refreshToken']
            );

            $this->storeTokens($tokens);

            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->withToken($tokens['accessToken'])
                ->withHeaders(['Accept' => 'application/json'])
                ->send('PUT', $endpoint, ['multipart' => $multipart]);
        }

        Log::debug('NowCerts API response', [
            'status' => $response->status(),
            'body'   => $response->json() ?? $response->body(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'NowCerts UploadInsuredFile failed: ' . $response->body(),
                $response->status()
            );
        }

        return $response->json() ?? [];
    }

    private function getInsuredDocumentsFolderId(string $insuredDatabaseId): ?string
    {
        try {
            $items = $this->request('GET', "Files/GetInsuredLevelFolders/{$insuredDatabaseId}");

            if (! is_array($items)) {
                return null;
            }

            foreach ($items as $folder) {
                // Target the system "Files" folder (systemFolderType 20)
                if (($folder['systemFolderType'] ?? null) === 20) {
                    return $folder['id'] ?? null;
                }
            }

            // Fallback: any folder whose name contains "files"
            foreach ($items as $folder) {
                $name = strtolower($folder['name'] ?? '');
                if (str_contains($name, 'files')) {
                    return $folder['id'] ?? null;
                }
            }
        } catch (\Throwable) {
            // Fall through to root upload
        }

        return null;
    }

    public function zapierInsertDriver(array $data): array
    {
        return $this->request('POST', 'Zapier/InsertDriver', $data);
    }

    public function zapierInsertVehicle(array $data): array
    {
        return $this->request('POST', 'Zapier/InsertVehicle', $data);
    }

    public function zapierInsertDriverViolation(string $driverId, array $violations): array
    {
        return $this->request('POST', 'Zapier/InsertDriverViolation', [
            'driverId'         => $driverId,
            'driverViolations' => $violations,
        ]);
    }

    public function insertGeneralLiabilityNotice(array $data): array
    {
        return $this->request('POST', 'Zapier/InsertGeneralLiabilityNotice', array_filter(
            $data,
            fn ($v) => $v !== null && $v !== '',
        ));
    }

    /**
     * Create a General Liability record (POST /GeneralLiabilities).
     */
    public function insertGeneralLiability(array $data): array
    {
        return $this->request('POST', 'GeneralLiabilities', $data);
    }

    /**
     * Update a General Liability record (PUT /GeneralLiabilities).
     * $data must include the record 'id'; remaining keys form the GL payload.
     */
    public function updateGeneralLiability(array $data): array
    {
        return $this->request('PUT', 'GeneralLiabilities', $data);
    }

    public function insertPolicyCoverage(array $data): array
    {
        return $this->request('POST', 'PolicyCoverage/Insert', array_filter(
            $data,
            fn ($v) => $v !== null && $v !== '',
        ));
    }

    public function findProperties(array $params = []): array
    {
        return $this->request('GET', 'Property/FindProperties', $params);
    }

    /**
     * Search the StateList OData endpoint by (partial) state name and return the
     * best match as ['text' => name, 'value' => databaseId], or null when no
     * state matches. Used to resolve a GeneralLiability controlling state.
     *
     * GET /StateList?$filter=contains(name,'Alabama')&$top=50&$skip=0&$orderby=name
     */
    public function searchState(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        // OData string literals escape single quotes by doubling them.
        $escaped  = str_replace("'", "''", $name);
        $response = $this->request('GET', 'StateList', [
            '$filter'  => "contains(name,'{$escaped}')",
            '$top'     => 50,
            '$skip'    => 0,
            '$orderby' => 'name',
        ]);

        $states = $response['value'] ?? [];
        if (empty($states) || ! is_array($states)) {
            return null;
        }

        // Prefer an exact (case-insensitive) name match, else fall back to the first row.
        $match = null;
        foreach ($states as $state) {
            if (strcasecmp($state['name'] ?? '', $name) === 0) {
                $match = $state;
                break;
            }
        }
        $match ??= $states[0];

        $databaseId = $match['databaseId'] ?? $match['DatabaseId'] ?? null;
        if (empty($databaseId)) {
            return null;
        }

        return [
            'text'  => $match['name'] ?? $name,
            'value' => $databaseId,
        ];
    }

    public function zapierInsertProperty(array $data): array
    {
        $hasDatabaseId =
            ! empty($data['databaseId']) &&
            $data['databaseId'] !== '00000000-0000-0000-0000-000000000000';

        // Remove invalid empty GUID
        if (! $hasDatabaseId) {
            unset($data['databaseId']);
        }

        // Strip zero UUID / empty insuredDatabaseId — NowCerts rejects it
        if (! $this->validId($data['insuredDatabaseId'] ?? null)) {
            unset($data['insuredDatabaseId']);
        }

        // NowCerts rejects insured linkage fields on update
        if ($hasDatabaseId) {
            unset(
                $data['insuredDatabaseId'],
                $data['insuredEmail'],
                $data['insuredFirstName'],
                $data['insuredLastName'],
                $data['insuredCommercialName'],
            );
        }

        return $this->request('POST', 'Property/InsertOrUpdate', $this->nestPropertyPayload($data));
    }

    /**
     * Convert flat prefixed property keys to the nested object structure the API expects.
     *
     * additional1_constructionCd           → additional1.constructionCd
     * additional2_dwellStyleCd             → additional2.dwellStyleCd
     * additional_numberOfFullTimeEmployees → additional.numberOfFullTimeEmployees
     * coverage_dwelling_A_limit            → coverage.dwelling_A.limit
     * coverage_propertyTypeCd              → coverage.propertyTypeCd
     * propertyFloodInformation_city        → propertyFloodInformation.city
     * propertyLienHolders_loanNumber       → propertyLienHolders[0].loanNumber
     */
    private function nestPropertyPayload(array $data): array
    {
        $coverageSubGroups = [
            'dwelling_A', 'otherStructures_B', 'personalProperty_C',
            'lossOfUse_D', 'personalLiability_E', 'medicalPayments_F',
            'allOtherPerils', 'hurricane', 'incOrdinanceOrLaw', 'coverageCs',
        ];

        $result = [];

        foreach ($data as $key => $value) {
            // Check longer prefixes first to avoid additional_ matching additional1_/additional2_
            if (str_starts_with($key, 'additional1_')) {
                $subKey = substr($key, 12);
                if ($subKey === 'constructionCd' && is_string($value)) {
                    $value = ConstructionType::fromLabel($value)?->value;
                }
                if ($subKey === 'roofMaterialCd' && is_string($value)) {
                    $value = RoofMaterialType::fromLabel($value)?->value;
                }
                if ($subKey === 'residenceTypeCd' && is_string($value)) {
                    $value = ResidenceType::fromLabel($value)?->value;
                }
                if ($subKey === 'dwellUseCd' && is_string($value)) {
                    $value = DwellUseType::fromLabel($value)?->value;
                }
                if ($subKey === 'airConditioningCd' && is_string($value)) {
                    $value = AirConditioningType::fromLabel($value)?->value;
                }
                if ($subKey === 'numStories' && is_string($value)) {
                    $value = is_numeric($value) ? (float) $value : null;
                }
                if (in_array($subKey, ['distanceToHydrant', 'distanceToFireStation', 'yearBuilt', 'fireProtectionClassCd'], true) && is_string($value)) {
                    $value = is_numeric($value) ? (int) $value : null;
                }
                if ($value !== null) {
                    $result['additional1'][$subKey] = $value;
                }
                continue;
            }

            if (str_starts_with($key, 'additional2_')) {
                $subKey = substr($key, 12);
                if ($subKey === 'dwellStyleCd' && is_string($value)) {
                    $value = DwellStyleType::fromLabel($value)?->value;
                }
                if ($subKey === 'heatSourcePrimaryCd' && is_string($value)) {
                    $value = HeatSourcePrimaryType::fromLabel($value)?->value;
                }
                if ($subKey === 'garageTypeCd' && is_string($value)) {
                    $value = GarageType::fromLabel($value)?->value;
                }
                if (in_array($subKey, ['numberOfUnits', 'numFamilies', 'fireplaceInfoNumHearths', 'numberOfPools', 'fireplaceInfoNumChimneys', 'garageNumVehs'], true) && is_string($value)) {
                    $value = is_numeric($value) ? (int) $value : null;
                }
                if (in_array($subKey, ['estimatedReplCostAmt', 'parkingArea'], true) && is_string($value)) {
                    $value = is_numeric($value) ? (float) $value : null;
                }
                if ($value !== null) {
                    $result['additional2'][$subKey] = $value;
                }
                continue;
            }

            if (str_starts_with($key, 'additional_')) {
                $subKey = substr($key, 11);
                if (in_array($subKey, ['numberOfFullTimeEmployees', 'numberOfPartTimeEmployees'], true) && is_string($value)) {
                    $value = is_numeric($value) ? (int) $value : null;
                }
                if (in_array($subKey, ['annualRevenues', 'occupiedPct', 'occupiedArea', 'openToPublicArea', 'totalBuildingArea'], true) && is_string($value)) {
                    $value = is_numeric($value) ? (float) $value : null;
                }
                if ($value !== null) {
                    $result['additional'][$subKey] = $value;
                }
                continue;
            }

            if (str_starts_with($key, 'propertyFloodInformation_')) {
                $subKey = substr($key, 25);
                // NowCerts stores these enum fields as string codes (e.g. "5"), not ints.
                if ($subKey === 'foundationType' && is_string($value)) {
                    $resolved = FoundationType::fromLabel($value)?->value;
                    $value    = $resolved !== null ? (string) $resolved : null;
                }
                if ($subKey === 'personalPropertyCostValueType' && is_string($value)) {
                    $resolved = CostValueType::fromLabel($value)?->value;
                    $value    = $resolved !== null ? (string) $resolved : null;
                }
                if ($subKey === 'occupancy' && is_string($value)) {
                    $resolved = OccupancyType::fromLabel($value)?->value;
                    $value    = $resolved !== null ? (string) $resolved : null;
                }
                if ($subKey === 'policyType' && is_string($value)) {
                    $resolved = FloodPolicyType::fromLabel($value)?->value;
                    $value    = $resolved !== null ? (string) $resolved : null;
                }
                if ($subKey === 'construction' && is_string($value)) {
                    $resolved = ExteriorWallType::fromLabel($value)?->value;
                    $value    = $resolved !== null ? (string) $resolved : null;
                }
                if (in_array($subKey, ['buildYear', 'floodArea'], true) && is_string($value)) {
                    $value = is_numeric($value) ? (int) $value : null;
                }
                if (in_array($subKey, ['noOfStories', 'dwellingTiv', 'personalPropertyTiv', 'buildingsLimit', 'contentsLimit', 'elevationHeight'], true) && is_string($value)) {
                    $value = is_numeric($value) ? (float) $value : null;
                }
                if (in_array($subKey, ['houseElevatedAfterPriorFloodLoss', 'buildingOverWater'], true) && is_string($value)) {
                    $lower = strtolower(trim($value));
                    $value = in_array($lower, ['true', 'yes', '1'], true) ? true : (in_array($lower, ['false', 'no', '0'], true) ? false : null);
                }
                if ($value !== null) {
                    $result['propertyFloodInformation'][$subKey] = $value;
                }
                continue;
            }

            if (str_starts_with($key, 'propertyLienHolders_')) {
                $result['propertyLienHolders'][0][substr($key, 20)] = $value;
                continue;
            }

            if (str_starts_with($key, 'coverage_')) {
                $remainder = substr($key, 9);

                $matched = null;
                foreach ($coverageSubGroups as $group) {
                    if (str_starts_with($remainder, $group . '_')) {
                        $matched = $group;
                        break;
                    }
                }

                if ($matched !== null) {
                    $subKey = substr($remainder, strlen($matched) + 1);
                    $coverageNumericKeys = ['limitCsl', 'limit1', 'limit2', 'premium', 'deductible', 'deductiblePct'];
                    if (in_array($subKey, $coverageNumericKeys, true) && is_string($value)) {
                        $value = is_numeric($value) ? $value + 0 : null;
                    }
                    if ($matched === 'coverageCs') {
                        $result['coverage']['coverageCs'][0][$subKey] = $value;
                    } else {
                        $result['coverage'][$matched][$subKey] = $value;
                    }
                } else {
                    // Direct coverage field e.g. coverage_propertyTypeCd → coverage.propertyTypeCd
                    $result['coverage'][$remainder] = $value;
                }
                continue;
            }

            $result[$key] = $value;
        }

        // Drop PropertyFloodInformation when it only has classification fields
        // (foundationType/occupancy/construction) with no substantive flood data.
        // Sending it with only enum codes causes NowCerts to LINQ-query on zero-default
        // numeric fields (FloodArea=0, BuildYear=0), find no matching record, and throw NRE.
        if (isset($result['propertyFloodInformation'])) {
            $classificationOnlyKeys = ['foundationType', 'occupancy', 'construction'];
            $substantiveKeys = array_diff(
                array_keys($result['propertyFloodInformation']),
                $classificationOnlyKeys,
            );
            if (empty($substantiveKeys)) {
                unset($result['propertyFloodInformation']);
            }
        }

        // Drop coverageCs entries that have no numeric data — sending an entry
        // with only a name and all-null numeric fields causes NowCerts to throw
        // "Nullable object must have a value".
        if (isset($result['coverage']['coverageCs'])) {
            $numericKeys = ['limitCsl', 'limit1', 'limit2', 'premium', 'deductible', 'deductiblePct'];
            $result['coverage']['coverageCs'] = array_values(array_filter(
                $result['coverage']['coverageCs'],
                fn ($entry) => (bool) array_filter(
                    array_intersect_key($entry, array_flip($numericKeys)),
                    fn ($v) => $v !== null && $v !== '',
                ),
            ));

            if (empty($result['coverage']['coverageCs'])) {
                unset($result['coverage']['coverageCs']);
            }
        }

        return $result;
    }

    public function zapierInsertOpportunity(array $data): array
    {
        if (isset($data['assigned_to']) && ! is_array($data['assigned_to'])) {
            $data['assigned_to'] = [$data['assigned_to']];
        }

        // NowCerts requires these array fields to be present (even if empty).
        $data += [
            'policy_numbers'       => [],
            'policy_database_id'   => [],
            'created_from_renewal' => false,
        ];

        return $this->request('POST', 'Zapier/InsertOpportunity', $data);
    }

    public function getAvailableFields(): array
    {
        // Entities hidden from the field-mapping dropdown (sync logic still works).
        $hidden = [
            NowCertsEntity::GeneralLiabilityNotice,
        ];

        $result = [];
        foreach (NowCertsEntity::cases() as $entity) {
            if (in_array($entity, $hidden, true)) {
                continue;
            }
            $result[$entity->value] = $entity->fields();
        }
        return $result;
    }

    private function resolveTokens(): array
    {
        $accessToken  = Cache::get('nowcerts_tokens')['accessToken']  ?? null;
        $refreshToken = Cache::get('nowcerts_tokens')['refreshToken'] ?? null;

        if (! $accessToken || ! $refreshToken) {
            return $this->getToken();
        }

        return compact('accessToken', 'refreshToken');
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $tokens = $this->resolveTokens();
        $url    = $this->baseUrl . $path;

        $response = $this->send($method, $url, $tokens['accessToken'], $data);

        if ($response->status() === 401) {
            $tokens = $this->refreshToken($tokens['accessToken'], $tokens['refreshToken']);
            $this->storeTokens($tokens);

            $response = $this->send($method, $url, $tokens['accessToken'], $data);
        }

        if (! $response->successful()) {
            throw new RuntimeException("NowCerts {$method} {$path} failed: " . $response->body());
        }

        $json = $response->json();

        // Some endpoints (e.g. POST/PUT /GeneralLiabilities) return a bare scalar such as
        // the new record id, or an empty body. Wrap it so the array return contract holds
        // and callers can still read the id from ['id'] / ['result'].
        if (! is_array($json)) {
            return $json === null ? [] : ['id' => $json, 'result' => $json];
        }

        return $json;
    }

    private function send(string $method, string $url, string $accessToken, array $data): \Illuminate\Http\Client\Response
    {
        $http = Http::acceptJson()->withToken($accessToken);

        return match (strtoupper($method)) {
            'GET'  => $http->get($url, $data),
            'PUT'  => $http->put($url, $data),
            default => $http->post($url, $data),
        };
    }

    private function storeTokens(array $tokens): void
    {
        Cache::put('nowcerts_tokens', [
            'accessToken'  => $tokens['accessToken'],
            'refreshToken' => $tokens['refreshToken'],
        ], now()->addMinutes(55));
    }
}
