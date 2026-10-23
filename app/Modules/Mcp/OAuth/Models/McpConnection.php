<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A live connection between a person and an assistant: the refresh token
 * in force and the access token it issued last. Revoking it deletes the
 * access token and marks the row; the row stays, for the page that says
 * what was connected and when it was cut.
 *
 * @property int $id
 * @property int $mcp_client_id
 * @property int $user_id
 * @property list<string> $scopes
 * @property string|null $refresh_token_hash
 * @property string|null $previous_refresh_token_hash
 * @property string $family
 * @property int|null $access_token_id
 * @property Carbon|null $last_used_at
 * @property Carbon $refresh_expires_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
 * @property Carbon $created_at
 * @property-read McpClient $client
 */
final class McpConnection extends Model
{
    public const REFRESH_LIFETIME_DAYS = 30;

    protected $table = 'mcp_connections';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashOf(string $refreshToken): string
    {
        return hash('sha256', $refreshToken);
    }

    /**
     * @return BelongsTo<McpClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(McpClient::class, 'mcp_client_id');
    }

    /**
     * @param  Builder<McpConnection>  $query
     * @return Builder<McpConnection>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('refresh_expires_at', '>', now());
    }

    public function isLive(): bool
    {
        return $this->revoked_at === null && $this->refresh_expires_at->isFuture();
    }
}
