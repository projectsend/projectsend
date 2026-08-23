<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail;

use App\Modules\Platform\Settings\MailProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One OAuth mail provider's app registration and its connected mailbox.
 *
 * Shaped after SocialSettings, including the part that matters most:
 * `client_secret` and both tokens carry an `'encrypted'` cast, so a
 * database dump does not hand over a credential that can send mail as
 * the organization.
 *
 * The row splits into two halves with different lifetimes: the app
 * registration (client_id/client_secret/tenant_id) survives a
 * disconnect, while the connection itself (tokens, account, error state)
 * is what connecting and disconnecting write. Transports read this row
 * fresh at send time — tokens must never travel through the boot-config
 * cache (see MailConfigApplier, which caches only readiness and the
 * account address).
 *
 * @property int $id
 * @property MailProvider $provider
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property string|null $tenant_id
 * @property string|null $account_email
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $last_refreshed_at
 * @property string|null $last_error
 */
class MailOAuthConnection extends Model
{
    protected $table = 'mail_oauth_connections';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provider' => MailProvider::class,
            'client_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
        ];
    }

    public static function for(MailProvider $provider): self
    {
        return static::query()->firstOrNew(['provider' => $provider->value]);
    }

    /**
     * Whether the connect flow can be started: the admin has entered the
     * app registration, even if no mailbox is connected yet.
     */
    public function configured(): bool
    {
        return $this->filled('client_id') && $this->filled('client_secret');
    }

    /**
     * Whether transports can send through this connection. A half-torn
     * state (configured but never connected, or tokens cleared by a
     * disconnect) behaves as "not usable" rather than failing inside a
     * queued job — the same rule SocialSettings::usable() follows.
     */
    public function usable(): bool
    {
        return $this->configured() && $this->filled('refresh_token');
    }

    private function filled(string $attribute): bool
    {
        $value = $this->getAttribute($attribute);

        return is_string($value) && trim($value) !== '';
    }
}
