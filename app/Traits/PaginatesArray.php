<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait PaginatesArray
{
    /**
     * Paginate an array and return data + pagination meta.
     *
     * @param  array<int, mixed>  $items
     * @return array{items: array, pagination: array, search: string}
     */
    protected function paginateArray(array $items, Request $request, int $perPage = 10): array
    {
        $search = $request->string('search')->trim()->toString();
        $page   = max(1, (int) $request->get('page', 1));

        if ($search !== '') {
            $items = array_values(array_filter(
                $items,
                fn ($item) => $this->matchesSearch($item, $search)
            ));
        }

        $total      = count($items);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        $page       = min($page, max(1, $totalPages));
        $sliced     = array_slice($items, ($page - 1) * $perPage, $perPage);

        return [
            'items'      => $sliced,
            'search'     => $search,
            'pagination' => [
                'currentPage' => $page,
                'perPage'     => $perPage,
                'total'       => $total,
                'totalPages'  => $totalPages,
            ],
        ];
    }

    /**
     * Override in your controller to customise search matching.
     * Default: searches all string values in the item.
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
