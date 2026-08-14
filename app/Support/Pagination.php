<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Shared shape for the `pagination` Inertia prop consumed by the frontend
 * <Pagination> component. Keeps every list controller emitting the same keys.
 */
class Pagination
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{page: int, last_page: int, prev: ?string, next: ?string, total: int}
     */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * Whether a requested page number lands past the end of the results.
     *
     * A stale or guessed ?page= would otherwise render an empty list rather
     * than the real one, so every paginated listing redirects back to the
     * last real page instead.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function isPastLastPage(LengthAwarePaginator $paginator, int $page): bool
    {
        return $page > $paginator->lastPage();
    }

    /**
     * The page number that redirect should carry, or null on a single-page
     * result so the URL stays clean.
     *
     * Small, but it was written out at five call sites, and each of them
     * decides what a user's URL looks like after the redirect — the sort of
     * expression that only has to be edited in four places to start
     * disagreeing with itself.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function redirectPage(LengthAwarePaginator $paginator): ?int
    {
        return $paginator->lastPage() > 1 ? $paginator->lastPage() : null;
    }
}
