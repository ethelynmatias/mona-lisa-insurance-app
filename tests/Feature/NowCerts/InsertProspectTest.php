<?php

namespace Tests\Feature\NowCerts;

use App\Services\NowCertsService;
use Tests\TestCase;

class InsertProspectTest extends TestCase
{
    public function test_insert_prospect_success(): void
    {
        $service = app(NowCertsService::class);

        $result = $service->syncInsured([
            'first_name'          => 'crissh',
            'last_name'           => 'Does',
            'email'               => 'ethlocal+4@hubstart.io',
            'phone_number'        => '555-123-4567',
            'city'                => 'New York',
            'state_name'          => 'New York',
            'zip_code'            => '60601',
            'type'                => "Personal"
        ]);

        dump($result);

        $this->assertNotEmpty($result);
    }
}
