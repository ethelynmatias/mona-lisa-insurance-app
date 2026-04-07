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
        $status = $request->string('status', 'all')->toString();
        $forms  = [];
        $error  = null;

        try {
            $forms = app(CognitoFormsService::class)->getForms();

            if ($status === 'active') {
                $forms = array_values(array_filter($forms, fn ($f) => ($f['IsAvailable'] ?? false) === true));
            } elseif ($status === 'inactive') {
                $forms = array_values(array_filter($forms, fn ($f) => ($f['IsAvailable'] ?? false) === false));
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        $paginated = $this->paginateArray($forms, $request);

        return Inertia::render('Dashboard', [
            'forms'      => $paginated['items'],
            'search'     => $paginated['search'],
            'status'     => $status,
            'pagination' => $paginated['pagination'],
            'error'      => $error,
        ]);
    }

    protected function matchesSearch(mixed $item, string $search): bool
    {
        return str_contains(strtolower($item['Name'] ?? ''), strtolower($search));
    }
}
