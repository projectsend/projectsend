<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use App\Models\User;
use App\Modules\Audit\ActivityLog;
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
     * @param  list<int>  $ids
     * @return array<int, string|null>
     */
    public static function lastSignIns(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = ActivityLog::query()
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
