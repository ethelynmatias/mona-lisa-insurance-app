<?php

namespace App\Services;

use App\Enums\NowCertsEntity;
use App\Traits\HandlesHttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    public function syncInsured(array $payload): array
    {
        return $this->request('POST', 'Zapier/InsertProspect', $payload);
    }

    public function upsertPolicy(array $payload): array
    {
        return $this->request('POST', 'Zapier/InsertPolicy', $payload);
    }

    public function insertContact(string $insuredDatabaseId, array $payload): array
    {
        $payload['insuredDatabaseId'] = $insuredDatabaseId;

        return $this->request('POST', 'Zapier/InsertPrincipal', $payload);
    }

    public function updateContact(string $insuredDatabaseId, string $contactDatabaseId, array $payload): array
    {
        $payload['insuredDatabaseId'] = $insuredDatabaseId;
        $payload['databaseId']        = $contactDatabaseId;

        return $this->request('POST', 'Zapier/InsertPrincipal', $payload);
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
