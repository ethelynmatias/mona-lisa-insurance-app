<?php

namespace App\Http\Controllers;

use App\Services\CognitoFormsService;
use App\Traits\PaginatesArray;
use Illuminate\Http\Request;
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
        $form  = null;
        $error = null;

        $fields = [];

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

        return Inertia::render('Cognito/FormDetails', [
            'form'   => $form,
            'fields' => $fields,
            'error'  => $error,
        ]);
    }

    protected function matchesSearch(mixed $item, string $search): bool
    {
        return str_contains(strtolower($item['Name'] ?? ''), strtolower($search));
    }
}
