<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One provider's configuration.
 *
 * Shaped after LdapSettings, including the part that matters most:
 * `client_secret` carries an `'encrypted'` cast, so a database dump does
 * not hand over an OAuth client credential. v1 stored all eight of these
 * in plain text and echoed them into the settings form's HTML `value=`.
 *
 * @property SocialProvider $provider
 * @property bool $enabled
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property string|null $issuer_url
 * @property string|null $tenant_id
 * @property bool $require_verified_email
 * @property string|null $allowed_domains
 * @property bool $auto_provision
 * @property bool $auto_approve
 */
class SocialSettings extends Model
{
    /**
     * Versioned, so a shape change to the cached payload does not have to
     * wait for a cache that never expires.
     */
    private const AVAILABLE_CACHE_KEY = 'identity.social.available.v1';

    protected $table = 'social_login_providers';

    protected $guarded = [];

    /**
     * Column defaults only apply on INSERT, so they never reach the
     * unsaved instance `for()` hands back for a provider nobody has
     * configured yet — these do.
     */
    protected $attributes = [
        'enabled' => false,
        'require_verified_email' => true,
        'auto_provision' => false,
        'auto_approve' => false,
    ];

    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
            'enabled' => 'boolean',
            'client_secret' => 'encrypted',
            'require_verified_email' => 'boolean',
            'auto_provision' => 'boolean',
            'auto_approve' => 'boolean',
        ];
    }

    public static function for(SocialProvider $provider): self
    {
        return static::query()->firstOrNew(['provider' => $provider->value]);
    }

    /**
     * Every provider, configured or not, in enum order — so the settings
     * screen always renders the same cards and a provider added to the
     * enum needs no seeding.
     *
     * @return array<string, self>
     */
    public static function allProviders(): array
    {
        $stored = static::query()->get()->keyBy(fn (self $row): string => $row->provider->value);

        $rows = [];

        foreach (SocialProvider::cases() as $provider) {
            $rows[$provider->value] = $stored->get($provider->value) ?? static::for($provider);
        }

        return $rows;
    }

    /**
     * The providers a visitor can actually see, as the sign-in buttons and
     * the Connected accounts screen need them.
     *
     * Cached because this is read on every request through Inertia's
     * shared props, and flushed on save. Only the key and the label — no
     * secret is ever written to the cache store, which is the mistake
     * MailConfigApplier and ExternalStorageConfigApplier both make with
     * their own credentials.
     *
     * @return list<array{provider: string, label: string}>
     */
    public static function available(): array
    {
        /** @var list<array{provider: string, label: string}> */
        return Cache::rememberForever(self::AVAILABLE_CACHE_KEY, function (): array {
            $available = [];

            foreach (self::allProviders() as $key => $settings) {
                if ($settings->usable()) {
                    $available[] = ['provider' => $key, 'label' => $settings->provider->label()];
                }
            }

            return $available;
        });
    }

    protected static function booted(): void
    {
        // Any write to this table can change the button row, including one
        // that turns a provider off.
        static::saved(fn () => Cache::forget(self::AVAILABLE_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::AVAILABLE_CACHE_KEY));
    }

    /**
     * Whether a sign-in may use this provider at all.
     *
     * Incomplete configuration behaves exactly as "switched off" rather
     * than throwing halfway through a redirect — the same rule
     * LdapSettings::usable() follows, and for the same reason: an
     * administrator can save a half-filled form.
     */
    public function usable(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if (! $this->filled('client_id') || ! $this->filled('client_secret')) {
            return false;
        }

        if ($this->provider->needsIssuerUrl() && ! $this->filled('issuer_url')) {
            return false;
        }

        if ($this->provider->needsTenantId() && ! $this->filled('tenant_id')) {
            return false;
        }

        return true;
    }

    /**
     * The issuer whose discovery document describes this provider.
     *
     * Microsoft's is derived from the tenant, which is what makes the
     * tenant a security control: the login endpoint we talk to is the
     * tenant's own, so a token minted by any other tenant cannot arrive
     * here at all.
     */
    public function issuer(): ?string
    {
        return match ($this->provider) {
            SocialProvider::Microsoft => $this->filled('tenant_id')
                ? 'https://login.microsoftonline.com/'.$this->tenant_id.'/v2.0'
                : null,
            SocialProvider::Oidc => $this->filled('issuer_url') ? rtrim((string) $this->issuer_url, '/') : null,
            default => null,
        };
    }

    /**
     * Whether an address is inside this provider's allow-list.
     *
     * Blank means any, which is the default. A public Google client with
     * auto-provisioning and no list here means the whole internet can
     * create an account — v1 had no equivalent and no way to say
     * otherwise.
     */
    public function allowsDomain(string $email): bool
    {
        $domains = $this->allowedDomains();

        if ($domains === []) {
            return true;
        }

        $at = strrpos($email, '@');

        if ($at === false) {
            return false;
        }

        return in_array(Str::lower(substr($email, $at + 1)), $domains, true);
    }

    /**
     * @return list<string>
     */
    public function allowedDomains(): array
    {
        $domains = array_map(
            fn (string $domain): string => Str::lower(trim(ltrim(trim($domain), '@'))),
            explode(',', (string) $this->allowed_domains),
        );

        return array_values(array_filter($domains, fn (string $domain): bool => $domain !== ''));
    }

    private function filled(string $attribute): bool
    {
        $value = $this->getAttribute($attribute);

        return is_string($value) && trim($value) !== '';
    }
}
