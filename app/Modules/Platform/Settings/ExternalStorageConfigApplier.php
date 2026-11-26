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
 * storage_locations, so a file stamped with that disk can be read. A
 * bucket that stopped accepting uploads is still registered: the files
 * already in it have to keep downloading. Until the storage_locations
 * migration has run — boot happens before `artisan migrate` — the single
 * ExternalStorageSettings row is read as before, on 'files_external'.
 *
 * Community-only (Capability::StorageConfigure) — cloud operates its own
 * S3 and must never honor a stored bucket/credentials, even a stray one
 * left over from a downgrade. That check is deliberately made fresh on
 * every call, outside the cached resolve() below (same shape as
 * MailConfigApplier) — resolve()'s cache only ever holds the
 * edition-independent fact of what's stored in the database. Baking the
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
    // boot with "Undefined array key" (every permitted boot reads resolve()).
    // v3: the S3 secret and the GCS key file left the shape. The cache
    // store encrypts nothing and rememberForever never expires, so on the
    // documented CACHE_STORE=database they sat in clear — the service
    // account's private key included — in the same database whose dump
    // the `encrypted` cast exists to survive. Both are now read straight
    // from the row, by the provider branch that uses them.
    // v4: use_instance_role joined the shape. Not a credential — it is
    // the fact that there isn't one — so it caches like the rest.
    // v5: a list, one entry per storage location.
    private const CACHE_KEY = 'platform.storage_locations.v5';

    /** The inert stub config/filesystems.php gives 'files_external'. */
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
        foreach ($this->externalLocations() as $resolved) {
            $this->applyLocation($resolved);
        }
    }

    /**
     * @param  array<string, mixed>  $resolved  one entry of resolve()
     */
    private function applyLocation(array $resolved): void
    {
        $disk = 'filesystems.disks.'.$resolved['disk'];
        $provider = StorageProvider::from($resolved['provider']);

        // The driver is part of what gets overwritten, not a constant:
        // config/filesystems.php ships the disk as an inert 's3' stub, and
        // this is the only thing that ever makes it anything else. A
        // bucket's own disk starts from the same stub.
        Config::set($disk, self::DISK_TEMPLATE);
        Config::set($disk.'.driver', $provider->driver());
        Config::set($disk.'.bucket', $resolved['bucket']);

        match ($provider) {
            StorageProvider::S3 => $this->applyS3($disk, $resolved),
            // Only the id: everything GCS needs from the row is the key
            // file, and that is a credential the cache no longer holds.
            StorageProvider::Gcs => $this->applyGcs($disk, $resolved['id']),
        };

        // One "folder inside the bucket" on the screen, two names in the
        // adapters: Laravel's own drivers read 'root', and the GCS driver
        // in this application reads 'prefix' and builds its adapter with
        // it. Setting both looked harmless and was not — Laravel's
        // FilesystemManager wraps any disk carrying a non-empty 'prefix'
        // in League\Flysystem\PathPrefixing\PathPrefixedAdapter, which
        // lives in an optional package nobody installs, so an S3 disk with
        // a folder set died with "class not found" the moment it was
        // touched (#1788). Only the driver that reads it gets it.
        //
        // Written on every apply, not only when there is a folder, so a
        // process that switched provider or cleared the folder cannot keep
        // a stale one: config lives for the length of the process.
        Config::set($disk.'.root', $resolved['root']);
        Config::set(
            $disk.'.prefix',
            $provider === StorageProvider::Gcs ? $resolved['root'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $resolved
     */
    private function applyS3(string $disk, array $resolved): void
    {
        // Left null on purpose when the machine's own role is doing the
        // authenticating. Laravel's FilesystemManager::formatS3Config()
        // only builds a `credentials` entry when both a key and a secret
        // are non-empty, and the AWS SDK falls back to its default
        // credential provider chain — ECS task role, EC2 instance
        // profile, EKS/IRSA, environment — whenever none is supplied.
        // Passing an empty string instead of nothing would be a
        // credential, and would fail rather than fall through.
        Config::set($disk.'.key', $resolved['use_instance_role'] ? null : $resolved['key']);
        Config::set($disk.'.secret', $resolved['use_instance_role'] ? null : $this->credential($resolved['id'], 'secret'));
        Config::set($disk.'.region', $resolved['region']);
        Config::set($disk.'.endpoint', $resolved['endpoint']);
        Config::set($disk.'.use_path_style_endpoint', $resolved['use_path_style']);
    }

    private function applyGcs(string $disk, ?int $locationId): void
    {
        // Decoded here rather than stored decoded: the column holds the
        // key file verbatim, exactly as Google issued it, so that what an
        // administrator pasted is what can be handed back to them and
        // compared against the console.
        //
        // Read from the row rather than from $resolved: it is a private
        // key, and the cached array no longer carries one.
        $keyFile = json_decode((string) $this->credential($locationId, 'key_file'), true);

        Config::set($disk.'.key_file', is_array($keyFile) ? $keyFile : null);

        // Left over from the S3 stub in config/filesystems.php, and
        // meaningless to the GCS adapter — cleared rather than left
        // sitting there looking like configuration.
        Config::set($disk.'.key', null);
        Config::set($disk.'.secret', null);
        Config::set($disk.'.endpoint', null);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * One credential column, read from the row rather than from the cache.
     *
     * The same rule MailOAuthConnection states for tokens — "must never
     * travel through the boot-config cache" — applied to the two columns
     * on this row that are credentials: the S3 secret access key and the
     * GCS service account key file. Reached only from the provider branch
     * that uses one, and only when isActive() has already said the disk is
     * configured and permitted, so nothing is read on an installation that
     * stores files locally.
     *
     * Guarded like the cached read beside it: resolve() can hand back a
     * warm "configured" from a database that has since stopped answering,
     * and booting must survive that. A null id is the previous release's
     * row, read before the storage locations migration has run.
     */
    private function credential(?int $locationId, string $column): ?string
    {
        return BootSettingsCache::read(
            fn (): ?string => $locationId === null
                ? ExternalStorageSettings::current()->{$column}
                : StorageLocation::query()->find($locationId)?->{$column},
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
     * Whether any bucket is both configured and permitted: an upload, or
     * an orphan, can be somewhere other than the local disk. Deliberately
     * uncached (see class docblock) so an edition change or a capability
     * flip is never one process-boot stale.
     */
    public function isActive(): bool
    {
        return $this->externalLocations() !== [];
    }

    /**
     * The disk uploads that follow the default (staff, clients with no
     * locations of their own) go to: the local disk unless the default is
     * a bucket that is configured, permitted and taking uploads.
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
     * Configured buckets, when this edition permits them.
     *
     * @return list<array{id: int|null, name: string, disk: string, provider: string, is_default: bool, accepts_uploads: bool, use_instance_role: bool, key: string|null, region: string|null, bucket: string|null, endpoint: string|null, use_path_style: bool, root: string|null}>
     */
    public function externalLocations(): array
    {
        return $this->permitted() ? $this->resolve() : [];
    }

    /**
     * Deliberately edition-independent: the configured buckets the
     * database holds, nothing more. The local disk needs no registering
     * and is always there. Callers add the capability check, live and
     * uncached — see class docblock.
     *
     * @return list<array{id: int|null, name: string, disk: string, provider: string, is_default: bool, accepts_uploads: bool, use_instance_role: bool, key: string|null, region: string|null, bucket: string|null, endpoint: string|null, use_path_style: bool, root: string|null}>
     */
    private function resolve(): array
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

            if (! Schema::hasTable('external_storage_settings')) {
                return [];
            }

            $settings = ExternalStorageSettings::current();

            if (! $settings->isConfigured()) {
                return [];
            }

            // The previous release's single row, as the one bucket it was:
            // on 'files_external', where every new upload went.
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
        }, []);
    }
}
