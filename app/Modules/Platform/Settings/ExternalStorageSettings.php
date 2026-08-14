<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-configured S3-compatible external storage backend, editable from
 * the Storage settings page. Single row (id 1 in practice, never
 * enforced) — same reasoning as MailProviderSettings: `secret` needs real
 * Eloquent encryption, which the generic settings table can't offer
 * per-key.
 *
 * @property int $id
 * @property bool $active
 * @property string|null $key
 * @property string|null $secret
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
        'key',
        'secret',
        'bucket',
        'region',
        'endpoint',
        'use_path_style',
        'root',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'secret' => 'encrypted',
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
        return $this->active
            && $this->key !== null && $this->key !== ''
            && $this->secret !== null && $this->secret !== ''
            && $this->bucket !== null && $this->bucket !== '';
    }
}
