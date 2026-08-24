<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

/**
 * Which object store the external `files_external` disk is talking to.
 *
 * Unlike MailProvider, this is not a preset picker over one transport:
 * the two cases are genuinely different Flysystem drivers, authenticated
 * differently — an access key and secret against an S3 API, a service
 * account key against Google's. Which fields the Storage settings screen
 * shows, which of them are validated, and what
 * ExternalStorageConfigApplier writes into the disk config all follow
 * from this.
 *
 * S3 keeps its endpoint and path-style settings because "S3" here means
 * the whole S3-compatible family — AWS itself, MinIO, Backblaze,
 * Wasabi, and Google's own interoperability endpoint for anyone who
 * would rather use HMAC keys than a service account.
 */
enum StorageProvider: string
{
    case S3 = 's3';
    case Gcs = 'gcs';

    public function label(): string
    {
        return match ($this) {
            self::S3 => 'S3-compatible',
            self::Gcs => 'Google Cloud Storage',
        };
    }

    /** The Laravel filesystem driver this provider is served by. */
    public function driver(): string
    {
        return match ($this) {
            self::S3 => 's3',
            self::Gcs => 'gcs',
        };
    }
}
