<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Slices N independently-ordered query-builder "sequences" into one flat,
 * fixed-size page window — sequence order is fill priority (earlier
 * sequences fill first), no merge-sort needed since sequences never
 * interleave within a page. E.g. folders-then-files, or
 * groups-then-folders-then-files.
 */
class ConcatenatedPagination
{
    /**
     * @param  array<string, Builder<Model>>  $sequences  ordered name => query builder; order = fill priority
     * @param  array<string, mixed>  $paginatorOptions  passed straight to LengthAwarePaginator (path/query)
     * @return array{items: array<string, Collection<int, Model>>, paginator: LengthAwarePaginatorContract<int, Model>}
     */
    public static function slice(array $sequences, int $page, int $perPage, array $paginatorOptions = []): array
    {
        $totals = [];
        foreach ($sequences as $key => $query) {
            $totals[$key] = $query->count();
        }
        $totalItems = array_sum($totals);

        $remainingOffset = ($page - 1) * $perPage;
        $remainingLimit = $perPage;
        $items = [];

        foreach ($sequences as $key => $query) {
            $total = $totals[$key];

            if ($remainingLimit <= 0 || $remainingOffset >= $total) {
                $items[$key] = collect();
                $remainingOffset = max(0, $remainingOffset - $total);

                continue;
            }

            $limit = min($remainingLimit, $total - $remainingOffset);
            $items[$key] = $query->skip($remainingOffset)->take($limit)->get();
            $remainingLimit -= $limit;
            $remainingOffset = 0;
        }

        $paginator = new LengthAwarePaginator([], $totalItems, $perPage, $page, $paginatorOptions);

        return ['items' => $items, 'paginator' => $paginator];
    }
}
