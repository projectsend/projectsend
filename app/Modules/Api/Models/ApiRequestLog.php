<?php

declare(strict_types=1);

namespace App\Modules\Api\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per API request. See the migration for why this is separate from
 * the activity log, and why it stores a route pattern and no IP.
 *
 * @property int $id
 * @property int|null $api_token_id
 * @property string|null $api_token_name
 * @property int|null $user_id
 * @property string $method
 * @property string $route
 * @property int $status
 * @property int $duration_ms
 * @property Carbon $created_at
 */
class ApiRequestLog extends Model
{
    protected $table = 'api_request_logs';

    // Written once and never touched again, like the activity log.
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', '>=', 400);
    }

    /**
     * Everything a given user's own tokens did.
     *
     * Matched on the token's owner rather than on `user_id` alone so a
     * revoked token's history stays visible to the person who created it —
     * which is exactly when someone goes looking.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
