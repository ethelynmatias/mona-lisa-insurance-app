<?php

namespace Tests\Feature\NowCerts;

use App\Services\NowCertsService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    public function test_get_token(): void
    {
        Cache::forget('nowcerts_access_token');
        Cache::forget('nowcerts_refresh_token');

        $service = app(NowCertsService::class);

        $token = $service->getToken();

        dump([
            'access_token'  => $token,
            'refresh_token' => Cache::get('nowcerts_refresh_token'),
        ]);

        $this->assertNotEmpty($token, 'Access token should not be empty');
    }

    public function test_refresh_token(): void
    {
        $service = app(NowCertsService::class);

        // Get initial token first
        $service->getToken();

        $refreshToken = Cache::get('nowcerts_refresh_token');

        dump(['refresh_token_before' => $refreshToken]);

        $this->assertNotEmpty($refreshToken, 'Refresh token should be stored after login');

        $newToken = $service->refreshToken();

        dump([
            'new_access_token'  => $newToken,
            'new_refresh_token' => Cache::get('nowcerts_refresh_token'),
        ]);

        $this->assertNotEmpty($newToken, 'New access token should not be empty');
    }

    public function test_token_is_cached(): void
    {
        Cache::forget('nowcerts_access_token');

        $service = app(NowCertsService::class);

        $token1 = $service->getToken();
        $token2 = $service->getToken();

        dump(['token' => $token1, 'served_from_cache' => ($token1 === $token2)]);

        $this->assertEquals($token1, $token2, 'Second call should return cached token');
    }
}
