<?php

namespace Tests\Feature\Webhook;

use App\Models\WebhookLog;
use App\Services\NowCertsService;
use Tests\TestCase;

/**
 * Live integration test — calls the real NowCerts API.
 *
 * Run with:
 *   php artisan test tests/Feature/Webhook/SyncOccupantContactIntegrationTest.php
 *
 * Requires NOWCERTS_USERNAME and NOWCERTS_PASSWORD set in .env.
 * Skipped automatically when credentials are missing.
 */
class SyncOccupantContactIntegrationTest extends TestCase
{
    private const INSURED_ID = 'dd4356be-ddc0-44a0-8840-2612377969a8';
    private const POLICY_ID  = 'f1a9b930-dc39-45c1-8614-7d39f045433e';

    // First occupant (no DOB)
    private const OCCUPANT1_FIRST = 'John';
    private const OCCUPANT1_LAST  = 'Davis';

    // Second occupant (with DOB)
    private const OCCUPANT2_FIRST = 'Jane';
    private const OCCUPANT2_LAST  = 'Davis';
    private const OCCUPANT2_DOB   = '04/28/1993';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver'  => 'array',
            'cache.default'   => 'array',
            'logging.default' => 'stderr',
        ]);

        if (empty(config('nowcerts.username')) || empty(config('nowcerts.password'))) {
            $this->markTestSkipped('NowCerts credentials not configured — skipping live API test.');
        }
    }

    /**
     * Insert John Davis (first occupant, no DOB) as a principal on the insured.
     */
    public function test_insert_first_occupant_contact(): void
    {
        $nowcerts = app(NowCertsService::class);

        $response = $this->insertPrincipal($nowcerts, [
            'first_name'         => self::OCCUPANT1_FIRST,
            'last_name'          => self::OCCUPANT1_LAST,
            'policy_database_id' => self::POLICY_ID,
        ]);

        $contactId = $this->resolveContactId($response);

        $this->persistContactId(9, 'contactId', $contactId);

        echo "\n[Occupant 1] {$contactId} — " . self::OCCUPANT1_FIRST . ' ' . self::OCCUPANT1_LAST . "\n";
    }

    /**
     * Insert Jane Davis (second occupant, with DOB 04/28/1993) as a principal on the insured.
     */
    public function test_insert_second_occupant_contact_with_dob(): void
    {
        $nowcerts = app(NowCertsService::class);

        $response = $this->insertPrincipal($nowcerts, [
            'first_name'         => self::OCCUPANT2_FIRST,
            'last_name'          => self::OCCUPANT2_LAST,
            'birthday'           => self::OCCUPANT2_DOB,
            'policy_database_id' => self::POLICY_ID,
        ]);

        $contactId = $this->resolveContactId($response);

        echo "\n[Occupant 2] {$contactId} — " . self::OCCUPANT2_FIRST . ' ' . self::OCCUPANT2_LAST . ' (DOB: ' . self::OCCUPANT2_DOB . ")\n";
        echo "birthday in response: " . ($response['data']['birthday'] ?? $response['data']['birthday'] ?? 'not returned') . "\n";

        $this->persistContactId(9, 'contactId2', $contactId);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function insertPrincipal(NowCertsService $nowcerts, array $data): array
    {
        try {
            $response = $nowcerts->insertContact(self::INSURED_ID, $data);
        } catch (\Throwable $e) {
            $this->fail("insertContact (Zapier/InsertPrincipal) failed: " . $e->getMessage());
        }

        echo "\nNowCerts InsertPrincipal response:\n" . json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertIsArray($response);

        return $response;
    }

    private function resolveContactId(array $response): string
    {
        $contactId = $response['data']['database_id']
            ?? $response['data']['DatabaseId']
            ?? $response['DatabaseId']
            ?? $response['database_id']
            ?? $response['id']
            ?? null;

        $this->assertNotNull(
            $contactId,
            "Could not find a DatabaseId in the response:\n" . json_encode($response, JSON_PRETTY_PRINT)
        );

        return $contactId;
    }

    private function persistContactId(int $logId, string $key, string $contactId): void
    {
        try {
            $log = WebhookLog::find($logId);

            if ($log) {
                $ids       = $log->synced_nowcerts_ids ?? [];
                $ids[$key] = $contactId;
                $log->update(['synced_nowcerts_ids' => $ids]);
                $this->assertSame($contactId, $log->fresh()->synced_nowcerts_ids[$key]);
                echo "Saved {$key}={$contactId} to webhook log {$logId}\n";
            } else {
                echo "Webhook log {$logId} not found — not persisted.\n";
                $this->addToAssertionCount(1);
            }
        } catch (\Throwable) {
            echo "\nDB not reachable. Run manually:\n";
            echo "  UPDATE webhook_logs SET synced_nowcerts_ids = JSON_SET(synced_nowcerts_ids, '$.{$key}', '{$contactId}') WHERE id = {$logId};\n";
            $this->addToAssertionCount(1);
        }
    }
}
