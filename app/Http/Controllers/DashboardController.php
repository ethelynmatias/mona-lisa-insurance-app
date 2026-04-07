<?php

namespace App\Http\Controllers;

use App\Services\CognitoFormsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class DashboardController extends Controller
{
    private const PER_PAGE = 10;

    public function __invoke(Request $request): Response
    {
        $search  = $request->string('search')->trim()->toString();
        $filter  = $request->string('filter', 'all')->toString();
        $page    = max(1, (int) $request->get('page', 1));

        $forms      = [];
        $error      = null;
        $total      = 0;
        $totalPages = 0;

        try {
            $cognito   = app(CognitoFormsService::class);
            $allForms  = $cognito->getForms();

            // Search by name
            if ($search !== '') {
                $allForms = array_values(array_filter(
                    $allForms,
                    fn ($f) => str_contains(strtolower($f['Name'] ?? ''), strtolower($search))
                ));
            }

            // Filter by availability
            if ($filter === 'active') {
                $allForms = array_values(array_filter($allForms, fn ($f) => ($f['IsAvailable'] ?? false) === true));
            } elseif ($filter === 'inactive') {
                $allForms = array_values(array_filter($allForms, fn ($f) => ($f['IsAvailable'] ?? false) === false));
            }

            $total      = count($allForms);
            $totalPages = (int) ceil($total / self::PER_PAGE);
            $page       = min($page, max(1, $totalPages));
            $forms      = array_slice($allForms, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Dashboard', [
            'forms'      => $forms,
            'search'     => $search,
            'filter'     => $filter,
            'pagination' => [
                'currentPage' => $page,
                'perPage'     => self::PER_PAGE,
                'total'       => $total,
                'totalPages'  => $totalPages,
            ],
            'error' => $error,
        ]);
    }
}
