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
     * Show a form's entries.
     */
    public function show(Request $request, string $formId): Response
    {
        $form    = null;
        $entries = [];
        $error   = null;

        try {
            $form    = $this->cognito->getForm($formId);
            $entries = $this->cognito->getEntries($formId);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        $paginated = $this->paginateArray($entries, $request);

        return Inertia::render('Cognito/FormEntries', [
            'form'       => $form,
            'entries'    => $paginated['items'],
            'search'     => $paginated['search'],
            'pagination' => $paginated['pagination'],
            'error'      => $error,
        ]);
    }

    protected function matchesSearch(mixed $item, string $search): bool
    {
        if (! is_array($item)) {
            return false;
        }

        foreach ($item as $key => $value) {
            if (str_starts_with((string) $key, '$') || str_starts_with((string) $key, '_')) {
                continue;
            }
            if (is_string($value) && str_contains(strtolower($value), strtolower($search))) {
                return true;
            }
        }

        return false;
    }
}
