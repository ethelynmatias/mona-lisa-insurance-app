<?php

namespace App\Services;

use App\Enums\AirConditioningType;
use App\Enums\ConstructionType;
use App\Enums\DwellStyleType;
use App\Enums\DwellUseType;
use App\Enums\GarageType;
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
        // Insured/Insert supports updates via DatabaseId (PascalCase endpoint)
        // Zapier/InsertProspect is insert-only and ignores database_id
        if (! empty($data['database_id'])) {
            return $this->request('POST', 'Insured/Insert', $this->toInsuredPascalCase($data));
        }

        return $this->request('POST', 'Zapier/InsertProspect', $data);
    }

    /**
     * Convert snake_case insured field names to PascalCase for Insured/Insert.
     * Uses Str::studly() for most fields, with explicit overrides for special cases.
     */
    private function toInsuredPascalCase(array $data): array
    {
        $overrides = [
            'database_id'              => 'DatabaseId',
            'email'                    => 'EMail',
            'email2'                   => 'EMail2',
            'email3'                   => 'EMail3',
            'phone_number'             => 'Phone',
            'co_insured_first_name'    => 'CoInsured_FirstName',
            'co_insured_last_name'     => 'CoInsured_LastName',
            'co_insured_middle_name'   => 'CoInsured_MiddleName',
            'co_insured_date_of_birth' => 'CoInsured_DateOfBirth',
        ];

        $result = [];
        foreach ($data as $key => $value) {
            $result[$overrides[$key] ?? \Illuminate\Support\Str::studly($key)] = $value;
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

    public function insertGeneralLiabilityNotice(array $data): array
    {
        return $this->request('POST', 'Zapier/InsertGeneralLiabilityNotice', array_filter(
            $data,
            fn ($v) => $v !== null && $v !== '',
        ));
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
                if ($subKey === 'distanceToHydrant' && is_string($value)) {
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
                if ($value !== null) {
                    $result['additional2'][$subKey] = $value;
                }
                continue;
            }

            if (str_starts_with($key, 'additional_')) {
                $result['additional'][substr($key, 11)] = $value;
                continue;
            }

            if (str_starts_with($key, 'propertyFloodInformation_')) {
                $result['propertyFloodInformation'][substr($key, 25)] = $value;
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

        return $result;
    }

    public function zapierInsertOpportunity(array $data): array
    {
        return $this->request('POST', 'Zapier/InsertOpportunity', $data);
    }

    public function getAvailableFields(): array
    {
        $result = [];
        foreach (NowCertsEntity::cases() as $entity) {
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

        return $response->json();
    }

    private function send(string $method, string $url, string $accessToken, array $data): \Illuminate\Http\Client\Response
    {
        $http = Http::acceptJson()->withToken($accessToken);

        return strtoupper($method) === 'GET'
            ? $http->get($url, $data)
            : $http->post($url, $data);
    }

    private function storeTokens(array $tokens): void
    {
        Cache::put('nowcerts_tokens', [
            'accessToken'  => $tokens['accessToken'],
            'refreshToken' => $tokens['refreshToken'],
        ], now()->addMinutes(55));
    }
}
