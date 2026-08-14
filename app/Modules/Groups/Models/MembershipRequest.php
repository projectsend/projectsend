<?php

declare(strict_types=1);

namespace App\Modules\Groups\Models;

use App\Models\User;
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
