<?php

namespace Tests\Feature\Webhook;

use App\Enums\SyncStatus;
use App\Models\WebhookLog;
use App\Repositories\Contracts\FormFieldMappingRepositoryInterface;
use App\Repositories\Contracts\WebhookLogRepositoryInterface;
use App\Services\NowCertsService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Tests for Form 11 (Low Cost Insurance Homeowners) webhook processing.
 *
 * Key behaviours tested:
 *  - Full payload: insured synced, Mike Brown (occupant) inserted without
 *    policy_database_id when no policy exists — regression guard for the
 *    "Can't assign to Insured/Prospect" NowCerts API error.
 *  - With policy: policy_database_id is forwarded to insertContact when available.
 *  - Insured resolution failure: contact sync is skipped entirely.
 *  - Occupant DOB (ISO 8601) is normalised to MM/DD/YYYY before insertContact.
 *  - insertContact failure is non-blocking — overall sync still reports Synced.
 */
class Form11HomeownersWebhookTest extends TestCase
{
    private MockInterface $nowcerts;
    private MockInterface $webhookLogs;
    private MockInterface $mappings;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'logging.default'                => 'stderr',
            'logging.channels.single.driver' => 'errorlog',
            'session.driver'                 => 'array',
        ]);

        $this->nowcerts    = Mockery::mock(NowCertsService::class);
        $this->webhookLogs = Mockery::mock(WebhookLogRepositoryInterface::class);
        $this->mappings    = Mockery::mock(FormFieldMappingRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Build a WebhookLog stub. Passing 'payload' here is critical — process()
     * reads $log->payload, so without it the flattened entry is always empty.
     */
    private function makeLog(array $overrides = []): WebhookLog
    {
        $log = new WebhookLog();
        $log->id                  = $overrides['id']                  ?? 89;
        $log->form_id             = $overrides['form_id']             ?? '11';
        $log->form_name           = $overrides['form_name']           ?? 'Low Cost Insurance Insurance Homeowners Form';
        $log->event_type          = $overrides['event_type']          ?? 'entry.submitted';
        $log->entry_id            = $overrides['entry_id']            ?? '11-34';
        $log->sync_status         = $overrides['sync_status']         ?? SyncStatus::Pending;
        $log->synced_nowcerts_ids = $overrides['synced_nowcerts_ids'] ?? null;
        $log->payload             = $overrides['payload']             ?? $this->form11Payload();

        return $log;
    }

    /**
     * Real-world Form 11 payload — Sarah Williams (insured), Mike Brown (occupant).
     * Based on entry 11-34 from the live webhook log.
     */
    private function form11Payload(): array
    {
        return [
            'Id'   => '11-34',
            'Form' => [
                'Id'           => '11',
                'Name'         => 'Low Cost Insurance Insurance Homeowners Form',
                'InternalName' => 'LowCostInsuranceInsuranceHomeownersForm',
            ],
            'Entry' => [
                'Role'          => 'Public',
                'Order'         => null,
                'Action'        => 'Submit',
                'Number'        => 34,
                'Status'        => 'Submitted',
                'Version'       => 1,
                'AdminLink'     => 'https://www.cognitoforms.com/MonaLisaInsAndFinancialServicesI/11/entries/34',
                'Timestamp'     => '2026-05-12T14:06:40.916Z',
                'DateCreated'   => '2026-05-12T14:06:40.916Z',
                'DateUpdated'   => '2026-05-12T14:06:40.916Z',
                'DateSubmitted' => '2026-05-12T14:06:40.916Z',
            ],
            'Email'         => fake()->safeEmail(),
            'Email2'        => fake()->safeEmail(),
            'PhoneNumber'   => '(903) 975-8187',
            'FaxNumber'     => '(203) 337-0268',
            'DateOfBirth'   => '1993-05-28',
            'NameOfInsured' => [
                'First'         => 'Sarah',
                'Last'          => 'Williams',
                'Middle'        => null,
                'Prefix'        => null,
                'Suffix'        => null,
                'FirstAndLast'  => 'Sarah Williams',
                'MiddleInitial' => null,
            ],
            'NameOfOccupant' => [
                'First'         => 'Mike',
                'Last'          => 'Brown',
                'Middle'        => null,
                'Prefix'        => null,
                'Suffix'        => null,
                'FirstAndLast'  => 'Mike Brown',
                'MiddleInitial' => null,
            ],
            'DateOfBirthOccupant'  => '1993-05-28',
            'DateOfBirthOccupant1' => '1993-05-28',
            'DateOfBirthOccupant2' => '1993-05-28',
            'LocationAddress' => [
                'City'        => 'Chicago',
                'Line1'       => '1933 Cedar Ln',
                'Line2'       => '9427 Oak Ave',
                'State'       => 'Alabama',
                'PostalCode'  => '58160',
                'Country'     => 'United States',
                'CountryCode' => 'US',
            ],
            'PropertyAddress' => [
                'City'        => 'Phoenix',
                'Line1'       => '2255 Main St',
                'Line2'       => '2999 Cedar Ln',
                'State'       => 'Alaska',
                'PostalCode'  => '25076',
                'Country'     => 'United States',
                'CountryCode' => 'US',
            ],
            'YearOfRoof'             => '2009',
            'HomeYearBuilt'          => '2009',
            'TotalAreaSquareFootage' => '200',
            'SocialSecurityNumber'   => '444445',
            'form_id'                => '11',
            'event'                  => 'entry.submitted',
        ];
    }

    /**
     * Bind all three service mocks and common stubs:
     *   - getMappingsForForm / getUploadFieldsForForm → caller-supplied (default [])
     *   - syncInsured → caller-supplied response
     *   - getUploadedFileIds → []
     */
    private function bindMocks(
        array $syncInsuredResponse = [],
        array $fieldMappings = [],
    ): void {
        $this->mappings->shouldReceive('getMappingsForForm')->andReturn($fieldMappings);
        $this->mappings->shouldReceive('getUploadFieldsForForm')->andReturn([]);
        $this->nowcerts->shouldReceive('getAvailableFields')->andReturn([]);
        $this->nowcerts->shouldReceive('syncInsured')->andReturn($syncInsuredResponse);
        $this->webhookLogs->shouldReceive('getUploadedFileIds')->andReturn([]);

        $this->app->instance(NowCertsService::class, $this->nowcerts);
        $this->app->instance(WebhookLogRepositoryInterface::class, $this->webhookLogs);
        $this->app->instance(FormFieldMappingRepositoryInterface::class, $this->mappings);
    }

    // ---------------------------------------------------------------------------
    // Test: no policy → policy_database_id must NOT be sent
    //       Regression guard for "Can't assign to Insured/Prospect" (entry 11-35)
    // ---------------------------------------------------------------------------

    public function test_insert_contact_omits_policy_database_id_when_no_policy_exists(): void
    {
        $insuredId = 'insured-uuid-sarah';
        $contactId = 'contact-uuid-mike';

        // log->payload must hold the real payload so process() can flatten the entry
        $log = $this->makeLog(['payload' => $this->form11Payload()]);

        $this->webhookLogs->shouldReceive('create')->once()->andReturn($log);
        $this->webhookLogs->shouldReceive('saveDiscoveredFields')->once();
        $this->webhookLogs
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (WebhookLog $l, array $d) => ($d['sync_status'] ?? null) === SyncStatus::Synced);

        // No policy field mapping → mapPolicy returns [] → upsertPolicy never called
        $this->bindMocks(['_insuredDatabaseId' => $insuredId]);

        $this->nowcerts
            ->shouldReceive('insertContact')
            ->once()
            ->with(
                $insuredId,
                Mockery::on(function (array $payload) {
                    return $payload['first_name'] === 'Mike'
                        && $payload['last_name']  === 'Brown'
                        // policy_database_id must be absent entirely (not null) to avoid the API error
                        && ! array_key_exists('policy_database_id', $payload);
                })
            )
            ->andReturn(['data' => ['database_id' => $contactId]]);

        $this->nowcerts->shouldReceive('insertNote')->andReturn([]);

        $response = $this->postJson('/webhook/cognito?form_id=11&event=entry.submitted', $this->form11Payload());

        $response->assertOk()->assertJson(['ok' => true]);
    }

    // ---------------------------------------------------------------------------
    // Test: with policy → policy_database_id IS included in insertContact payload
    // ---------------------------------------------------------------------------

    public function test_insert_contact_includes_policy_database_id_when_policy_exists(): void
    {
        $insuredId = 'insured-uuid-sarah';
        $policyId  = 'policy-uuid-home';
        $contactId = 'contact-uuid-mike';

        $log = $this->makeLog(['payload' => $this->form11Payload()]);

        $this->webhookLogs->shouldReceive('create')->once()->andReturn($log);
        $this->webhookLogs->shouldReceive('saveDiscoveredFields')->once();
        $this->webhookLogs->shouldReceive('update')->once();

        // Configure a Policy field mapping so mapPolicy returns non-empty data
        // and upsertPolicy is actually called.
        $policyMapping = [
            'Email' => ['entity' => 'Policy', 'field' => 'email_address'],
        ];

        $this->bindMocks(
            syncInsuredResponse: ['_insuredDatabaseId' => $insuredId],
            fieldMappings: $policyMapping,
        );

        $this->nowcerts
            ->shouldReceive('upsertPolicy')
            ->once()
            ->andReturn(['policyDatabaseId' => $policyId]);

        $this->nowcerts
            ->shouldReceive('insertContact')
            ->once()
            ->with(
                $insuredId,
                Mockery::on(fn (array $payload) =>
                    $payload['first_name']                       === 'Mike'
                    && $payload['last_name']                     === 'Brown'
                    && ($payload['policy_database_id'] ?? null)  === $policyId
                )
            )
            ->andReturn(['data' => ['database_id' => $contactId]]);

        $this->nowcerts->shouldReceive('insertNote')->andReturn([]);

        $response = $this->postJson('/webhook/cognito?form_id=11&event=entry.submitted', $this->form11Payload());

        $response->assertOk()->assertJson(['ok' => true]);
    }

    // ---------------------------------------------------------------------------
    // Test: insured resolution fails → contact sync is skipped entirely
    // ---------------------------------------------------------------------------

    public function test_contact_sync_skipped_when_insured_cannot_be_resolved(): void
    {
        $log = $this->makeLog(['payload' => $this->form11Payload()]);

        $this->webhookLogs->shouldReceive('create')->once()->andReturn($log);
        $this->webhookLogs->shouldReceive('saveDiscoveredFields')->once();
        $this->webhookLogs->shouldReceive('update')->once();

        // syncInsured is called (Sarah Williams resolves from the flattened entry)
        // but returns no database ID
        $this->bindMocks(['_insuredDatabaseId' => null]);

        $this->nowcerts->shouldReceive('insertContact')->never();
        $this->nowcerts->shouldReceive('updateContact')->never();
        $this->nowcerts->shouldReceive('insertNote')->andReturn([]);

        $response = $this->postJson('/webhook/cognito?form_id=11&event=entry.submitted', $this->form11Payload());

        $response->assertOk()->assertJson(['ok' => true]);
    }

    // ---------------------------------------------------------------------------
    // Test: DateOfBirthOccupant (ISO 8601 from Cognito) normalised to MM/DD/YYYY
    // ---------------------------------------------------------------------------

    public function test_occupant_date_of_birth_normalised_from_iso8601(): void
    {
        $insuredId = 'insured-uuid-sarah';

        $log = $this->makeLog(['payload' => $this->form11Payload()]);

        $this->webhookLogs->shouldReceive('create')->once()->andReturn($log);
        $this->webhookLogs->shouldReceive('saveDiscoveredFields')->once();
        $this->webhookLogs->shouldReceive('update')->once();

        $this->bindMocks(['_insuredDatabaseId' => $insuredId]);

        $this->nowcerts
            ->shouldReceive('insertContact')
            ->once()
            ->with(
                $insuredId,
                Mockery::on(fn (array $payload) =>
                    $payload['first_name']          === 'Mike'
                    && $payload['last_name']         === 'Brown'
                    && ($payload['birthday'] ?? null) === '05/28/1993'   // 1993-05-28 → MM/DD/YYYY
                )
            )
            ->andReturn(['data' => ['database_id' => 'contact-uuid-mike']]);

        $this->nowcerts->shouldReceive('insertNote')->andReturn([]);

        $response = $this->postJson('/webhook/cognito?form_id=11&event=entry.submitted', $this->form11Payload());

        $response->assertOk();
    }

    // ---------------------------------------------------------------------------
    // Test: insertContact throws → non-blocking, overall sync still Synced
    // ---------------------------------------------------------------------------

    public function test_insert_contact_failure_does_not_fail_overall_sync(): void
    {
        $insuredId = 'insured-uuid-sarah';

        $log = $this->makeLog(['payload' => $this->form11Payload()]);

        $this->webhookLogs->shouldReceive('create')->once()->andReturn($log);
        $this->webhookLogs->shouldReceive('saveDiscoveredFields')->once();
        $this->webhookLogs
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (WebhookLog $l, array $d) => ($d['sync_status'] ?? null) === SyncStatus::Synced);

        $this->bindMocks(['_insuredDatabaseId' => $insuredId]);

        $this->nowcerts
            ->shouldReceive('insertContact')
            ->once()
            ->andThrow(new \RuntimeException(
                'NowCerts POST Zapier/InsertPrincipal failed: "Can\'t assign to Insured/Prospect"'
            ));

        $this->nowcerts->shouldReceive('insertNote')->andReturn([]);

        $response = $this->postJson('/webhook/cognito?form_id=11&event=entry.submitted', $this->form11Payload());

        $response->assertOk()->assertJson(['ok' => true]);
    }
}
