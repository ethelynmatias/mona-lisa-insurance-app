<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CognitoWebhookController extends Controller
{
    /**
     * Receive an incoming Cognito Forms webhook.
     *
     * Cognito sends the entry payload as JSON in the request body.
     * The form ID and optional event type can be passed as query params:
     *   POST /webhook/cognito?form_id=1&event=entry.submitted
     */
    public function receive(Request $request): JsonResponse
    {
        $payload   = $request->all();
        $formId    = $request->query('form_id') ?? ($payload['FormId'] ?? ($payload['form_id'] ?? 'unknown'));
        $formName  = $request->query('form_name') ?? ($payload['FormName'] ?? ($payload['form_name'] ?? null));
        $eventType = $request->query('event') ?? ($payload['EventType'] ?? 'entry.submitted');
        $entryId   = $payload['Id'] ?? ($payload['EntryId'] ?? ($payload['entry_id'] ?? null));

        WebhookLog::create([
            'form_id'    => $formId,
            'form_name'  => $formName,
            'event_type' => $eventType,
            'entry_id'   => $entryId,
            'status'     => 'received',
            'payload'    => $payload ?: null,
        ]);

        return response()->json(['ok' => true]);
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
