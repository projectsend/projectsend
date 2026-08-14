<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Models\User;
use App\Modules\Api\Models\ApiRequestLog;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The single place that decides *whose* API usage a viewer may see.
 *
 * A token is a personal credential. Without a boundary, an API dashboard
 * would show every staff member which integrations their colleagues run,
 * how often, and against what — a new disclosure that no existing screen
 * makes. So the rule is: your own tokens always, everyone's only with
 * `view_actions_log`, which is already the permission that grants a
 * whole-installation view of who did what.
 *
 * Every query the dashboard runs goes through here. Keeping it in one
 * class rather than repeating a `when($installWide)` in each method is the
 * difference between a boundary and a convention.
 */
class ApiUsageScope
{
    /**
     * Whether this viewer may see beyond their own tokens.
     */
    public function mayViewInstallWide(User $viewer): bool
    {
        return $viewer->can('view_actions_log');
    }

    /**
     * Resolves what the caller asked for against what they may have, so a
     * controller cannot widen the scope by passing `true`.
     */
    public function resolve(User $viewer, bool $requested): bool
    {
        return $requested && $this->mayViewInstallWide($viewer);
    }

    /**
     * @return Builder<ApiRequestLog>
     */
    public function requests(User $viewer, bool $installWide): Builder
    {
        $query = ApiRequestLog::query();

        return $installWide ? $query : $query->ownedBy($viewer);
    }

    /**
     * @return Builder<PersonalAccessToken>
     */
    public function tokens(User $viewer, bool $installWide): Builder
    {
        $query = PersonalAccessToken::query();

        return $installWide
            ? $query
            : $query->where('tokenable_type', $viewer->getMorphClass())->where('tokenable_id', $viewer->id);
    }
}
