<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait PaginatesArray
{
    /**
     * Search, sort, and paginate an array.
     *
     * @param  array<int, mixed>  $items
     * @param  string[]           $sortableFields  Keys allowed for sorting
     * @return array{items: array, pagination: array, search: string, sort: string, direction: string}
     */
    protected function paginateArray(array $items, Request $request, int $perPage = 10, array $sortableFields = []): array
    {
        $search    = $request->string('search')->trim()->toString();
        $sort      = $request->string('sort')->toString();
        $direction = $request->string('direction', 'asc')->lower()->toString();
        $direction = in_array($direction, ['asc', 'desc']) ? $direction : 'asc';
        $page      = max(1, (int) $request->get('page', 1));

        // Search
        if ($search !== '') {
            $items = array_values(array_filter(
                $items,
                fn ($item) => $this->matchesSearch($item, $search)
            ));
        }

        // Sort
        if ($sort !== '' && (empty($sortableFields) || in_array($sort, $sortableFields))) {
            usort($items, function ($a, $b) use ($sort, $direction) {
                $aVal = strtolower((string) ($a[$sort] ?? ''));
                $bVal = strtolower((string) ($b[$sort] ?? ''));
                $cmp  = strcmp($aVal, $bVal);
                return $direction === 'desc' ? -$cmp : $cmp;
            });
        }

        // Paginate
        $total      = count($items);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        $page       = min($page, max(1, $totalPages));
        $sliced     = array_slice($items, ($page - 1) * $perPage, $perPage);

        return [
            'items'      => $sliced,
            'search'     => $search,
            'sort'       => $sort,
            'direction'  => $direction,
            'pagination' => [
                'currentPage' => $page,
                'perPage'     => $perPage,
                'total'       => $total,
                'totalPages'  => $totalPages,
            ],
        ];
    }

    /**
     * Override to customise search matching.
     */
    protected function matchesSearch(mixed $item, string $search): bool
    {
        if (is_array($item)) {
            foreach ($item as $value) {
                if (is_string($value) && str_contains(strtolower($value), strtolower($search))) {
                    return true;
                }
            }
            return false;
        }

        return str_contains(strtolower((string) $item), strtolower($search));
    }
}
