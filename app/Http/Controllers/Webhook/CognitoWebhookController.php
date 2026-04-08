<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Services\NowCertsFieldMapper;
use App\Services\NowCertsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CognitoWebhookController extends Controller
{
    public function __construct(private readonly NowCertsService $nowcerts) {}

    /**
     * Receive an incoming Cognito Forms webhook, log it, then push to NowCerts.
     *
     * POST /webhook/cognito?form_id=1&event=entry.submitted
     */
    public function receive(Request $request): JsonResponse
    {
        $payload   = $request->all();
        $formId    = $request->query('form_id') ?? ($payload['FormId']    ?? ($payload['form_id']    ?? 'unknown'));
        $formName  = $request->query('form_name') ?? ($payload['FormName'] ?? ($payload['form_name'] ?? null));
        $eventType = $request->query('event')    ?? ($payload['EventType'] ?? 'entry.submitted');
        $entryId   = $payload['Id'] ?? ($payload['EntryId'] ?? ($payload['entry_id'] ?? null));

        // Log the raw event immediately
        $log = WebhookLog::create([
            'form_id'    => $formId,
            'form_name'  => $formName,
            'event_type' => $eventType,
            'entry_id'   => $entryId,
            'status'     => 'received',
            'payload'    => $payload ?: null,
            'sync_status'=> 'pending',
        ]);

        // Deletions: nothing to push, mark skipped
        if ($eventType === 'entry.deleted') {
            $log->update(['sync_status' => 'skipped']);
            return response()->json(['ok' => true]);
        }

        // Push to NowCerts
        $this->syncToNowCerts($log, $formId, $payload);

        return response()->json(['ok' => true]);
    }

    /**
     * Map the Cognito payload to NowCerts entities and call the API.
     * Updates the log record with the outcome.
     */
    private function syncToNowCerts(WebhookLog $log, string $formId, array $entry): void
    {
        try {
            // Flatten nested objects (e.g. NameOfInsured.First, LocationAddress.City)
            // so DB-saved mappings like "NameOfInsured.First" → "Insured.FirstName" resolve correctly.
            $entry  = NowCertsFieldMapper::flattenEntry($entry);
            $mapper = new NowCertsFieldMapper($formId, $this->nowcerts);

            $syncedEntities = [];
            $errors         = [];

            // ── Insured ──────────────────────────────
            $insuredData = $mapper->mapInsured($entry);
            if (! empty($insuredData)) {
                try {
                    $this->nowcerts->upsertInsured($insuredData);
                    $syncedEntities[] = 'Insured';
                } catch (Throwable $e) {
                    $errors[] = 'Insured: ' . $e->getMessage();
                }
            }

            // ── Policy ───────────────────────────────
            $policyData = $mapper->mapPolicy($entry);
            if (! empty($policyData)) {
                try {
                    $this->nowcerts->upsertPolicy($policyData);
                    $syncedEntities[] = 'Policy';
                } catch (Throwable $e) {
                    $errors[] = 'Policy: ' . $e->getMessage();
                }
            }

            // ── Driver ───────────────────────────────
            $driverData = $mapper->mapDriver($entry);
            if (! empty($driverData)) {
                try {
                    $this->nowcerts->insertDriver($driverData);
                    $syncedEntities[] = 'Driver';
                } catch (Throwable $e) {
                    $errors[] = 'Driver: ' . $e->getMessage();
                }
            }

            // ── Vehicle ──────────────────────────────
            $vehicleData = $mapper->mapVehicle($entry);
            if (! empty($vehicleData)) {
                try {
                    $this->nowcerts->insertVehicle($vehicleData);
                    $syncedEntities[] = 'Vehicle';
                } catch (Throwable $e) {
                    $errors[] = 'Vehicle: ' . $e->getMessage();
                }
            }

            // No mappings configured for this form at all
            if (empty($syncedEntities) && empty($errors)) {
                $log->update(['sync_status' => 'skipped']);
                return;
            }

            $log->update([
                'sync_status'     => empty($errors) ? 'synced' : 'failed',
                'sync_error'      => empty($errors) ? null : implode('; ', $errors),
                'synced_entities' => $syncedEntities ?: null,
                'synced_at'       => now(),
            ]);
        } catch (Throwable $e) {
            $log->update([
                'sync_status' => 'failed',
                'sync_error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Rerun the NowCerts sync for a specific webhook log entry.
     * Only allowed when sync_status is pending or failed.
     */
    public function rerunSync(WebhookLog $log): RedirectResponse
    {
        if (! in_array($log->sync_status, ['pending', 'failed'])) {
            return back()->with('error', 'Only pending or failed events can be rerun.');
        }

        if ($log->event_type === 'entry.deleted') {
            return back()->with('error', 'Delete events cannot be synced to NowCerts.');
        }

        if (empty($log->payload)) {
            return back()->with('error', 'No payload stored for this event — cannot rerun.');
        }

        // Reset status before retrying
        $log->update([
            'sync_status'     => 'pending',
            'sync_error'      => null,
            'synced_entities' => null,
            'synced_at'       => null,
        ]);

        $this->syncToNowCerts($log, $log->form_id, $log->payload);

        $log->refresh();

        return match ($log->sync_status) {
            'synced'  => back()->with('success', 'Sync completed successfully. Entities pushed: ' . implode(', ', $log->synced_entities ?? [])),
            'skipped' => back()->with('success', 'No field mappings configured for this form — sync skipped.'),
            default   => back()->with('error', 'Sync failed: ' . ($log->sync_error ?? 'Unknown error.')),
        };
    }

    /**
     * Clear all webhook history.
     */
    public function clearAll(): RedirectResponse
    {
        WebhookLog::truncate();

        return back()->with('success', 'Webhook history cleared.');
    }

    /**
     * Clear webhook history for a specific form.
     */
    public function clearByForm(string $formId): RedirectResponse
    {
        WebhookLog::where('form_id', $formId)->delete();

        return back()->with('success', 'Webhook history cleared for this form.');
    }
}
