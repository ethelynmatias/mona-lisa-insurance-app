<?php

namespace Tests\Feature\NowCerts;

use App\Services\NowCertsService;
use Tests\TestCase;

class InsertInsuredTest extends TestCase
{
    public function test_login_with_credentials_returns_token(): void
    {
        $service = app(NowCertsService::class);

        $token = $service->getToken();

        dump(['token' => $token]);

        $this->assertNotEmpty($token, 'Expected a non-empty access token');
    }

    public function test_upsert_insured_live(): void
    {
        $service = app(NowCertsService::class);

        $result = $service->upsertInsured([
            'firstName'    => 'John',
            'lastName'     => 'Doe',
            'email'        => 'testeth1@hubstart.com',
            'phoneNumber'  => '(555) 123-4567',
            'cellPhone'    => '',
            'smsPhone'     => '',
            'addressLine1' => '123 Main St',
            'addressLine2' => '',
            'city'         => 'Houston',
            'state'        => 'TX',
            'zipCode'      => '77001',
            'commercialName'       => '',
            'greetingName'         => '',
            'typeOfBusiness'       => '',
            'yearBusinessStarted'  => 0,
            'website'              => '',
            'tagName'              => '',
            'tagDescription'       => '',
            'type'                 => '',
            'agents'               => [],
            'csRs'                 => [],
            'serializeAgentsModel' => false,
            'agentsModel'          => [],
            'serializeCSRsModel'   => false,
            'csRsModel'            => [],
            'referralSource'       => '',
            'middleName'           => '',
            'dateOfBirth'          => '',
            'dba'                  => '',
            'fein'                 => '',
            'naic'                 => '',
            'leadSources'          => [],
        ]);

        dump($result);
    }
}
