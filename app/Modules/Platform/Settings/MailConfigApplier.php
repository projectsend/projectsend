<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Overrides Laravel's mail config with the admin-configured
 * MailProviderSettings row, when one exists — otherwise leaves
 * config/mail.php + .env completely untouched (fresh installs, or any
 * install that hasn't visited the Email settings page yet, behave
 * exactly as before this feature existed).
 *
 * Transport (host/port/username/password/encryption) and sender identity
 * (from address/name) are gated independently: transport only applies
 * under Capability::EmailTransportConfigure (community edition — cloud
 * operates its own relay and must never honor a stored host, even a
 * stray one), while sender identity always applies in both editions —
 * hosted customers still set their own From/reply-to.
 *
 * Called on every process boot (PlatformServiceProvider::boot(), so both
 * web requests and a freshly (re)started queue worker pick it up) and
 * once more immediately after a save, so the same request already
 * reflects the new values (e.g. before clicking "Send test email").
 */
class MailConfigApplier
{
    // Bumped (was 'platform.mail_provider_settings') when the resolved
    // array's shape changed (transport/identity split) — a stale
    // rememberForever value under the old key would otherwise crash every
    // boot with "Undefined array key" (PlatformServiceProvider::boot()
    // calls apply() unconditionally). Bump again on any future shape change.
    private const CACHE_KEY = 'platform.mail_provider_settings.v2';

    public function __construct(
        private readonly CapabilityRegistry $capabilities,
    ) {}

    public function apply(): void
    {
        $resolved = $this->resolve();

        if ($resolved['transport_configured'] && $this->capabilities->has(Capability::EmailTransportConfigure)) {
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', $resolved['host']);
            Config::set('mail.mailers.smtp.port', $resolved['port']);
            Config::set('mail.mailers.smtp.username', $resolved['username']);
            Config::set('mail.mailers.smtp.password', $resolved['password']);
            Config::set('mail.mailers.smtp.encryption', $resolved['encryption']);
        }

        if ($resolved['from_address'] !== null) {
            Config::set('mail.from.address', $resolved['from_address']);
        }

        if ($resolved['from_name'] !== null) {
            Config::set('mail.from.name', $resolved['from_name']);
        }
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{transport_configured: bool, host: string|null, port: int|null, username: string|null, password: string|null, encryption: string|null, from_address: string|null, from_name: string|null}
     */
    private function resolve(): array
    {
        $blank = [
            'transport_configured' => false,
            'host' => null, 'port' => null, 'username' => null, 'password' => null, 'encryption' => null,
            'from_address' => null, 'from_name' => null,
        ];

        // Through BootSettingsCache, not Cache directly: this runs on every
        // process boot, including the artisan commands that install the
        // application, and must survive a database that has no tables yet
        // (or none at all). See that class for the full story.
        return BootSettingsCache::rememberForever(self::CACHE_KEY, function () use ($blank): array {
            if (! Schema::hasTable('mail_provider_settings')) {
                return $blank;
            }

            $settings = MailProviderSettings::current();
            $hasHost = $settings->host !== null && $settings->host !== '';

            return [
                'transport_configured' => $hasHost,
                'host' => $settings->host,
                'port' => $settings->port,
                'username' => $settings->username,
                'password' => $settings->password,
                'encryption' => $settings->encryption === 'none' ? null : $settings->encryption,
                'from_address' => $settings->from_address,
                'from_name' => $settings->from_name,
            ];
        }, $blank);
    }
}
