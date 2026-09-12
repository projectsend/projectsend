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
        ];
    }

    /**
     * A fresh invitation for $email, retiring any other still-pending one
     * for the same address first — one live token per address at a time,
     * whether this is staff sending a second invite or the invited person
     * asking for a new link after the first expired.
     */
    public static function issue(string $email, ?string $name, ?Group $group, ?User $invitedBy, Carbon $expiresAt, int $storageQuotaMb = 0): self
    {
        self::query()->pending()->where('email', $email)->update(['status' => self::STATUS_SUPERSEDED]);

        return self::query()->create([
            'name' => $name,
            'email' => $email,
            'token' => Str::random(40),
            'status' => self::STATUS_PENDING,
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
