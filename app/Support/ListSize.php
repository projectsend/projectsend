<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Refuses an over-long list before any rule looks at its items.
 *
 * A `max:` on a list is not enough on its own. Laravel still runs every
 * `list.*` rule on every item after the list's own `max` has failed, so a
 * request with a hundred thousand ids did a hundred thousand `exists`
 * queries, or a `distinct` check that compares every item with every other,
 * before the "too many" error came back. Any signed-in account could hold
 * a worker for half a minute that way.
 *
 * Call this first, then validate as usual: by then no list is longer than
 * its limit.
 */
final class ListSize
{
    /** Most categories one request may name. */
    public const CATEGORIES = 500;

    /**
     * @param  array<string, int>  $limits  field => the most items it may hold
     */
    public static function enforce(Request $request, array $limits): void
    {
        // `sometimes` and `nullable`: whether the field is required, and
        // what a missing or null one means, stay the main rules' question.
        $request->validate(array_map(
            fn (int $max): array => ['sometimes', 'nullable', 'array', 'max:'.$max],
            $limits,
        ));
    }
}
