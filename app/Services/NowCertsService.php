<?php

namespace App\Services;

use App\Enums\NowCertsEntity;
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
        $existing   = $this->findExistingInsured($data);
        $databaseId = null;

        if ($existing) {
            $databaseId         = $existing['database_id']
                ?? $existing['insuredDatabaseId']
                ?? $existing['DatabaseId']
                ?? $existing['databaseId']
                ?? null;
            $data['DatabaseId'] = $databaseId;

            Log::info('NowCerts existing insured found — will update', [
                'insuredDatabaseId' => $databaseId,
                'name'              => trim(($existing['firstName'] ?? $existing['FirstName'] ?? '') . ' ' . ($existing['lastName'] ?? $existing['LastName'] ?? '')),
            ]);
        }

        $result = $this->upsertInsured($data);

        if (! $databaseId) {
            $databaseId = $result['database_id']
                ?? $result['DatabaseId']
                ?? $result['databaseId']
                ?? $result['insuredDatabaseId']
                ?? null;

            if (! $databaseId) {
                $found      = $this->findExistingInsured($data);
                $databaseId = $found
                    ? ($found['database_id'] ?? $found['insuredDatabaseId'] ?? $found['DatabaseId'] ?? $found['databaseId'] ?? null)
                    : null;
            }
        }

        if ($databaseId) {
            try {
                $full       = $this->getInsureds($databaseId);
                $databaseId = $full['database_id']
                    ?? $full['insuredDatabaseId']
                    ?? $full['DatabaseId']
                    ?? $full['databaseId']
                    ?? $databaseId;
            } catch (\Throwable) {
                // Keep the ID we already resolved
            }
        }

        $result['_insuredDatabaseId'] = $databaseId;

        return $result;
    }

    private function upsertInsured(array $data): array
    {
        return $this->request('POST', 'Zapier/InsertProspect', $data);
    }

    private function findExistingInsured(array $data): ?array
    {
        $email = $data['email'] ?? $data['Email'] ?? null;

        if (! $email) {
            return null;
        }

        try {
            $results = $this->request('GET', 'api/Insured', [
                '$filter' => "eMail eq '{$email}'",
                '$top'    => 1,
            ]);

            $items = $results['value'] ?? $results;

            return is_array($items) && ! empty($items) ? $items[0] : null;
        } catch (\Throwable) {
            return null;
        }
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

    public function zapierInsertProperty(array $data): array
    {
        return $this->request('POST', 'Zapier/InsertProperty', $data);
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
