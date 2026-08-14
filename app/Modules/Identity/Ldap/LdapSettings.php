<?php

declare(strict_types=1);

namespace App\Modules\Identity\Ldap;

use Illuminate\Database\Eloquent\Model;

/**
 * The single row describing this installation's directory.
 *
 * Shaped after MailProviderSettings, including the part that matters most:
 * `bind_password` carries an `'encrypted'` cast, so a database dump does
 * not hand over a service-account credential. v1 stored this one in plain
 * text and then echoed it into the settings form's HTML `value=`.
 *
 * @property bool $active
 * @property string|null $host
 * @property int $port
 * @property LdapEncryption $encryption
 * @property string|null $ca_cert_path
 * @property string|null $bind_dn
 * @property string|null $bind_password
 * @property string|null $base_dn
 * @property string|null $user_filter
 * @property string $email_attribute
 * @property string $name_attribute
 * @property bool $auto_provision
 * @property bool $auto_approve
 */
class LdapSettings extends Model
{
    protected $table = 'ldap_settings';

    protected $guarded = [];

    /**
     * Column defaults only apply on INSERT, so they never reach the unsaved
     * instance `current()` hands back on a fresh install — these do.
     */
    protected $attributes = [
        'active' => false,
        'port' => 389,
        'encryption' => 'tls',
        'email_attribute' => 'mail',
        'name_attribute' => 'cn',
        'auto_provision' => false,
        'auto_approve' => false,
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'port' => 'integer',
            'encryption' => LdapEncryption::class,
            'bind_password' => 'encrypted',
            'auto_provision' => 'boolean',
            'auto_approve' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrNew([]);
    }

    /**
     * Whether a login may consult the directory at all.
     *
     * The extension check is part of the answer rather than a separate
     * question: an administrator can save settings on a server that cannot
     * talk LDAP, and every login must then behave exactly as if the
     * feature were switched off rather than throwing.
     */
    public function usable(): bool
    {
        return $this->active
            && extension_loaded('ldap')
            && is_string($this->host) && $this->host !== ''
            && is_string($this->base_dn) && $this->base_dn !== '';
    }
}
