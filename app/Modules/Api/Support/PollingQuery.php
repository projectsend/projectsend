<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;

/**
 * The shape every list endpoint shares, so an integration learns it once.
 *
 * Two modes, chosen by whether the caller passed `updated_since`:
 *
 *  - **Polling.** Ordered by (updated_at, id) *ascending* and filtered to
 *    rows touched at or after the given time. That ordering is what makes
 *    a repeated poll safe: new and edited rows always land at the end of
 *    the walk, so paging forward with a cursor visits every row exactly
 *    once. Newest-first would insert new rows at the front and silently
 *    shift everything the caller had not read yet.
 *  - **Browsing.** Newest-first, for a human looking at a list.
 *
 * `updated_since` is inclusive on purpose. A caller is told to poll with
 * the highest `updated_at` it has seen, and two rows can share a
 * timestamp to the second; excluding the boundary would drop the second
 * one forever. The cost is re-seeing the boundary row, which a client
 * de-duplicates by id — the safe direction of the trade.
 *
 * Some tables have no `updated_at` because their rows are never edited —
 * the activity log is one. They pass their own column instead. The
 * *parameter* stays `updated_since` for every endpoint even so: the
 * shape being learned once is worth more than a second name that would
 * behave identically, since on an append-only table the two timestamps
 * are the same thing.
 *
 * Known limitation, documented rather than papered over: polling cannot
 * observe deletions. A soft-deleted row simply stops appearing. Webhooks
 * are the fix, and are deliberately a later phase.
 */
class PollingQuery
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  string  $column  the timestamp to walk, for a table whose
     *                          rows are appended rather than edited
     * @return CursorPaginator<int, TModel>
     */
    public function paginate(Request $request, Builder $query, string $table, string $column = 'updated_at'): CursorPaginator
    {
        $since = $request->query('updated_since');

        if (is_string($since) && $since !== '') {
            // Parsed, never passed through as a string. Callers send proper
            // ISO 8601 ("2026-08-06T05:00:00+02:00"), and the database will
            // not compare that against a datetime column — MySQL fails to
            // cast the `T` and the offset and silently matches nothing, so
            // a polling client would see an empty result forever instead of
            // an error. Carbon also normalises the offset into the app's
            // timezone, so a caller in any timezone gets the same rows.
            $query->where("{$table}.{$column}", '>=', Carbon::parse($since)->timezone(config('app.timezone')))
                ->orderBy("{$table}.{$column}")
                ->orderBy("{$table}.id");
        } else {
            $query->orderByDesc("{$table}.{$column}")
                ->orderByDesc("{$table}.id");
        }

        return $query->cursorPaginate($this->perPage($request))->withQueryString();
    }

    /**
     * The cap exists so one caller cannot turn a list endpoint into a
     * full-table export in a single request.
     */
    public function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page', (string) config('api.pagination.per_page'));
        $max = (int) config('api.pagination.max_per_page');

        return max(1, min($requested, $max));
    }

    /**
     * Validation rules a controller merges into its own, so `updated_since`
     * is rejected consistently rather than silently ignored when malformed
     * — a caller polling with a bad timestamp would otherwise re-read the
     * whole table on every tick and never notice.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('api.pagination.max_per_page')],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
