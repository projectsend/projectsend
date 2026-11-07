<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use App\Models\User;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogScope;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\FileAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Client accounts, as the app counts them: type `client`, and not a
 * request still waiting for approval — and, for a client-scoped staff
 * member, only the clients assigned to them, as StaffLibraryScope keeps
 * the clients screen. Found by id or by address the same way for every
 * tool that takes one, so a client off somebody's roster is not found
 * rather than refused.
 */
final class ClientAccountsQuery
{
    /**
     * @return Builder<User>
     */
    public static function clients(Account $account): Builder
    {
        return app(StaffLibraryScope::class)->clients($account->user)
            ->where('account_requested', false);
    }

    public static function byId(Account $account, int $id): ?User
    {
        return self::clients($account)->whereKey($id)->first();
    }

    public static function byEmail(Account $account, string $email): ?User
    {
        return self::clients($account)->where('email', strtolower(trim($email)))->first();
    }

    /**
     * When each client last signed in, from the activity log, in one
     * query for the page rather than one per row.
     *
     * A sign-in time comes from the activity log, so it is told only to
     * somebody who may read the log (`view_actions_log`), through the log
     * page's own scope. The web's client screens show no sign-in times at
     * all; without this, `manage_clients` alone was enough.
     *
     * @param  list<int>  $ids
     * @return array<int, string|null>
     */
    public static function lastSignIns(Account $account, array $ids): array
    {
        if ($ids === [] || ! $account->can('view_actions_log')) {
            return [];
        }

        $rows = app(ActivityLogScope::class)->apply(ActivityLog::query(), $account->user)
            ->where('action', 'auth.login')
            ->whereIn('actor_id', $ids)
            ->selectRaw('actor_id, max(created_at) as last_at')
            ->groupBy('actor_id')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            /** @var Model $row */
            $out[(int) $row->getAttribute('actor_id')] = Presenter::iso($row->getAttribute('last_at'));
        }

        return $out;
    }

    /**
     * How many files are assigned to each client on the page, one query.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    public static function sharedCounts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = FileAssignment::query()
            ->where('assignable_type', self::userMorph())
            ->whereIn('assignable_id', $ids)
            ->selectRaw('assignable_id, count(*) as total')
            ->groupBy('assignable_id')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            /** @var Model $row */
            $out[(int) $row->getAttribute('assignable_id')] = (int) $row->getAttribute('total');
        }

        return $out;
    }

    public static function userMorph(): string
    {
        return (new User)->getMorphClass();
    }
}
