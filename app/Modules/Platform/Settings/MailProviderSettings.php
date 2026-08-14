<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

use Illuminate\Database\Eloquent\Model;

/**
 * SMTP transport configuration, editable from the Email settings page.
 * Single row (id 1 in practice, never enforced) — see the migration for
 * why this isn't just another Setting: `password` needs real Eloquent
 * encryption, which the generic settings table can't offer per-key.
 *
 * @property int $id
 * @property MailProvider $provider
 * @property string|null $host
 * @property int|null $port
 * @property string|null $username
 * @property string|null $password
 * @property string $encryption
 * @property string|null $from_address
 * @property string|null $from_name
 */
class MailProviderSettings extends Model
{
    protected $table = 'mail_provider_settings';

    /**
     * Defaults for a not-yet-saved instance (current()'s firstOrNew) —
     * mirrors the migration's column defaults, which only apply on
     * INSERT and never touch an in-memory, unsaved model.
     */
    protected $attributes = [
        'provider' => 'custom',
        'encryption' => 'tls',
    ];

    protected $fillable = [
        'provider',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
    ];

    protected function casts(): array
    {
        return [
            'provider' => MailProvider::class,
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrNew([]);
    }
}
