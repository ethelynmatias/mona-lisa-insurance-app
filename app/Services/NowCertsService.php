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
     * Get API Token
     */
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
            throw new RuntimeException(
                'Momentum token request failed: ' . $response->body()
            );
        }

        $data = $response->json();
        $tokens = [
            'accessToken'  => $data['accessToken'] ?? throw new RuntimeException('Token not found in response.'),
            'refreshToken' => $data['refreshToken'] ?? throw new RuntimeException('Refresh token not found in response.'),
        ];

        Cache::put('nowcerts_tokens', $tokens, now()->addMinutes(55));

        return $tokens;
    }

    public function refreshToken(string $accessToken, string $refreshToken): array
    {
        $response = Http::acceptJson()
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}token/refresh", [
                'accessToken'  => $accessToken,
                'refreshToken' => $refreshToken,
            ]);

        if ($response->failed()) {
            throw new \Exception(
                'Token refresh failed: ' . $response->body()
            );
        }

        return $response->json();
    }

    public function insertProspect(array $payload): array
    {
        $accessToken  = $this->getStoredAccessToken();
        $refreshToken = $this->getStoredRefreshToken();

        if (! $accessToken || ! $refreshToken) {
            $tokens       = $this->getToken();
            $accessToken  = $tokens['accessToken'];
            $refreshToken = $tokens['refreshToken'];
        }

        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->post("{$this->baseUrl}Zapier/InsertProspect", $payload);

        if ($response->status() === 401) {
            $newTokens = $this->refreshToken($accessToken, $refreshToken);
            $this->storeTokens($newTokens);

            $response = Http::acceptJson()
                ->withToken($newTokens['accessToken'])
                ->post("{$this->baseUrl}Zapier/InsertProspect", $payload);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'InsertProspect failed: ' . $response->body()
            );
        }

        return $response->json();
    }

    private function getStoredAccessToken(): ?string
    {
        return Cache::get('nowcerts_tokens')['accessToken'] ?? null;
    }

    private function getStoredRefreshToken(): ?string
    {
        return Cache::get('nowcerts_tokens')['refreshToken'] ?? null;
    }

    private function storeTokens(array $tokens): void
    {
        Cache::put('nowcerts_tokens', [
            'accessToken'  => $tokens['accessToken'],
            'refreshToken' => $tokens['refreshToken'],
        ], now()->addMinutes(55));
    }

}
