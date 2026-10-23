<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An assistant that may ask a person to connect it.
 *
 * `cimd`: the client is identified by the HTTPS URL of a document it
 * publishes, and this row is a cache of that document. `dcr`: the client
 * registered itself and this row is what it declared. The consent shows
 * the first as verified by its hostname and the second as unverified.
 *
 * @property int $id
 * @property string $client_id
 * @property string $client_id_hash
 * @property string $kind
 * @property string $name
 * @property string|null $client_uri
 * @property string|null $logo_uri
 * @property list<string> $redirect_uris
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $metadata_fetched_at
 * @property Carbon|null $metadata_expires_at
 * @property string|null $fingerprint
 * @property Carbon $created_at
 */
final class McpClient extends Model
{
    public const KIND_METADATA_DOCUMENT = 'cimd';

    public const KIND_DYNAMIC = 'dcr';

    protected $table = 'mcp_clients';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'metadata' => 'array',
            'metadata_fetched_at' => 'datetime',
            'metadata_expires_at' => 'datetime',
        ];
    }

    public static function hashOf(string $clientId): string
    {
        return hash('sha256', $clientId);
    }

    public static function findByClientId(string $clientId): ?self
    {
        return self::query()->where('client_id_hash', self::hashOf($clientId))->first();
    }

    /** Whether the client's identity was read from a document it publishes. */
    public function isVerified(): bool
    {
        return $this->kind === self::KIND_METADATA_DOCUMENT;
    }

    /**
     * The hostname a person is shown as who the client is: the document's
     * host for a verified client, the first redirect's host otherwise.
     */
    public function identity(): string
    {
        $source = $this->isVerified() ? $this->client_id : ($this->redirect_uris[0] ?? '');
        $host = parse_url($source, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $this->name;
    }

    /**
     * @return HasMany<McpConnection, $this>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(McpConnection::class, 'mcp_client_id');
    }
}
