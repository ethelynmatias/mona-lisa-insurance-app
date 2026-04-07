<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CognitoFormsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CognitoFormsController extends Controller
{
    public function __construct(private readonly CognitoFormsService $cognito) {}

    // ──────────────────────────────────────────
    //  Forms
    // ──────────────────────────────────────────

    public function index(): JsonResponse
    {
        return $this->respond(fn () => $this->cognito->getForms());
    }

    public function show(string $formId): JsonResponse
    {
        return $this->respond(fn () => $this->cognito->getForm($formId));
    }

    public function fields(string $formId): JsonResponse
    {
        return $this->respond(fn () => $this->cognito->getFormFields($formId));
    }

    // ──────────────────────────────────────────
    //  Entries
    // ──────────────────────────────────────────

    public function entries(Request $request, string $formId): JsonResponse
    {
        $params = $request->only(['filter', 'sort', 'page', 'pageSize']);

        return $this->respond(fn () => $this->cognito->getEntries($formId, $params));
    }

    public function entry(string $formId, string $entryId): JsonResponse
    {
        return $this->respond(fn () => $this->cognito->getEntry($formId, $entryId));
    }

    public function createEntry(Request $request, string $formId): JsonResponse
    {
        $request->validate([
            'data' => ['required', 'array'],
        ]);

        return $this->respond(fn () => $this->cognito->createEntry($formId, $request->input('data')), 201);
    }

    public function updateEntry(Request $request, string $formId, string $entryId): JsonResponse
    {
        $request->validate([
            'data' => ['required', 'array'],
        ]);

        return $this->respond(fn () => $this->cognito->updateEntry($formId, $entryId, $request->input('data')));
    }

    public function deleteEntry(string $formId, string $entryId): JsonResponse
    {
        return $this->respond(fn () => $this->cognito->deleteEntry($formId, $entryId));
    }

    // ──────────────────────────────────────────
    //  Internal
    // ──────────────────────────────────────────

    private function respond(callable $action, int $successStatus = 200): JsonResponse
    {
        try {
            $data = $action();

            return response()->json(['data' => $data], $successStatus);
        } catch (RuntimeException $e) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 500;

            return response()->json(['error' => $e->getMessage()], $status);
        }
    }
}
