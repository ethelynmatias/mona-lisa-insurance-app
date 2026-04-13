<?php

namespace Tests\Feature\Webhook;

use App\Enums\SyncStatus;
use App\Http\Controllers\Webhook\CognitoWebhookController;
use App\Models\WebhookLog;
use App\Repositories\Contracts\FormFieldMappingRepositoryInterface;
use App\Repositories\Contracts\WebhookLogRepositoryInterface;
use App\Services\NowCertsService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Tests for the syncOccupantContact path inside CognitoWebhookController.
 *
 * The occupant contact sync runs when the flattened Cognito entry contains
 * NameOfOccupant.First or NameOfOccupant.Last and an insuredDatabaseId has
 * been resolved from the prior syncInsured call.
 *
 * Key behaviours tested:
 *  - First sync: insertContact is called and the returned contactId is stored.
 *  - Rerun with stored contactId: updateContact is called (no duplicate insert).
 *  - Rerun without stored contactId: falls back to insertContact and persists the new id.
 *  - No occupant fields: neither insertContact nor updateContact is called.
 *  - insertContact throws: sync still completes (non-blocking failure).
 *  - DateOfBirthOccupant: passed through to the contact payload.
 */
class SyncOccupantContactTest extends TestCase
{
    private MockInterface $nowcerts;
    private MockInterface $webhookLogs;
    private MockInterface $mappings;

    protected function setUp(): void
    {
        parent::setUp();

        // Redirect logs to stderr; use in-memory session so no DB connection is needed
        config([
            'logging.default' => 'stderr',
            'session.driver'  => 'array',
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

    private function makeLog(array $overrides = []): WebhookLog
    {
        $log = new WebhookLog();
        $log->id                  = $overrides['id']                  ?? 1;
        $log->form_id             = $overrides['form_id']             ?? '11';
        $log->form_name           = $overrides['form_name']           ?? 'Test Form';
        $log->event_type          = $overrides['event_type']          ?? 'entry.submitted';
        $log->entry_id            = $overrides['entry_id']            ?? '11-18';
        $log->sync_status         = $overrides['sync_status']         ?? SyncStatus::Pending;
        $log->synced_nowcerts_ids = $overrides['synced_nowcerts_ids'] ?? null;
        $log->payload             = $overrides['payload']             ?? [];

        return $log;
    }

    /**
     * Minimal Cognito payload that produces NameOfOccupant.First / NameOfOccupant.Last
     * after NowCertsFieldMapper::flattenEntry().
     */
    private function occupantPayload(string $first = 'Jane', string $last = 'Doe', ?string $dob = null): array
    {
        $payload = [
            'Id'   => '11-18',
            'Form' => ['Id' => '11', 'Name' => 'Test Form', 'InternalName' => 'test'],
            'Entry' => [
                'Role' => 'Public', 'Order' => 1, 'Action' => 'Submit',
                'Number' => 1, 'Status' => 'Submitted', 'Version' => 1,
                'AdminLink' => '', 'Timestamp' => now()->toIso8601String(),
                'DateCreated' => now()->toIso8601String(),
                'DateUpdated' => now()->toIso8601String(),
                'DateSubmitted' => now()->toIso8601String(),
            ],
            'NameOfInsured'  => [
                'First' => 'John', 'Last' => 'Smith', 'Middle' => '',
                'Prefix' => '', 'Suffix' => '', 'FirstAndLast' => 'John Smith',
                'MiddleInitial' => '',
            ],
            'NameOfOccupant' => [
                'First' => $first, 'Last' => $last, 'Middle' => '',
                'Prefix' => '', 'Suffix' => '',
                'FirstAndLast' => "{$first} {$last}",
                'MiddleInitial' => '',
            ],
        ];

        if ($dob !== null) {
            $payload['DateOfBirthOccupant'] = $dob;
        }

        return $payload;
    }

    /**
     * Bind mocks into the container and register common stubs:
     * - getMappingsForForm → [] (all entities produce no mapped fields)
     * - getAvailableFields → [] (no NowCerts field list needed)
     * - syncInsured → caller-supplied response (provides insuredDatabaseId)
     * - getUploadedFileIds → [] (no prior file uploads)
     */
    private function bindMocks(array $syncInsuredResponse = []): void
    {
        $this->mappings->shouldReceive('getMappingsForForm')->andReturn([]);
        $this->nowcerts->shouldReceive('getAvailableFields')->andReturn([]);
        $this->nowcerts->shouldReceive('syncInsured')->andReturn($syncInsuredResponse);
        $this->webhookLogs->shouldReceive('getUploadedFileIds')->andReturn([]);

        $this->app->instance(NowCertsService::class, $this->nowcerts);
        $this->app->instance(WebhookLogRepositoryInterface::class, $this->webhookLogs);
        $this->app->instance(FormFieldMappingRepositoryInterface::class, $this->mappings);
    }

    // ---------------------------------------------------------------------------
    // Test: first sync — inserts occupant contact and stores contactId
    // ---------------------------------------------------------------------------

    public function test_first_sync_inserts_occupant_contact_and_stores_contact_id(): void
    {
        $insuredId = 'insured-uuid-111';
        $contactId = 'contact-uuid-999';

        $log = $this->makeLog();

        $this->webhookLogs->shouldReceive('create')->once()->andReturn($log);
        $this->webhookLogs->shouldReceive('saveDiscoveredFields')->once();

        // Final update must contain the new contactId and insuredDatabaseId
        $this->webhookLogs
            ->shouldReceive('update')
            ->once()
            ->withArgs(function (WebhookLog $l, array $data) use ($contactId, $insuredId) {
                return $data['sync_status'] === SyncStatus::Synced
                    && ($data['synced_nowcerts_ids']['contactId']         ?? null) === $contactId
                    && ($data['synced_nowcerts_ids']['insuredDatabaseId'] ?? null) === $insuredId;
            });

        $this->bindMocks(['_insuredDatabaseId' => $insuredId]);

        // insertContact called once with first/last name; returns contactId
        $this->nowcerts
            ->shouldReceive('insertContact')
            ->once()
            ->with($insuredId, Mockery::on(fn (array $d) =>
                $d['first_name'] === 'Jane' && $d['last_name'] === 'Doe'
            ))
            ->andReturn(['data' => ['database_id' => $contactId]]);

        // Note is inserted on first (non-rerun) sync
        $this->nowcerts->shouldReceive('insertNote')->once()->andReturn([]);

        $response = $this->postJson('/webhook/cognito?form_id=11&event=entry.submitted', $this->occupantPayload());

        $response->assertOk()->assertJson(['ok' => true]);
    }

    // ---------------------------------------------------------------------------
    // Test: rerun WITH stored contactId — updates existing contact (no duplicate)
    // ---------------------------------------------------------------------------

    public function test_rerun_with_stored_contact_id_updates_existing_contact(): void
    {
        $insuredId = 'insured-uuid-111';
        $contactId = 'contact-uuid-999';

        $log = $this->makeLog([
            'event_type'          => 'entry.submitted',
            'synced_nowcerts_ids' => [
                'insuredDatabaseId' => $insuredId,
                'contactId'         => $contactId,
            ],
            'payload' => $this->occupantPayload('Jane', 'Doe'),
        ]);

        // rerunSync resets status first, then updates with final result
        $this->webhookLogs
            ->shouldReceive('update')
            ->once()
            ->with(Mockery::type(WebhookLog::class), Mockery::on(
                fn ($d) => ($d['sync_status'] ?? null) === SyncStatus::Pending
            ))
            ->ordered();

        $this->webhookLogs
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (WebhookLog $l, array $d) => ($d['sync_status'] ?? null) === SyncStatus::Synced)
            ->ordered();

        $this->bindMocks(['_insuredDatabaseId' => $insuredId]);

        // updateContact called with the stored contactId — insertContact must NOT run
        $this->nowcerts
            ->shouldReceive('updateContact')
            ->once()
            ->with($insuredId, $contactId, Mockery::on(fn (array $d) =>
                $d['first_name'] === 'Jane' && $d['last_name'] === 'Doe'
            ))
            ->andReturn([]);

        $this->nowcerts->shouldReceive('insertContact')->never();

        // Note is skipped on rerun
        $this->nowcerts->shouldReceive('insertNote')->never();

        $controller = $this->app->make(CognitoWebhookController::class);
        $controller->rerunSync($log);

        // Mockery expectations above already assert updateContact was called and insertContact was not.
        // Add a PHPUnit assertion so the test is not flagged as risky.
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    // ---------------------------------------------------------------------------
    // Test: rerun WITHOUT stored contactId — falls back to insert (the bug scenario)
    // ---------------------------------------------------------------------------

    public function test_rerun_without_stored_contact_id_falls_back_to_insert(): void
    {
        $insuredId    = 'insured-uuid-111';
        $newContactId = 'contact-uuid-new';

        $log = $this->makeLog([
            'event_type'          => 'entry.submitted',
            'synced_nowcerts_ids' => [
                'insuredDatabaseId' => $insuredId,
                // no contactId — simulates missing contactId from previous sync
            ],
            'payload' => $this->occupantPayload('Jane', 'Doe'),
        ]);

        $this->webhookLogs
            ->shouldReceive('update')
            ->once()
            ->with(Mockery::type(WebhookLog::class), Mockery::on(
                fn ($d) => ($d['sync_status'] ?? null) === SyncStatus::Pending
            ))
            ->ordered();

        // After fallback insert, the new contactId should be persisted
        $this->webhookLogs
            ->shouldReceive('update')
            ->once()
            ->withArgs(function (WebhookLog $l, array $data) use ($newContactId) {
                return ($data['sync_status'] ?? null) === SyncStatus::Synced
                    && ($data['synced_nowcerts_ids']['contactId'] ?? null) === $newContactId;
            })
            ->ordered();

        $this->bindMocks(['_insuredDatabaseId' => $insuredId]);

        $this->nowcerts->shouldReceive('updateContact')->never();
        $this->nowcerts
            ->shouldReceive('insertContact')
            ->once()
            ->andReturn(['data' => ['database_id' => $newContactId]]);

        $controller = $this->app->make(CognitoWebhookController::class);
        $controller->rerunSync($log);

        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    // ---------------------------------------------------------------------------
    // Test: no occupant fields — insertContact and updateContact never called
    // ---------------------------------------------------------------------------

    public function test_no_occupant_fields_skips_contact_sync(): void
    {
        $insuredId = 'insured-uuid-111';

        $log = $this->makeLog();

        $payloadWithoutOccupant = [
            'Id'   => '11-18',
            'Form' => ['Id' => '11', 'Name' => 'Test Form', 'InternalName' => 'test'],
            'Entry' => [
                'Role' => 'Public', 'Order' => 1, 'Action' => 'Submit',
                'Number' => 1, 'Status' => 'Submitted', 'Version' => 1,
                'AdminLink' => '', 'Timestamp' => now()->toIso8601String(),
                'DateCreated' => now()->toIso8601String(),
                'DateUpdated' => now()->toIso8601String(),
                'DateSubmitted' => now()->toIso8601String(),
            ],
            'NameOfInsured' => [
                'First' => 'John', 'Last' => 'Smith', 'Middle' => '',
                'Prefix' => '', 'Suffix' => '', 'FirstAndLast' => 'John Smith',
                'MiddleInitial' => '',
            ],
            // NameOfOccupant intentionally absent
        ];

        $this->webhookLogs->shouldReceive('create')->once()->andReturn($log);
        $this->webhookLogs->shouldReceive('saveDiscoveredFields')->once();
        $this->webhookLogs->shouldReceive('update')->once();

        $this->bindMocks(['_insuredDatabaseId' => $insuredId]);

        $this->nowcerts->shouldReceive('insertNote')->once()->andReturn([]);

        $this->nowcerts->shouldReceive('insertContact')->never();
        $this->nowcerts->shouldReceive('updateContact')->never();

        $response = $this->postJson('/webhook/cognito?form_id=11&event=entry.submitted', $payloadWithoutOccupant);

        $response->assertOk();
    }

    // ---------------------------------------------------------------------------
    // Test: insertContact throws — sync still completes as Synced (non-blocking)
    // ---------------------------------------------------------------------------

    public function test_occupant_contact_failure_is_non_blocking(): void
    {
        $insuredId = 'insured-uuid-111';

        $log = $this->makeLog();

        $this->webhookLogs->shouldReceive('create')->once()->andReturn($log);
        $this->webhookLogs->shouldReceive('saveDiscoveredFields')->once();

        // Overall sync must still be Synced despite the contact insert failing
        $this->webhookLogs
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (WebhookLog $l, array $d) => ($d['sync_status'] ?? null) === SyncStatus::Synced);

        $this->bindMocks(['_insuredDatabaseId' => $insuredId]);

        $this->nowcerts
            ->shouldReceive('insertContact')
            ->once()
            ->andThrow(new \RuntimeException('NowCerts API timeout'));

        $this->nowcerts->shouldReceive('insertNote')->once()->andReturn([]);

        $response = $this->postJson('/webhook/cognito?form_id=11&event=entry.submitted', $this->occupantPayload());

        $response->assertOk()->assertJson(['ok' => true]);
    }

    // ---------------------------------------------------------------------------
    // Test: DateOfBirthOccupant is normalized to MM/DD/YYYY before sending
    // ---------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('dobFormatProvider')]
    public function test_occupant_date_of_birth_is_normalized_to_mm_dd_yyyy(string $rawDob, string $expectedFormatted): void
    {
        $insuredId = 'insured-uuid-111';

        $log = $this->makeLog();

        $this->webhookLogs->shouldReceive('create')->once()->andReturn($log);
        $this->webhookLogs->shouldReceive('saveDiscoveredFields')->once();
        $this->webhookLogs->shouldReceive('update')->once();

        $this->bindMocks(['_insuredDatabaseId' => $insuredId]);

        $this->nowcerts
            ->shouldReceive('insertContact')
            ->once()
            ->with($insuredId, Mockery::on(fn (array $d) =>
                $d['first_name'] === 'Jane'
                && $d['last_name'] === 'Doe'
                && $d['birthday']  === $expectedFormatted   // always MM/DD/YYYY
            ))
            ->andReturn(['data' => ['database_id' => 'contact-uuid-001']]);

        $this->nowcerts->shouldReceive('insertNote')->once()->andReturn([]);

        $response = $this->postJson(
            '/webhook/cognito?form_id=11&event=entry.submitted',
            $this->occupantPayload('Jane', 'Doe', $rawDob)
        );

        $response->assertOk();
    }

    public static function dobFormatProvider(): array
    {
        return [
            'MM/DD/YYYY (already correct)'  => ['04/28/1993', '04/28/1993'],
            'YYYY-MM-DD (ISO 8601)'         => ['1993-04-28', '04/28/1993'],
            'MM-DD-YYYY'                    => ['04-28-1993', '04/28/1993'],
            'single-digit month and day'    => ['4/8/1993',   '04/08/1993'],
        ];
    }
}
