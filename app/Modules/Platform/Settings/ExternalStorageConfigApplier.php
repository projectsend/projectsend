<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

use App\Modules\Files\Storage\ResolvingUploadDisk;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Overrides the inert 'files_external' disk stub (config/filesystems.php)
 * with the admin-configured ExternalStorageSettings row, when one exists
 * and is fully filled in — otherwise config/filesystems.php's blank
 * defaults stand untouched (fresh installs, or any install that hasn't
 * visited the Storage settings page yet, behave exactly as before this
 * feature existed).
 *
 * Community-only (Capability::StorageConfigure) — cloud operates its own
 * S3 and must never honor a stored bucket/credentials, even a stray one
 * left over from a downgrade. That check is deliberately made fresh on
 * every call, outside the cached resolve() below (same shape as
 * MailConfigApplier) — resolve()'s cache only ever holds the
 * edition-independent fact of what's stored in the DB row. Baking the
 * capability check into the cached value instead would let a value
 * cached while running as Community keep applying after a switch to
 * Cloud, since rememberForever() never expires and the only thing that
 * calls flush() is saving the settings form — an edition change on its
 * own wouldn't invalidate it.
 *
 * Called on every process boot (PlatformServiceProvider::boot(), so both
 * web requests and a freshly (re)started queue worker pick it up) and
 * once more immediately after a save. Also the ResolvingUploadDisk
 * listener registered from PlatformServiceProvider::boot() — the only
 * thing that ever redirects a new upload away from the local 'files'
 * disk (see docs/extension-points-architecture.md for why this is an
 * event listener rather than an interface binding).
 */
class ExternalStorageConfigApplier
{
    // Bumped on any shape change to the resolved array below — a stale
    // rememberForever value under an old key would otherwise crash every
    // boot with "Undefined array key" (apply() calls resolve() unconditionally).
    private const CACHE_KEY = 'platform.external_storage_settings.v2';

    public function __construct(
        private readonly CapabilityRegistry $capabilities,
    ) {}

    public function apply(): void
    {
        if (! $this->isActive()) {
            return;
        }

        $resolved = $this->resolve();
        $provider = StorageProvider::from($resolved['provider']);

        // The driver is part of what gets overwritten, not a constant:
        // config/filesystems.php ships the disk as an inert 's3' stub, and
        // this is the only thing that ever makes it anything else.
        Config::set('filesystems.disks.files_external.driver', $provider->driver());
        Config::set('filesystems.disks.files_external.bucket', $resolved['bucket']);

        match ($provider) {
            StorageProvider::S3 => $this->applyS3($resolved),
            StorageProvider::Gcs => $this->applyGcs($resolved),
        };

        if ($resolved['root'] !== null) {
            // Two names for one idea, because the two adapters disagree:
            // Laravel's S3 driver reads 'root', Flysystem's GCS adapter is
            // constructed with a 'prefix'. Setting both keeps the settings
            // screen able to speak of one "folder inside the bucket".
            Config::set('filesystems.disks.files_external.root', $resolved['root']);
            Config::set('filesystems.disks.files_external.prefix', $resolved['root']);
        }
    }

    /**
     * @param  array<string, mixed>  $resolved
     */
    private function applyS3(array $resolved): void
    {
        Config::set('filesystems.disks.files_external.key', $resolved['key']);
        Config::set('filesystems.disks.files_external.secret', $resolved['secret']);
        Config::set('filesystems.disks.files_external.region', $resolved['region']);
        Config::set('filesystems.disks.files_external.endpoint', $resolved['endpoint']);
        Config::set('filesystems.disks.files_external.use_path_style_endpoint', $resolved['use_path_style']);
    }

    /**
     * @param  array<string, mixed>  $resolved
     */
    private function applyGcs(array $resolved): void
    {
        // Decoded here rather than stored decoded: the column holds the
        // key file verbatim, exactly as Google issued it, so that what an
        // administrator pasted is what can be handed back to them and
        // compared against the console.
        $keyFile = json_decode((string) $resolved['key_file'], true);

        Config::set('filesystems.disks.files_external.key_file', is_array($keyFile) ? $keyFile : null);

        // Left over from the S3 stub in config/filesystems.php, and
        // meaningless to the GCS adapter — cleared rather than left
        // sitting there looking like configuration.
        Config::set('filesystems.disks.files_external.key', null);
        Config::set('filesystems.disks.files_external.secret', null);
        Config::set('filesystems.disks.files_external.endpoint', null);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function resolveDisk(ResolvingUploadDisk $event): void
    {
        if ($this->isActive()) {
            $event->disk = 'files_external';
        }
    }

    /**
     * Whether 'files_external' is both fully configured (the DB row) and
     * permitted (the edition's capability) — the single live check every
     * caller in this class needs, kept in one place. Deliberately
     * uncached (see class docblock) so an edition change or a capability
     * flip is never one process-boot stale.
     */
    public function isActive(): bool
    {
        return $this->resolve()['configured'] && $this->capabilities->has(Capability::StorageConfigure);
    }

    /**
     * Deliberately edition-independent: whether the DB row itself is fully
     * filled in and active, nothing more. Callers AND the capability check
     * live and uncached — see class docblock.
     *
     * @return array{configured: bool, provider: string, key: string|null, secret: string|null, key_file: string|null, region: string|null, bucket: string|null, endpoint: string|null, use_path_style: bool, root: string|null}
     */
    private function resolve(): array
    {
        $blank = [
            'configured' => false,
            'provider' => StorageProvider::S3->value,
            'key' => null, 'secret' => null, 'key_file' => null,
            'region' => null, 'bucket' => null,
            'endpoint' => null, 'use_path_style' => false, 'root' => null,
        ];

        // Through BootSettingsCache, not Cache directly: this runs on every
        // process boot, including the artisan commands that install the
        // application, and must survive a database that has no tables yet
        // (or none at all). See that class for the full story.
        return BootSettingsCache::rememberForever(self::CACHE_KEY, function () use ($blank): array {
            if (! Schema::hasTable('external_storage_settings')) {
                return $blank;
            }

            $settings = ExternalStorageSettings::current();

            if (! $settings->isConfigured()) {
                return $blank;
            }

            return [
                'configured' => true,
                'provider' => $settings->provider->value,
                'key' => $settings->key,
                'secret' => $settings->secret,
                'key_file' => $settings->key_file,
                'region' => $settings->region,
                'bucket' => $settings->bucket,
                'endpoint' => $settings->endpoint,
                'use_path_style' => $settings->use_path_style,
                'root' => $settings->root,
            ];
        }, $blank);
    }
}
