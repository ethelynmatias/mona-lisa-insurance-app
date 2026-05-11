<?php

namespace Tests\Feature\NowCerts;

use App\Services\NowCertsService;
use Tests\TestCase;

class InsertProspectTest extends TestCase
{
    public function test_insert_prospect_success(): void
    {
        $service = app(NowCertsService::class);

        $result = $service->insertProspect([
            'first_name'           => 'Johns',
            'last_name'            => 'Does',
            'email'               => 'eth+2@hubstart.io',
            'phone_number'         => '555-123-4567',
            'city'                => 'New York',
            'state_name'               => 'New York',
            'zip_code'             => '60601',
            'type'               => "Personal"
        ]);

        dump($result);

        $this->assertNotEmpty($result);
    }

    public function test_insert_prospect_invalid_email(): void
    {
        $service = app(NowCertsService::class);

        $this->expectException(\RuntimeException::class);

        $service->insertProspect([
            'firstName' => 'John',
            'lastName'  => 'Doe',
            'email'     => 'invalid-email',
        ]);
    }
}
