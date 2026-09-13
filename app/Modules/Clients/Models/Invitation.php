<?php

declare(strict_types=1);

namespace App\Modules\Clients\Models;

use App\Models\User;
use App\Modules\Groups\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A staff-sent invitation for a specific address to register a client
 * account, ahead of the public registration form. Redeeming one is
 * handled by ClientProvisioning, the same as any other self-provisioned
 * account — an invitation only settles who is allowed to reach the form
 * and with which address, not the account's own policy.
 *
 * **The token is the whole authorization**, the same as a file's share
 * link: the redemption route has nothing else to look it up by, so it is
 * stored the way CreateShareLink stores one — Str::random(40), plain,
 * queried directly — rather than hashed the way a password is.
 *
 * @property int $id
 * @property string|null $name
 * @property string $email
 * @property string $token
 * @property string $status
 * @property int $resends
 * @property int $storage_quota_mb
 * @property int|null $group_id
 * @property int|null $invited_by_id
 * @property Carbon $expires_at
 */
class Invitation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REDEEMED = 'redeemed';

    // Retired by a fresh invitation to the same address, issued either
    // because staff sent another one or because the invited person asked
    // for a new link — see issue(). Never redeemable, but kept rather than
    // deleted so the activity log's trail of who invited this address,
    // and when, stays intact.
    public const STATUS_SUPERSEDED = 'superseded';

    // Cancelled by staff before anybody used it — the wrong address, or a
    // decision taken back. Distinct from superseded because it is the only
    // one of the two that was somebody's intention: a revoked invitation is
    // never re-issued, where a superseded one was retired precisely so a
    // fresh link could take its place. Both are outside pending(), so the
    // redemption and resend doors refuse either without asking which.
    public const STATUS_REVOKED = 'revoked';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            // Read straight into provision()'s `int $storageQuotaMb` when
            // the invitation is redeemed -- see the same cast on User.
            'storage_quota_mb' => 'integer',
        ];
    }

    /**
     * A fresh invitation for $email, retiring any other still-pending one
     * for the same address first — one live token per address at a time,
     * whether this is staff sending a second invite or the invited person
     * asking for a new link after the first expired.
     *
     * @param  int  $resends  How many self-resends this link already stands
     *                        on. Staff leave it at zero; the resend door
     *                        passes the previous invitation's count plus
     *                        one, which is what makes the limit apply to
     *                        the chain rather than to a single row.
     */
    public static function issue(string $email, ?string $name, ?Group $group, ?User $invitedBy, Carbon $expiresAt, int $storageQuotaMb = 0, int $resends = 0): self
    {
        self::query()->pending()->where('email', $email)->update(['status' => self::STATUS_SUPERSEDED]);

        return self::query()->create([
            'name' => $name,
            'email' => $email,
            'token' => Str::random(40),
            'status' => self::STATUS_PENDING,
            // Zero from staff, and deliberately: sending an invitation is
            // somebody deciding to, which starts the allowance again. Only
            // a self-resend carries the previous count forward.
            'resends' => $resends,
            'storage_quota_mb' => $storageQuotaMb,
            'group_id' => $group?->id,
            'invited_by_id' => $invitedBy?->id,
            'expires_at' => $expiresAt,
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * What a person reading a list of invitations should be told this one
     * is — which is not quite `status`.
     *
     * "Expired" is not a stored status and deliberately is not one: nothing
     * writes it, a row becomes expired by the clock passing rather than by
     * anybody acting, and a stored value would need a scheduled task to
     * stay true. But it is the distinction somebody scanning the list cares
     * about most, so it is derived here, once, rather than in the screen
     * and again in the filter — the two would eventually disagree about the
     * edge.
     *
     * @return 'pending'|'expired'|'redeemed'|'revoked'|'superseded'
     */
    public function state(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => $this->isExpired() ? 'expired' : 'pending',
            self::STATUS_REDEEMED => 'redeemed',
            self::STATUS_REVOKED => 'revoked',
            default => 'superseded',
        };
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
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
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }
}
