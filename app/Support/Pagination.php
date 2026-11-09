<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Shared shape for the `pagination` Inertia prop consumed by the frontend
 * <Pagination> component. Keeps every list controller emitting the same keys.
 */
class Pagination
{
    /**
     * The page sizes a person may pick on a list that offers the choice.
     * The first is the default.
     */
    public const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    /**
     * Pass true for a list whose controller reads perPage(): the pager then
     * offers a page-size select. Lists with a fixed page size leave it out,
     * so the select never appears where it would do nothing.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{page: int, last_page: int, prev: ?string, next: ?string, total: int, per_page?: int, per_page_options?: list<int>}
     */
    public static function meta(LengthAwarePaginator $paginator, bool $choosablePerPage = false): array
    {
        $meta = [
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
            'total' => $paginator->total(),
        ];

        if ($choosablePerPage) {
            $meta['per_page'] = $paginator->perPage();
            $meta['per_page_options'] = self::PER_PAGE_OPTIONS;
        }

        return $meta;
    }

    /**
     * The page size this request asked for. Anything that isn't one of the
     * offered sizes gets the default, rather than an error: it only ever
     * arrives through a URL, and a hand-edited `per_page=100000` must not
     * load the whole library into one response.
     */
    public static function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page');

        return in_array($requested, self::PER_PAGE_OPTIONS, true) ? $requested : self::PER_PAGE_OPTIONS[0];
    }

    /**
     * The `per_page` a redirect should carry: null for the default, so the
     * URL stays clean.
     */
    public static function perPageParam(int $perPage): ?int
    {
        return $perPage !== self::PER_PAGE_OPTIONS[0] ? $perPage : null;
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
