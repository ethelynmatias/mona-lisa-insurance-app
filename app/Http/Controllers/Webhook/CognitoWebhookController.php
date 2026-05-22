<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Repositories\Contracts\WebhookLogRepositoryInterface;
use App\Services\CognitoSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CognitoWebhookController extends Controller
{
    public function __construct(
        private readonly CognitoSyncService            $syncService,
        private readonly WebhookLogRepositoryInterface $webhookLogs,
    ) {}

    public function receive(Request $request): JsonResponse
    {
        $log = $this->syncService->receiveWebhook($request->all(), $request->query());

        $this->syncService->process($log);

        return response()->json(['ok' => true]);
    }

    public function rerunSync(WebhookLog $log): RedirectResponse
    {
        return $this->syncService->rerun($log);
    }

    public function clearAll(): RedirectResponse
    {
        $this->webhookLogs->truncateAll();

        return back()->with('success', 'Webhook history cleared.');
    }

    public function clearByForm(string $formId): RedirectResponse
    {
        $this->webhookLogs->deleteByForm($formId);

        return back()->with('success', 'Webhook history cleared for this form.');
    }

    public function deleteEntry(WebhookLog $log): RedirectResponse
    {
        $entryId  = $log->entry_id;
        $formName = $log->form_name ?? $log->form_id;

        $log->delete();

        return back()->with('success', "Webhook entry deleted (Entry ID: {$entryId}, Form: {$formName}).");
    }
}
