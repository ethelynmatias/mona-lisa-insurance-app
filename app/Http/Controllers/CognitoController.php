<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveMappingsRequest;
use App\Services\CognitoFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CognitoController extends Controller
{
    public function __construct(
        private readonly CognitoFormService $formService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', $this->formService->getForms($request));
    }

    public function show(string $formId): Response
    {
        return Inertia::render('Cognito/FormDetails', $this->formService->getFormDetails($formId));
    }

    public function viewMappings(string $formId): Response
    {
        return Inertia::render('Cognito/SavedMappings', $this->formService->getFormMappings($formId));
    }

    public function saveMappings(SaveMappingsRequest $request, string $formId): RedirectResponse
    {
        $validated = $request->validated();

        $this->formService->saveMappings($formId, $validated['mappings'], $validated['upload_fields'] ?? []);

        return back()->with('success', 'Mappings saved successfully.');
    }
}
