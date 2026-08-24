<?php

declare(strict_types=1);

namespace App\Modules\Platform\Storage;

use DateTimeInterface;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;

/**
 * The `gcs` filesystem driver, which Laravel does not ship.
 *
 * Two things here are not boilerplate, and both are the kind of thing
 * that fails quietly rather than loudly.
 *
 * **Laravel will not find the adapter's own method.** FilesystemAdapter
 * ::temporaryUrl() looks for a method named `getTemporaryUrl` on the
 * adapter, falls back to a registered callback, and otherwise throws
 * "This driver does not support creating temporary URLs". League's
 * adapter implements Flysystem's TemporaryUrlGenerator and names the
 * method `temporaryUrl`. The names do not meet, so without the
 * buildTemporaryUrlsUsing() below every download and every preview of a
 * GCS-stored file is a 500.
 *
 * **The two SDKs spell the signing options differently.** The callers —
 * StoredFileResponse, and anything else that hands options to
 * temporaryUrl() — speak the AWS vocabulary, because S3 came first and
 * one vocabulary is better than two. GCS wants `responseDisposition`
 * where S3 says `ResponseContentDisposition`, and an option it does not
 * recognise is ignored in silence: no exception, just downloads that
 * arrive named after the storage key and previews that download instead
 * of displaying. Translating here is what keeps every caller
 * provider-agnostic, and keeps the failure from being invisible.
 */
class GoogleCloudStorageDriver
{
    /**
     * AWS option name => Google option name, for the subset this
     * application actually sends. Anything absent is passed through
     * untouched, so a caller can still reach a Google-specific option by
     * its real name.
     */
    private const OPTION_NAMES = [
        'ResponseContentDisposition' => 'responseDisposition',
        'ResponseContentType' => 'responseType',
    ];

    public function register(): void
    {
        Storage::extend('gcs', fn ($app, array $config): FilesystemAdapter => $this->make($config));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function make(array $config): FilesystemAdapter
    {
        $client = new StorageClient(array_filter([
            // The key file carries its own project_id, so there is
            // nothing else to configure. Absent, the client falls back to
            // Application Default Credentials — which is how a self-hosted
            // install on a Google VM can work with no key at all, at the
            // cost of an IAM round trip per signature.
            'keyFile' => is_array($config['key_file'] ?? null) ? $config['key_file'] : null,
        ]));

        $adapter = new GoogleCloudStorageAdapter(
            $client->bucket((string) ($config['bucket'] ?? '')),
            (string) ($config['prefix'] ?? ''),
        );

        $disk = new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);

        // Bound and captured before registering, not called as
        // $this->signingOptions() inside the closure: Laravel re-binds the
        // callback to the FilesystemAdapter before invoking it
        // (bindTo($this, static::class)), so `$this` in there is the disk,
        // not this class, and the call fails at the first download rather
        // than here.
        $signingOptions = $this->signingOptions(...);

        $disk->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiration, array $options): string => $adapter->temporaryUrl(
                $path,
                $expiration,
                new Config(['gcp_signing_options' => $signingOptions($options)]),
            )
        );

        return $disk;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function signingOptions(array $options): array
    {
        $translated = [];

        foreach ($options as $name => $value) {
            $translated[self::OPTION_NAMES[$name] ?? $name] = $value;
        }

        // V4 explicitly rather than by default: v2 signatures are the
        // library's historical default in some paths, they are deprecated,
        // and the difference only shows up as a rejected URL at the moment
        // somebody tries to download something.
        return ['version' => 'v4', ...$translated];
    }
}
