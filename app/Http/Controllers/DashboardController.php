<?php

namespace App\Http\Controllers;

use App\Services\CognitoFormsService;
use App\Traits\PaginatesArray;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class DashboardController extends Controller
{
    use PaginatesArray;

    public function __invoke(Request $request): Response
    {
        $forms = [];
        $error = null;

        try {
            $forms = app(CognitoFormsService::class)->getForms();
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

    protected function matchesSearch(mixed $item, string $search): bool
    {
        return str_contains(strtolower($item['Name'] ?? ''), strtolower($search));
    }
}
