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
        $tokens = [];
        $tokens['accessToken'] = $data['accessToken'];
        $tokens['refreshToken'] = $data['refreshToken'];

        return $tokens
            ?? throw new RuntimeException('Token not found in response.');
    }

    /**
     * Create Prospect
     */
    public function insertProspect(array $payload): array
    {
        $tokens = $this->getToken();

        $response = Http::acceptJson()
            ->withToken($tokens['accessToken'])
            ->post("{$this->baseUrl}Zapier/InsertProspect", $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'InsertProspect failed: ' . $response->body()
            );
        }

        return $response->json();
    }

}
