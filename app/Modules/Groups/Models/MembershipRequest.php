<?php

declare(strict_types=1);

namespace App\Modules\Groups\Models;

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A client's request to join a group. Pending requests await staff
 * approval; denied ones persist to enforce the re-request cooldown.
 *
 * @property int $id
 * @property int $group_id
 * @property int $user_id
 * @property string $status
 * @property Carbon|null $denied_at
 */
class MembershipRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DENIED = 'denied';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'denied_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<MembershipRequest>  $query
     * @return Builder<MembershipRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * The requests a staff member may actually act on.
     *
     * MembershipRequestsController guards approve() and deny() with
     * StaffLibraryScope::allowsGroupMembership, because joining a client
     * to a group decides what that client -- and any staff member
     * holding them -- can reach. This is the listing half of the same
     * rule, and both the queue and the sidebar badge read it, so the
     * number and the screen behind it cannot drift apart. That is why it
     * lives here rather than in either caller, the same reasoning
     * VisibleCommentScope::pendingTotal() gives for owning the comment
     * badge instead of leaving the middleware to count for itself.
     *
     * Narrowed on the client only. Whether the *group* is reachable is
     * the other half of allowsGroupMembership, and it depends on what is
     * shared with that group -- not a question to ask row by row in a
     * listing. So a scoped viewer may still be shown a request they
     * would be refused on; it will be one of their own clients asking to
     * join a group out of their reach, rather than a client they were
     * never meant to hear about. The names are the part that leaks.
     *
     * @param  Builder<MembershipRequest>  $query
     * @return Builder<MembershipRequest>
     */
    public function scopeApprovableBy(Builder $query, User $viewer): Builder
    {
        $clientIds = app(StaffLibraryScope::class)->assignableClientIds($viewer);

        return $clientIds === null ? $query : $query->whereIn('user_id', $clientIds);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
