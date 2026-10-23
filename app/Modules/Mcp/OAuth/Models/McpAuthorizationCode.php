<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One authorization attempt the person approved: sixty seconds, one use.
 *
 * @property int $id
 * @property string $code_hash
 * @property int $mcp_client_id
 * @property int $user_id
 * @property list<string> $scopes
 * @property string $code_challenge
 * @property string $redirect_uri
 * @property string $resource
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property int|null $issued_token_id
 * @property-read McpClient $client
 */
final class McpAuthorizationCode extends Model
{
    public const LIFETIME_SECONDS = 60;

    protected $table = 'mcp_authorization_codes';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public static function hashOf(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * @return BelongsTo<McpClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(McpClient::class, 'mcp_client_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
