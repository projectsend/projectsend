<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

use App\Modules\Files\Storage\ResolvingUploadDisk;
use App\Modules\Files\Storage\UploadLocations;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Registers a Laravel disk for every configured bucket in
 * storage_locations, so a file stamped with that disk can be read, and
 * answers ResolvingUploadDisk for new uploads (through UploadLocations).
 *
 * Community-only (Capability::StorageConfigure) — cloud operates its own
 * S3 and must never honor a stored bucket/credentials, even a stray one
 * left over from a downgrade. That check is deliberately made fresh on
 * every call, outside the cached locations() below (same shape as
 * MailConfigApplier) — the cache only ever holds the edition-independent
 * fact of what's stored in the database. Baking the capability check into
 * the cached value instead would let a value cached while running as
 * Community keep applying after a switch to Cloud, since rememberForever()
 * never expires and the only thing that calls flush() is saving the
 * storage screen — an edition change on its own wouldn't invalidate it.
 *
 * A bucket that stopped accepting uploads is still registered: the files
 * already in it have to keep downloading.
 *
 * Until the storage_locations migration has run — boot happens before
 * `artisan migrate`, so every upgrade passes through that window — the
 * single ExternalStorageSettings row is read exactly as the previous
 * release read it, on the same 'files_external' disk.
 *
 * Called on every process boot (PlatformServiceProvider::boot(), so both
 * web requests and a freshly (re)started queue worker pick it up) and
 * once more immediately after a save.
 */
class ExternalStorageConfigApplier
{
    // Bumped on any shape change to the cached list below — a stale
    // rememberForever value under an old key would otherwise crash every
    // boot with "Undefined array key" (apply() calls locations()
    // unconditionally). v5: one entry per storage location rather than
    // the single external row. Secrets and key files stay out of it, as
    // they have since v3: the cache store encrypts nothing.
    private const CACHE_KEY = 'platform.storage_locations.v5';

    // What the previous release cached under. Forgotten on flush() too, so
    // a downgrade does not boot on a value from before this release.
    private const LEGACY_CACHE_KEY = 'platform.external_storage_settings.v4';

    /** The inert shape config/filesystems.php gives 'files_external'. */
    private const DISK_TEMPLATE = [
        'driver' => 's3',
        'key' => '',
        'secret' => '',
        'region' => '',
        'bucket' => '',
        'endpoint' => null,
        'use_path_style_endpoint' => false,
        'throw' => false,
    ];

    public function __construct(
        private readonly CapabilityRegistry $capabilities,
    ) {}

    public function apply(): void
    {
        if (! $this->permitted()) {
            return;
        }

        foreach ($this->externalLocations() as $location) {
            $this->register($location);
        }
    }

    /**
     * @param  array<string, mixed>  $location  one entry of locations()
     */
    private function register(array $location): void
    {
        $provider = StorageProvider::from($location['provider']);

        // The whole disk is written, not patched, so a process that
        // switched a location's provider or cleared its folder cannot keep
        // a stale value: config lives for the length of the process.
        $config = [
            ...self::DISK_TEMPLATE,
            'driver' => $provider->driver(),
            'bucket' => $location['bucket'],
            // One "folder inside the bucket" on the screen, two names in
            // the adapters: Laravel's own drivers read 'root', and the GCS
            // driver in this application reads 'prefix'. Setting both
            // looked harmless and was not — Laravel's FilesystemManager
            // wraps any disk carrying a non-empty 'prefix' in
            // League\Flysystem\PathPrefixing\PathPrefixedAdapter, which
            // lives in an optional package nobody installs, so an S3 disk
            // with a folder set died with "class not found" the moment it
            // was touched (#1788). Only the driver that reads it gets it.
            'root' => $location['root'],
            'prefix' => $provider === StorageProvider::Gcs ? $location['root'] : null,
        ];

        $config = match ($provider) {
            StorageProvider::S3 => [
                ...$config,
                // Left null on purpose when the machine's own role is doing
                // the authenticating. Laravel's FilesystemManager only builds
                // a `credentials` entry when both a key and a secret are
                // non-empty, and the AWS SDK falls back to its default
                // credential provider chain — ECS task role, EC2 instance
                // profile, EKS/IRSA, environment — whenever none is
                // supplied. An empty string instead of nothing would be a
                // credential, and would fail rather than fall through.
                'key' => $location['use_instance_role'] ? null : $location['key'],
                'secret' => $location['use_instance_role'] ? null : $this->credential($location, 'secret'),
                'region' => $location['region'],
                'endpoint' => $location['endpoint'],
                'use_path_style_endpoint' => $location['use_path_style'],
            ],
            // Decoded here rather than stored decoded: the column holds the
            // key file verbatim, exactly as Google issued it. The S3 fields
            // are cleared rather than left looking like configuration.
            StorageProvider::Gcs => [
                ...$config,
                'key' => null,
                'secret' => null,
                'endpoint' => null,
                'key_file' => is_array($keyFile = json_decode((string) $this->credential($location, 'key_file'), true)) ? $keyFile : null,
            ],
        };

        Config::set('filesystems.disks.'.$location['disk'], $config);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::LEGACY_CACHE_KEY);
    }

    /**
     * One credential column, read from the row rather than from the cache.
     *
     * The same rule MailOAuthConnection states for tokens — "must never
     * travel through the boot-config cache" — applied to the two columns
     * that are credentials: the S3 secret access key and the GCS service
     * account key file. Reached only for a location that is configured and
     * permitted, so nothing is read on an installation that stores files
     * locally.
     *
     * Guarded like the cached read beside it: locations() can hand back a
     * warm "configured" from a database that has since stopped answering,
     * and booting must survive that.
     *
     * @param  array<string, mixed>  $location
     */
    private function credential(array $location, string $column): ?string
    {
        return BootSettingsCache::read(
            fn (): ?string => $location['id'] === null
                ? ExternalStorageSettings::current()->{$column}
                : StorageLocation::query()->find($location['id'])?->{$column},
        );
    }

    public function resolveDisk(ResolvingUploadDisk $event): void
    {
        $event->disk = app(UploadLocations::class)->diskFor($event->uploader, $event->requestedLocationId);
    }

    /** Whether this edition honors stored storage settings at all. */
    public function permitted(): bool
    {
        return $this->capabilities->has(Capability::StorageConfigure);
    }

    /**
     * Whether any bucket is configured and permitted: an upload, or an
     * orphan, can be somewhere other than the local disk. Deliberately
     * uncached (see class docblock) so an edition change or a capability
     * flip is never one process-boot stale.
     */
    public function isActive(): bool
    {
        return $this->externalLocations() !== [];
    }

    /** Whether uploads that follow the default (staff, unassigned clients) go to a bucket. */
    public function defaultIsExternal(): bool
    {
        return $this->defaultDisk() !== StorageLocation::LOCAL_DISK;
    }

    /**
     * The disk the default location writes to: the local disk unless the
     * default is a bucket that is configured, permitted and taking uploads.
     */
    public function defaultDisk(): string
    {
        foreach ($this->externalLocations() as $location) {
            if ($location['is_default'] && $location['accepts_uploads']) {
                return $location['disk'];
            }
        }

        return StorageLocation::LOCAL_DISK;
    }

    /**
     * The disks a new upload may be written to: the local disk, and every
     * registered bucket that is taking uploads.
     *
     * @return list<string>
     */
    public function writableDisks(): array
    {
        $disks = [StorageLocation::LOCAL_DISK];

        foreach ($this->externalLocations() as $location) {
            if ($location['accepts_uploads']) {
                $disks[] = $location['disk'];
            }
        }

        return $disks;
    }

    /**
     * Configured buckets, when this edition permits them.
     *
     * @return list<array{id: int|null, name: string, disk: string, provider: string, is_default: bool, accepts_uploads: bool, use_instance_role: bool, key: string|null, region: string|null, bucket: string|null, endpoint: string|null, use_path_style: bool, root: string|null}>
     */
    public function externalLocations(): array
    {
        if (! $this->permitted()) {
            return [];
        }

        return array_values(array_filter(
            $this->locations(),
            fn (array $location): bool => $location['disk'] !== StorageLocation::LOCAL_DISK,
        ));
    }

    /**
     * Deliberately edition-independent: what the database holds, nothing
     * more. Only configured buckets are listed; the local disk needs no
     * registering and is always there. Callers add the capability check,
     * live and uncached — see class docblock.
     *
     * @return list<array{id: int|null, name: string, disk: string, provider: string, is_default: bool, accepts_uploads: bool, use_instance_role: bool, key: string|null, region: string|null, bucket: string|null, endpoint: string|null, use_path_style: bool, root: string|null}>
     */
    private function locations(): array
    {
        // Through BootSettingsCache, not Cache directly: this runs on every
        // process boot, including the artisan commands that install the
        // application, and must survive a database that has no tables yet
        // (or none at all). See that class for the full story.
        return BootSettingsCache::rememberForever(self::CACHE_KEY, function (): array {
            if (Schema::hasTable('storage_locations')) {
                return array_values(StorageLocation::query()->orderBy('id')->get()
                    ->filter(fn (StorageLocation $location): bool => ! $location->isLocal() && $location->isConfigured())
                    ->map(fn (StorageLocation $location): array => [
                        'id' => $location->id,
                        'name' => $location->name,
                        'disk' => $location->disk,
                        'provider' => $location->provider,
                        'is_default' => $location->is_default,
                        'accepts_uploads' => $location->accepts_uploads,
                        'use_instance_role' => $location->use_instance_role,
                        'key' => $location->key,
                        'region' => $location->region,
                        'bucket' => $location->bucket,
                        'endpoint' => $location->endpoint,
                        'use_path_style' => $location->use_path_style,
                        'root' => $location->root,
                    ])->all());
            }

            return $this->legacyLocation();
        }, []);
    }

    /**
     * The previous release's single row, as the one bucket it was: on
     * 'files_external', and where every new upload went while switched on.
     *
     * @return list<array{id: null, name: string, disk: string, provider: string, is_default: bool, accepts_uploads: bool, use_instance_role: bool, key: string|null, region: string|null, bucket: string|null, endpoint: string|null, use_path_style: bool, root: string|null}>
     */
    private function legacyLocation(): array
    {
        if (! Schema::hasTable('external_storage_settings')) {
            return [];
        }

        $settings = ExternalStorageSettings::current();

        if (! $settings->isConfigured()) {
            return [];
        }

        return [[
            'id' => null,
            'name' => 'External storage',
            'disk' => 'files_external',
            'provider' => $settings->provider->value,
            'is_default' => true,
            'accepts_uploads' => true,
            'use_instance_role' => $settings->use_instance_role,
            'key' => $settings->key,
            'region' => $settings->region,
            'bucket' => $settings->bucket,
            'endpoint' => $settings->endpoint,
            'use_path_style' => $settings->use_path_style,
            'root' => $settings->root,
        ]];
    }
}
