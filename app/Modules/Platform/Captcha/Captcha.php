<?php

declare(strict_types=1);

namespace App\Modules\Platform\Captcha;

use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * The one place that answers "is there a CAPTCHA, and whose is it?".
 *
 * Deliberately resolved through the container with no binding, so each
 * resolution re-reads config() — the same reason CapabilityRegistry is not
 * a singleton, and the reason a test can flip an edition or an env var
 * without fighting a cached instance.
 *
 * Half-configured is never an error here. Every path that cannot produce a
 * complete configuration returns null, which every caller reads as
 * "switched off" — the rule SocialSettings::usable() established, and the
 * reason an administrator can save a partly-filled form without taking
 * their own login offline.
 */
class Captcha
{
    private bool $resolvedOnce = false;

    private ?ResolvedCaptcha $resolved = null;

    public function __construct(
        private readonly Settings $settings,
        private readonly CapabilityRegistry $capabilities,
    ) {}

    /**
     * The configuration in force, or null when there is none.
     */
    public function active(): ?ResolvedCaptcha
    {
        if ($this->resolvedOnce) {
            return $this->resolved;
        }

        $this->resolvedOnce = true;

        return $this->resolved = $this->resolve();
    }

    public function provider(): ?CaptchaProvider
    {
        return $this->active()?->provider;
    }

    /**
     * Whether this installation is allowed to use the platform's own keys
     * at all — the one genuine edition difference in this feature.
     */
    public function managedKeysAvailable(): bool
    {
        return $this->capabilities->has(Capability::CaptchaManagedKeys)
            && $this->managedConfig() !== null;
    }

    /**
     * Whether an administrator has *chosen* the platform's keys, whether or
     * not that choice can currently be honoured.
     *
     * Two gates, on purpose. The setting is inert without the capability,
     * so a value of "managed" carried in by a v1 import, a hand-edited row
     * or an install that used to be cloud can never make a self-hosted
     * server reach for keys it does not have.
     */
    public function managedKeysSelected(): bool
    {
        return $this->capabilities->has(Capability::CaptchaManagedKeys)
            && $this->settings->get(Setting::CaptchaKeySource) === 'managed';
    }

    /**
     * Whether this form is protected on this installation.
     *
     * Both halves matter: a provider has to be configured, *and* this
     * particular form has to be switched on. An operator who does not take
     * self-registrations should be able to leave that form alone without
     * turning the whole feature off.
     */
    public function protects(CaptchaForm $form): bool
    {
        return $this->active() !== null
            && $this->settings->get($form->setting()) === true;
    }

    /**
     * What the browser is told: the provider, its public site key, and
     * which forms will ask for a token. Never the secret.
     *
     * Cached because this rides on every Inertia response, and flushed by
     * CaptchaSettings on any credential write. The *settings* half of the
     * key (provider, key source, the four form toggles) has exactly one
     * writer — CaptchaSettingsController — which flushes it explicitly.
     * Anything else that learns to write those settings must do the same.
     *
     * @return array{provider: string, site_key: string, forms: list<string>}|null
     */
    public function forDisplay(): ?array
    {
        /** @var array{provider: string, site_key: string, forms: list<string>}|null */
        return Cache::rememberForever(CaptchaSettings::DISPLAY_CACHE_KEY, function (): ?array {
            $active = $this->active();

            if ($active === null) {
                return null;
            }

            $forms = [];

            foreach (CaptchaForm::cases() as $form) {
                if ($this->settings->get($form->setting()) === true) {
                    $forms[] = $form->value;
                }
            }

            return [
                'provider' => $active->provider->value,
                'site_key' => $active->siteKey,
                'forms' => $forms,
            ];
        });
    }

    public static function forgetDisplayCache(): void
    {
        Cache::forget(CaptchaSettings::DISPLAY_CACHE_KEY);
    }

    private function resolve(): ?ResolvedCaptcha
    {
        // First, and deliberately ahead of everything else: an operator
        // holding a shell but no working login needs a one-line fix that
        // touches no database. v1's equivalent was an accident — it
        // disabled itself on any hostname containing ".local".
        if (config('projectsend.captcha.disabled') === true) {
            return null;
        }

        if ($this->managedKeysSelected()) {
            return $this->managedConfig();
        }

        return $this->ownConfig();
    }

    /**
     * The platform's own keys, from config and never from the tenant
     * database — so a database dump never contains our credential, and
     * rotating it is one fleet-wide change rather than a migration.
     */
    private function managedConfig(): ?ResolvedCaptcha
    {
        $provider = CaptchaProvider::tryFrom((string) config('projectsend.captcha.managed.provider'));
        $siteKey = trim((string) config('projectsend.captcha.managed.site_key'));
        $secretKey = trim((string) config('projectsend.captcha.managed.secret_key'));

        if ($provider === null || $siteKey === '' || $secretKey === '') {
            return null;
        }

        return new ResolvedCaptcha(
            $provider,
            $siteKey,
            $secretKey,
            (float) config('projectsend.captcha.managed.score_threshold', CaptchaSettings::DEFAULT_SCORE_THRESHOLD),
            managed: true,
        );
    }

    /**
     * This installation's own keys.
     *
     * When they are incomplete the answer is null — CAPTCHA is off, and
     * the settings screen says so. It deliberately does *not* fall back to
     * the platform's keys on cloud: an administrator who chose their own
     * keys made a decision, and quietly substituting ours would misreport
     * what is protecting them and leave their provider's dashboard empty.
     */
    private function ownConfig(): ?ResolvedCaptcha
    {
        $selected = $this->settings->get(Setting::CaptchaProvider);

        $provider = is_string($selected) ? CaptchaProvider::tryFrom($selected) : null;

        if ($provider === null) {
            return null;
        }

        $stored = CaptchaSettings::for($provider);

        if (! $stored->usable()) {
            return null;
        }

        return new ResolvedCaptcha(
            $provider,
            (string) $stored->site_key,
            (string) $stored->secret_key,
            $stored->threshold(),
            managed: false,
        );
    }
}
