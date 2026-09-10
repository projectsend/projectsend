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
 * @property bool $use_instance_role
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
        'use_instance_role',
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
        'use_instance_role' => false,
        'use_path_style' => false,
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'provider' => StorageProvider::class,
            'use_instance_role' => 'boolean',
            'secret' => 'encrypted',
            'key_file' => 'encrypted',
            'use_path_style' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $settings = static::query()->firstOrNew([]);

        // Column defaults — the $attributes array above — apply to a NEW
        // model, never to one hydrated from a row. So a row written by an
        // older release, before one of these columns existed, reads that
        // column as null however sensible its default is.
        //
        // That matters here more than it would anywhere else, because
        // PlatformServiceProvider::boot() reads these settings on every
        // process boot — and boot happens BEFORE `artisan migrate` runs.
        // For the length of an upgrade the code is new and the schema is
        // still old, and every artisan command in that window, including
        // the one that would run the migrations, boots through here.
        //
        // A null `provider` made the match in isConfigured() throw
        // UnhandledMatchError, which the official image's readiness probe
        // reported to the operator as "database unreachable" — on a
        // perfectly reachable database, in a container that then
        // restart-looped without ever reaching the migration that would
        // have fixed it (#1770, upgrading from 2.0/2.1 with external
        // storage configured).
        //
        // Applying the defaults to a hydrated row closes that window for
        // every column that has one, rather than for the single column
        // where it was found. Inert on any install whose schema is current.
        foreach ((new self)->getAttributes() as $column => $default) {
            if (! array_key_exists($column, $settings->getAttributes())) {
                $settings->setAttribute($column, $default);
            }
        }

        return $settings;
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
        //
        // The match is deliberately left total rather than given a default
        // arm: current() guarantees a provider even on a row older than the
        // column, and a default arm here would quietly swallow a genuinely
        // unhandled case instead of naming it.
        //
        // Unless S3 is being asked to authenticate as the machine it is
        // running on, in which case there is no credential to fill in at
        // all and demanding one would leave the disk permanently
        // "unconfigured" — which fails silently, by leaving every new
        // upload on the local disk rather than by reporting anything.
        return match ($this->provider) {
            StorageProvider::S3 => $this->use_instance_role || ($this->filled('key') && $this->filled('secret')),
            StorageProvider::Gcs => $this->filled('key_file'),
        };
    }

    private function filled(string $attribute): bool
    {
        $value = $this->{$attribute};

        return is_string($value) && $value !== '';
    }
}
