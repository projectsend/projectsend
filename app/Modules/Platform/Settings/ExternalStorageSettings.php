<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-configured external storage backend — S3-compatible or Google
 * Cloud Storage, see StorageProvider — editable from the Storage
 * settings page. Single row (id 1 in practice, never
 * enforced) — same reasoning as MailProviderSettings: `secret` needs real
 * Eloquent encryption, which the generic settings table can't offer
 * per-key.
 *
 * @property int $id
 * @property bool $active
 * @property StorageProvider $provider
 * @property string|null $key
 * @property string|null $secret
 * @property string|null $key_file
 * @property string|null $bucket
 * @property string|null $region
 * @property string|null $endpoint
 * @property bool $use_path_style
 * @property string|null $root
 */
class ExternalStorageSettings extends Model
{
    protected $table = 'external_storage_settings';

    protected $fillable = [
        'active',
        'provider',
        'key',
        'secret',
        'key_file',
        'bucket',
        'region',
        'endpoint',
        'use_path_style',
        'root',
    ];

    /**
     * current() builds this with firstOrNew(), which does not apply the
     * column defaults — so on an install that has never opened the
     * Storage screen, `provider` would be null and the match in
     * isConfigured() would throw rather than answer. Defaults here are
     * what make an unsaved row a coherent object.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => false,
        'provider' => 's3',
        'use_path_style' => false,
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'provider' => StorageProvider::class,
            'secret' => 'encrypted',
            'key_file' => 'encrypted',
            'use_path_style' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrNew([]);
    }

    /**
     * Active isn't enough on its own — an admin could flip the toggle
     * before ever filling in real credentials (a blank bucket/key would
     * silently misroute every new upload to a broken disk).
     */
    public function isConfigured(): bool
    {
        if (! $this->active || ! $this->filled('bucket')) {
            return false;
        }

        // What counts as "filled in" is per provider, because the two
        // authenticate with different things entirely: S3 wants a key and
        // a secret, GCS wants a service account key file.
        return match ($this->provider) {
            StorageProvider::S3 => $this->filled('key') && $this->filled('secret'),
            StorageProvider::Gcs => $this->filled('key_file'),
        };
    }

    private function filled(string $attribute): bool
    {
        $value = $this->{$attribute};

        return is_string($value) && $value !== '';
    }
}
