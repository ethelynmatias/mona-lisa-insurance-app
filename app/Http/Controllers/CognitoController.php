<?php

namespace App\Http\Controllers;

use App\Models\FormFieldMapping;
use App\Services\CognitoFormsService;
use App\Services\NowCertsFieldMapper;
use App\Traits\PaginatesArray;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CognitoController extends Controller
{
    use PaginatesArray;

    public function __construct(private readonly CognitoFormsService $cognito) {}

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

        return Inertia::render('Dashboard', [
            'forms'      => $paginated['items'],
            'search'     => $paginated['search'],
            'sort'       => $paginated['sort'],
            'direction'  => $paginated['direction'],
            'pagination' => $paginated['pagination'],
            'error'      => $error,
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

        // Build mapping lookup: DB-saved records override the defaults
        $mapper  = new NowCertsFieldMapper();
        $lookup  = $mapper->getLookup();

        $saved = FormFieldMapping::where('form_id', $formId)
            ->get()
            ->keyBy('cognito_field');

        foreach ($saved as $cognitoField => $record) {
            if ($record->nowcerts_entity && $record->nowcerts_field) {
                $lookup[$cognitoField] = [
                    'entity' => $record->nowcerts_entity,
                    'field'  => $record->nowcerts_field,
                ];
            } else {
                // Explicitly unmapped in DB — remove default
                unset($lookup[$cognitoField]);
            }
        }

        return Inertia::render('Cognito/FormDetails', [
            'form'            => $form,
            'fields'          => $fields,
            'mappingLookup'   => $lookup,
            'availableFields' => NowCertsFieldMapper::availableFields(),
            'error'           => $error,
        ]);
    }

    /**
     * Save field mappings for a form.
     */
    public function saveMappings(Request $request, string $formId): RedirectResponse
    {
        $request->validate([
            'mappings'                  => ['required', 'array'],
            'mappings.*.cognito_field'  => ['required', 'string'],
            'mappings.*.nowcerts_entity'=> ['nullable', 'string'],
            'mappings.*.nowcerts_field' => ['nullable', 'string'],
        ]);

        foreach ($request->input('mappings') as $mapping) {
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
