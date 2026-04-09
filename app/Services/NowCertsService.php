<?php

namespace App\Services;

use App\Enums\NowCertsEntity;
use App\Traits\HandlesHttpResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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
    /**
     * Known NowCerts fields per entity.
     * Shape: [ NowCertsEntity value => string[] ]
     */
    private const KNOWN_FIELDS = [
        NowCertsEntity::Insured->value => [
            'FirstName', 'LastName', 'MiddleName', 'CommercialName', 'Dba',
            'Type', 'EMail', 'EMail2', 'EMail3', 'Phone', 'CellPhone',
            'SmsPhone', 'Fax', 'BusinessPhoneNumber',
            'AddressLine1', 'AddressLine2', 'City', 'State', 'ZipCode', 'County',
            'Website', 'FEIN', 'Description', 'Active',
            'CustomerId', 'InsuredId', 'TagName', 'ReferralSourceCompanyName',
        ],
        NowCertsEntity::Policy->value => [
            'Number', 'EffectiveDate', 'ExpirationDate', 'BindDate',
            'BusinessType', 'Description', 'BillingType',
            'LineOfBusinessName', 'CarrierName', 'MgaName',
            'Premium', 'AgencyCommissionPercent',
            'InsuredDatabaseId', 'InsuredEmail', 'InsuredFirstName', 'InsuredLastName',
        ],
        NowCertsEntity::Driver->value => [
            'FirstName', 'LastName', 'MiddleName',
            'DateOfBirth', 'LicenseNumber', 'LicenseState',
            'Gender', 'MaritalStatus', 'Relation',
            'InsuredDatabaseId',
        ],
        NowCertsEntity::Vehicle->value => [
            'Year', 'Make', 'Model', 'VIN', 'BodyStyle',
            'GrossWeight', 'CostNew', 'PurchaseDate',
            'GarageState', 'GarageZip',
            'InsuredDatabaseId',
        ],
    ];

    public function getAvailableFields(): array
    {
        $fields = array_map(fn ($f) => array_values($f), self::KNOWN_FIELDS);
        $fields[NowCertsEntity::Property->value]        = $this->getPropertyFields();
        $fields['Additional']                           = $this->getPropertyAdditionalFields();
        $fields[NowCertsEntity::InsuredLocation->value] = $this->getInsuredLocationFields();
        return $fields;
    }

    /**
     * Fetch Property entity fields from the NowCerts API.
     *
     * Calls FindProperties to pull a live record and extracts its scalar keys.
     * Falls back to the documented PropertyModel fields if the API returns nothing.
     *
     * Cached for 24 hours — Property schema rarely changes.
     */
    public function getPropertyFields(): array
    {
        return [
            'DatabaseId', 'PropertyUse', 'LocationNumber', 'BuildingNumber',
            'Description', 'DescriptionOfOperations',
            'AddressLine1', 'AddressLine2', 'City', 'County', 'State', 'Zip',
            'InsuredDatabaseId', 'InsuredEmail',
            'InsuredFirstName', 'InsuredLastName', 'InsuredCommercialName',
            'AttachToPolicyNumber', 'AnyAreaLeasedToOthers',
            'Coverage', 'PoliciesDatabaseId',
        ];
    }

    public function getPropertyAdditionalFields(): array
    {
        return [
            'Additional', 'Additional1', 'Additional2', 'Additional3', 'Additional4',
            'YearBuilt', 'Construction', 'NumberOfStories', 'SquareFootage',
            'RoofType', 'RoofYear', 'ProtectionClass', 'NumberOfUnits',
        ];
    }
    /**
     * Fetch InsuredLocation entity fields from the NowCerts API.
     *
     * Calls InsuredLocationList to pull a live record and extracts its scalar keys.
     * Falls back to the documented InsuredLocation fields if the API returns nothing.
     *
     * Cached for 24 hours.
     */
    public function getInsuredLocationFields(): array
    {
        return Cache::remember('nowcerts_insured_location_fields', 86400, function () {
            try {
                $response = $this->send('GET', 'InsuredLocationList', query: ['key' => '']);
                $sample   = $this->firstFromResponse($response);

                if (is_array($sample) && ! empty($sample)) {
                    return array_keys(array_filter(
                        $sample,
                        fn ($v) => ! is_array($v),
                    ));
                }
            } catch (\Throwable) {
                // return empty — no records exist yet to infer fields from
            }

            return [];
        });
    }

    /**
     * Authenticate with NowCerts and return a cached Bearer token.
     * Token is cached for ~58 minutes (NowCerts tokens expire at 60 min).
     * Automatically refreshes if expired (401 clears the cache in handleResponse).
     */
    public function authenticate(): string
    {
        return $this->getToken();
    }

    private function getToken(): string
    {
        return Cache::remember('nowcerts_token', 3500, function () {
            // Token endpoint: POST https://api.nowcerts.com/api/token
            $tokenUrl = rtrim($this->baseUrl, '/') . '/token';

            $response = Http::timeout($this->timeout)
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type' => 'password',
                    'username'   => $this->username,
                    'password'   => $this->password,
                ]);

            if (! $response->successful()) {
                $error = $response->json('error_description')
                    ?? $response->json('error')
                    ?? $response->json('Message')
                    ?? "HTTP {$response->status()}";

                throw new RuntimeException("NowCerts authentication failed: {$error}");
            }

            $token = $response->json('access_token');

            if (! $token) {
                throw new RuntimeException('NowCerts authentication failed: no access_token in response. Body: ' . $response->body());
            }

            return $token;
        });
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withToken($this->getToken())
            ->acceptJson()
            ->asJson();
    }
    /**
     * List insureds/prospects.
     *
     * @param  array{key?:string, Active?:bool, showAll?:bool}  $params
     */
    public function getInsureds(array $params = []): array
    {
        return $this->send('GET', 'InsuredDetailList', query: $params);
    }

    /**
     * Find an insured by name, address, email, or phone.
     *
     * @param  array{Name?:string, Address?:string, Email?:string, Phone?:string,
     *               InsuredId?:string, CustomerId?:string, DatabaseId?:string}  $params
     */
    public function findInsureds(array $params = []): array
    {
        return $this->send('GET', 'Customers/GetCustomers', query: $params);
    }

    /**
     * Insert or update an insured/prospect.
     *
     * @param  array{
     *   DatabaseId?:string, CommercialName?:string, FirstName?:string, LastName?:string,
     *   MiddleName?:string, Dba?:string, Type?:string, AddressLine1?:string,
     *   AddressLine2?:string, State?:string, City?:string, ZipCode?:string,
     *   EMail?:string, EMail2?:string, EMail3?:string, Fax?:string, Phone?:string,
     *   CellPhone?:string, SmsPhone?:string, Description?:string, Active?:bool,
     *   Website?:string, FEIN?:string, CustomerId?:string, InsuredId?:string,
     *   TagName?:string, ReferralSourceCompanyName?:string,
     *   CustomFieldsSimple?:array, CustomFields?:array, Agents?:array, CSRs?:array
     * }  $data
     */
    public function upsertInsured(array $data): array
    {
        return $this->send('POST', 'Insured/Insert', body: $data);
    }

    /**
     * Find an existing insured by email or name, then insert or update.
     * Prevents duplicate records on repeated webhook fires.
     *
     * - Looks up by EMail first (most reliable identifier)
     * - Falls back to FirstName + LastName if no email
     * - If found, injects insuredDatabaseId so NowCerts updates instead of inserting
     */
    /**
     * Sync an insured to NowCerts (find-or-create).
     * Returns the API response with '_insuredDatabaseId' injected so callers
     * can use it for follow-up calls (e.g. document uploads).
     */
    public function syncInsured(array $data): array
    {
        $existing   = $this->findExistingInsured($data);
        $databaseId = null;

        if ($existing) {
            $databaseId                = $existing['insuredDatabaseId']
                ?? $existing['DatabaseId']
                ?? $existing['databaseId']
                ?? null;
            $data['insuredDatabaseId'] = $databaseId;

            Log::info('NowCerts existing insured found — will update', [
                'insuredDatabaseId' => $databaseId,
                'name'              => trim(($existing['firstName'] ?? $existing['FirstName'] ?? '') . ' ' . ($existing['lastName'] ?? $existing['LastName'] ?? '')),
            ]);
        }

        $result = $this->upsertInsured($data);

        // If we didn't have the ID before insertion, try to resolve it now
        if (! $databaseId) {
            $databaseId = $result['databaseId']
                ?? $result['DatabaseId']
                ?? $result['insuredDatabaseId']
                ?? null;

            if (! $databaseId) {
                $found      = $this->findExistingInsured($data);
                $databaseId = $found
                    ? ($found['insuredDatabaseId'] ?? $found['DatabaseId'] ?? $found['databaseId'] ?? null)
                    : null;
            }
        }

        $result['_insuredDatabaseId'] = $databaseId;

        return $result;
    }

    /**
     * Download a file from the given URL and upload it to NowCerts Documents.
     *
     * @param  string  $insuredDatabaseId  NowCerts insured DB ID
     * @param  string  $fileUrl            Cognito download URL
     * @param  string  $fileName           Original filename (e.g. "policy.pdf")
     * @param  string  $contentType        MIME type
     * @param  string  $fieldLabel         Cognito field name used as document description
     */
    public function uploadDocument(
        string $insuredDatabaseId,
        string $fileUrl,
        string $fileName,
        string $contentType,
        string $fieldLabel = '',
    ): array {
        Log::info('NowCerts downloading file for upload', ['url' => $fileUrl, 'name' => $fileName]);

        $fileContent = Http::timeout($this->timeout)->get($fileUrl)->body();

        Log::info('NowCerts API request', [
            'method'   => 'POST',
            'endpoint' => 'Document/Insert',
            'body'     => compact('insuredDatabaseId', 'fileName', 'fieldLabel'),
        ]);

        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withToken($this->getToken())
            ->attach('file', $fileContent, $fileName)
            ->post('Document/Insert', [
                'insuredDatabaseId' => $insuredDatabaseId,
                'documentName'      => $fieldLabel ? "{$fieldLabel} — {$fileName}" : $fileName,
            ]);

        Log::debug('NowCerts API response', [
            'endpoint' => 'Document/Insert',
            'status'   => $response->status(),
            'body'     => $response->json() ?? $response->body(),
        ]);

        if (! $response->successful()) {
            $message = $this->resolveErrorMessage($response->status(), $response->json(), 'Document/Insert');
            throw new RuntimeException($message, $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * Look up an existing insured by email, then by name.
     * Returns the first matching record or null.
     */
    private function findExistingInsured(array $data): ?array
    {
        // 1. Look up by email (most reliable)
        if (! empty($data['EMail'])) {
            $result = $this->firstFromResponse(
                $this->findInsureds(['Email' => $data['EMail']])
            );
            if ($result) {
                return $result;
            }
        }

        // 2. Fall back to name lookup
        $firstName = $data['FirstName'] ?? '';
        $lastName  = $data['LastName']  ?? '';

        if ($firstName || $lastName) {
            $result = $this->firstFromResponse(
                $this->findInsureds(['Name' => trim("{$firstName} {$lastName}")])
            );
            if ($result) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Extract the first record from a NowCerts list response.
     * Handles both wrapped { data: [...] } and bare [...] shapes.
     */
    private function firstFromResponse(array $response): ?array
    {
        $list = collect($response)
            ->first(fn ($v) => is_array($v) && array_is_list($v) && count($v) > 0);

        if (! $list) {
            $list = array_is_list($response) ? $response : [];
        }

        $record = $list[0] ?? null;

        return is_array($record) ? $record : null;
    }

    /**
     * Insert insured + policies in one call.
     */
    public function upsertInsuredWithPolicies(array $data): array
    {
        return $this->send('POST', 'InsuredAndPolicies/Insert', body: $data);
    }
    /**
     * List policies for an insured.
     *
     * @param  array{key?:string, isActive?:bool}  $params
     */
    public function getPolicies(array $params = []): array
    {
        return $this->send('GET', 'PolicyDetailList', query: $params);
    }

    /**
     * Find policies by number, dates, carrier, or insured.
     *
     * @param  array{Number?:string, EffD?:string, ExpD?:string, Carrier?:string,
     *               Lob?:string, IId?:string, ICommN?:string, IEmail?:string,
     *               IFN?:string, ILN?:string}  $params
     */
    public function findPolicies(array $params = []): array
    {
        return $this->send('GET', 'Policy/FindPolicies', query: $params);
    }

    /**
     * Insert or update a policy.
     *
     * @param  array{
     *   InsuredDatabaseId?:string, InsuredEmail?:string, InsuredFirstName?:string,
     *   InsuredLastName?:string, DatabaseId?:string, Number?:string,
     *   EffectiveDate?:string, ExpirationDate?:string, BindDate?:string,
     *   BusinessType?:string, Description?:string, BillingType?:string,
     *   LineOfBusinessName?:string, CarrierName?:string, MgaName?:string,
     *   Premium?:float, AgencyCommissionPercent?:float,
     *   Agents?:array, CSRs?:array
     * }  $data
     */
    public function upsertPolicy(array $data): array
    {
        return $this->send('POST', 'Policy/Insert', body: $data);
    }

    /**
     * Partial update on a policy.
     */
    public function patchPolicy(array $data): array
    {
        return $this->send('PATCH', 'Policy/PartialUpdate', body: $data);
    }
    /**
     * List drivers for an insured.
     *
     * @param  array{key?:string, Active?:bool}  $params
     */
    public function getDrivers(array $params = []): array
    {
        return $this->send('GET', 'DriverList', query: $params);
    }

    /**
     * Find drivers by name or insured.
     *
     * @param  array{FirstName?:string, LastName?:string, InsuredId?:string,
     *               ICommN?:string, IEmail?:string, IFN?:string, ILN?:string}  $params
     */
    public function findDrivers(array $params = []): array
    {
        return $this->send('GET', 'Driver/FindDrivers', query: $params);
    }

    /**
     * Insert a single driver.
     */
    public function insertDriver(array $data): array
    {
        return $this->send('POST', 'Driver/InsertDriver', body: $data);
    }

    /**
     * Bulk insert drivers.
     */
    public function bulkInsertDrivers(array $drivers): array
    {
        return $this->send('POST', 'Driver/BulkInsertDriver', body: $drivers);
    }
    /**
     * List vehicles for an insured.
     *
     * @param  array{key?:string, Active?:bool}  $params
     */
    public function getVehicles(array $params = []): array
    {
        return $this->send('GET', 'VehicleList', query: $params);
    }

    /**
     * Insert a single vehicle.
     */
    public function insertVehicle(array $data): array
    {
        return $this->send('POST', 'Vehicle/InsertVehicle', body: $data);
    }

    /**
     * Bulk insert vehicles.
     */
    public function bulkInsertVehicles(array $vehicles): array
    {
        return $this->send('POST', 'Vehicle/BulkInsertVehicle', body: $vehicles);
    }
    /**
     * List claims for an insured (all types).
     *
     * @param  array{key?:string}  $params
     */
    public function getClaims(array $params = []): array
    {
        return $this->send('GET', 'ClaimList', query: $params);
    }

    /**
     * Insert a claim via Zapier endpoint.
     */
    public function insertClaim(array $data): array
    {
        return $this->send('POST', 'Zapier/InsertClaim', body: $data);
    }
    /**
     * List notes for an insured.
     *
     * @param  array{key?:string}  $params
     */
    public function getNotes(array $params = []): array
    {
        return $this->send('GET', 'NotesList', query: $params);
    }

    /**
     * Insert a note.
     */
    public function insertNote(array $data): array
    {
        return $this->send('POST', 'Zapier/InsertNote', body: $data);
    }
    /**
     * List tasks.
     *
     * @param  array{key?:string}  $params
     */
    public function getTasks(array $params = []): array
    {
        return $this->send('GET', 'TasksList', query: $params);
    }

    /**
     * Insert or update a task.
     */
    public function upsertTask(array $data): array
    {
        return $this->send('POST', 'TasksWork/InsertUpdate', body: $data);
    }
    /**
     * List opportunities.
     *
     * @param  array{key?:string}  $params
     */
    public function getOpportunities(array $params = []): array
    {
        return $this->send('GET', 'OpportunitiesList', query: $params);
    }

    /**
     * Insert or update an opportunity.
     */
    public function upsertOpportunity(array $data): array
    {
        return $this->send('POST', 'Zapier/InsertOpportunity', body: $data);
    }
    public function getAgents(array $params = []): array
    {
        return $this->send('GET', 'AgentList', query: $params);
    }

    public function getCarriers(array $params = []): array
    {
        return $this->send('GET', 'CarrierDetailList', query: $params);
    }

    public function getLinesOfBusiness(array $params = []): array
    {
        return $this->send('GET', 'LineOfBusinessList', query: $params);
    }

    public function getStates(array $params = []): array
    {
        return $this->send('GET', 'StateList', query: $params);
    }

    public function getReferralSources(array $params = []): array
    {
        return $this->send('GET', 'ReferralSourceDetailList', query: $params);
    }
    public function heartbeat(): array
    {
        return $this->send('GET', 'heartbeat');
    }
    private function send(string $method, string $endpoint, array $body = [], array $query = []): array
    {
        $request = $this->client();

        if (! empty($query)) {
            $request = $request->withQueryParameters($query);
        }

        Log::info('NowCerts API request', [
            'method'   => strtoupper($method),
            'endpoint' => $endpoint,
            'body'     => $body,
            'query'    => $query,
        ]);

        $response = $this->dispatchRequest($request, $method, $endpoint, $body);

        Log::debug('NowCerts API response', [
            'endpoint' => $endpoint,
            'status'   => $response->status(),
            'body'     => $response->json() ?? $response->body(),
        ]);

        if ($response->status() === 401) {
            Cache::forget('nowcerts_token');
        }

        if (! $response->successful()) {
            $status  = $response->status();
            $message = $this->resolveErrorMessage($status, $response->json(), $endpoint);

            Log::error('NowCerts API error', [
                'endpoint' => $endpoint,
                'status'   => $status,
                'message'  => $message,
            ]);

            throw new RuntimeException($message, $status);
        }

        return $response->json() ?? [];
    }

    protected function resolveErrorMessage(int $status, ?array $body, string $endpoint = ''): string
    {
        return match ($status) {
            401     => 'Unauthorized: check your NOWCERTS_USERNAME and NOWCERTS_PASSWORD.',
            403     => 'Forbidden: your account does not have permission for this action.',
            404     => 'Not found: the requested resource does not exist.',
            429     => 'Rate limit exceeded: too many requests.',
            default => $body['Message'] ?? $body['message'] ?? "NowCerts API error (HTTP {$status}).",
        };
    }
}
