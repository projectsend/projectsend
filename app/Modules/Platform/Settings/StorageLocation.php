<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Somewhere uploaded files can be kept: the server's own disk (exactly one
 * row, disk 'files') or a bucket.
 *
 * `disk` is the name files on this location are stamped with
 * (files.disk). It is fixed once the row exists, because changing it
 * would leave every file already stored here pointing at nothing.
 *
 * `provider` is 'local' or a StorageProvider value. StorageProvider
 * itself stays external-only, so the bucket form can never be submitted
 * as "local".
 *
 * @property int $id
 * @property string $name
 * @property string|null $client_note
 * @property string $disk
 * @property string $provider
 * @property bool $accepts_uploads
 * @property bool $is_default
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
class StorageLocation extends Model
{
    public const LOCAL_DISK = 'files';

    protected $fillable = [
        'name',
        'client_note',
        'provider',
        'accepts_uploads',
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

    /** @var array<string, mixed> */
    protected $attributes = [
        'provider' => 's3',
        'accepts_uploads' => true,
        'is_default' => false,
        'use_instance_role' => false,
        'use_path_style' => false,
    ];

    protected function casts(): array
    {
        return [
            'accepts_uploads' => 'boolean',
            'is_default' => 'boolean',
            'use_instance_role' => 'boolean',
            'secret' => 'encrypted',
            'key_file' => 'encrypted',
            'use_path_style' => 'boolean',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_storage_location')->withPivot('priority');
    }

    public function isLocal(): bool
    {
        return $this->disk === self::LOCAL_DISK;
    }

    /**
     * Whether a bucket has everything it needs to be talked to. The same
     * per-provider rule ExternalStorageSettings::isConfigured() applied,
     * minus its on/off switch: here that is accepts_uploads, and a location
     * that stopped taking uploads still has to serve the files it holds.
     */
    public function isConfigured(): bool
    {
        if ($this->isLocal()) {
            return true;
        }

        if (! $this->filled('bucket')) {
            return false;
        }

        return match (StorageProvider::from($this->provider)) {
            StorageProvider::S3 => $this->use_instance_role || ($this->filled('key') && $this->filled('secret')),
            StorageProvider::Gcs => $this->filled('key_file'),
        };
    }

    /** "s3://bucket/prefix", or the local directory, for the admin screen only. */
    public function location(): string
    {
        if ($this->isLocal()) {
            return rtrim(storage_path('app/files'), '/');
        }

        $root = $this->root !== null && $this->root !== '' ? '/'.trim($this->root, '/') : '';
        $scheme = $this->provider === StorageProvider::Gcs->value ? 'gs' : 's3';

        return $scheme.'://'.$this->bucket.$root;
    }

    private function filled(string $attribute): bool
    {
        $value = $this->{$attribute};

        return is_string($value) && $value !== '';
    }
}
