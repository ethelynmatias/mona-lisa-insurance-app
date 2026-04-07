<?php

namespace App\Http\Controllers;

use App\Models\FormFieldMapping;
use App\Models\WebhookLog;
use App\Services\CognitoFormsService;
use App\Services\NowCertsFieldMapper;
use App\Services\NowCertsService;
use App\Traits\PaginatesArray;
use App\Http\Requests\SaveMappingsRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CognitoController extends Controller
{
    use PaginatesArray;

    public function __construct(
    private readonly CognitoFormsService $cognito,
    private readonly NowCertsService     $nowcerts,
) {}

    /**
     * List all forms.
     */
    public function index(Request $request): Response
    {
        $forms = [];
        $error = null;

        try {
            $forms = $this->cognito->getForms();
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        $paginated = $this->paginateArray($forms, $request, sortableFields: ['Name', 'Id']);

        $webhooks = WebhookLog::orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'form_id', 'form_name', 'event_type', 'entry_id', 'status', 'created_at'])
            ->toArray();

        return Inertia::render('Dashboard', [
            'forms'          => $paginated['items'],
            'search'         => $paginated['search'],
            'sort'           => $paginated['sort'],
            'direction'      => $paginated['direction'],
            'pagination'     => $paginated['pagination'],
            'perPageOptions' => $this->perPageOptions,
            'webhooks'       => $webhooks,
            'error'          => $error,
        ]);
    }

    /**
     * Show form details.
     */
    public function show(Request $request, string $formId): Response
    {
        $form   = null;
        $fields = [];
        $error  = null;

        try {
            $forms = $this->cognito->getForms();
            $form  = collect($forms)->firstWhere('Id', $formId);

            if (! $form) {
                $error = "Form '{$formId}' not found.";
            } else {
                $fields = $this->cognito->getFormFields($formId);
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        $availableFields      = [];
        $availableFieldsError = null;
        $lookup               = [];

        try {
            $availableFields = $this->nowcerts->getAvailableFields();

            // Build lookup: DB-saved mappings + auto-suggestions for unmapped fields
            $mapper      = new NowCertsFieldMapper($formId, $this->nowcerts);
            $lookup      = $mapper->getLookup();
            $suggestions = $mapper->getSuggestions($fields);

            // Merge suggestions only for fields not already saved
            foreach ($suggestions as $cognitoField => $mapping) {
                $lookup[$cognitoField] ??= $mapping;
            }
        } catch (\Throwable $e) {
            $availableFieldsError = $e->getMessage();
        }

        $webhooks = WebhookLog::where('form_id', $formId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'form_id', 'form_name', 'event_type', 'entry_id', 'status', 'created_at'])
            ->toArray();

        return Inertia::render('Cognito/FormDetails', [
            'form'                 => $form,
            'fields'               => $fields,
            'mappingLookup'        => $lookup,
            'availableFields'      => $availableFields,
            'availableFieldsError' => $availableFieldsError,
            'webhooks'             => $webhooks,
            'error'                => $error,
        ]);
    }

    /**
     * Save field mappings for a form.
     */
    public function saveMappings(SaveMappingsRequest $request, string $formId): RedirectResponse
    {
        foreach ($request->validated()['mappings'] as $mapping) {
            FormFieldMapping::updateOrCreate(
                [
                    'form_id'       => $formId,
                    'cognito_field' => $mapping['cognito_field'],
                ],
                [
                    'nowcerts_entity' => $mapping['nowcerts_entity'] ?? null,
                    'nowcerts_field'  => $mapping['nowcerts_field']  ?? null,
                ]
            );
        }

        return back()->with('success', 'Mappings saved successfully.');
    }

    protected function matchesSearch(mixed $item, string $search): bool
    {
        return str_contains(strtolower($item['Name'] ?? ''), strtolower($search));
    }
}
