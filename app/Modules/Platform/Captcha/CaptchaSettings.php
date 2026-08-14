<?php

declare(strict_types=1);

namespace App\Modules\Platform\Captcha;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One provider's credentials.
 *
 * Shaped after SocialSettings, including the part that matters most:
 * `secret_key` carries an `'encrypted'` cast, so a database dump does not
 * hand over the credential that lets someone forge verifications against
 * this installation's site key. v1 stored all six of these in plain text
 * and echoed them into the settings form's HTML `value=`.
 *
 * @property CaptchaProvider $provider
 * @property string|null $site_key
 * @property string|null $secret_key
 * @property float|null $score_threshold
 */
class CaptchaSettings extends Model
{
    /**
     * Versioned, so a shape change to the cached payload does not have to
     * wait for a cache that never expires.
     */
    public const DISPLAY_CACHE_KEY = 'platform.captcha.display.v1';

    /**
     * reCAPTCHA v3's own documented default. Google returns 0.9 for
     * ordinary humans and 0.1 for obvious bots, so the midpoint is a
     * reasonable place to stand.
     */
    public const DEFAULT_SCORE_THRESHOLD = 0.5;

    protected $table = 'captcha_providers';

    protected $guarded = [];

    /**
     * Column defaults only apply on INSERT, so they never reach the
     * unsaved instance `for()` hands back for a provider nobody has
     * configured yet — this does.
     */
    protected $attributes = [
        'score_threshold' => self::DEFAULT_SCORE_THRESHOLD,
    ];

    protected function casts(): array
    {
        return [
            'provider' => CaptchaProvider::class,
            'secret_key' => 'encrypted',
            'score_threshold' => 'float',
        ];
    }

    public static function for(CaptchaProvider $provider): self
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

        foreach (CaptchaProvider::cases() as $provider) {
            $rows[$provider->value] = $stored->get($provider->value) ?? static::for($provider);
        }

        return $rows;
    }

    /**
     * Whether this provider can actually be used.
     *
     * Incomplete configuration behaves exactly as "switched off" rather
     * than throwing on the first visitor to reach a login form — the same
     * rule SocialSettings::usable() and LdapSettings::usable() follow, and
     * for the same reason: an administrator can save a half-filled form.
     */
    public function usable(): bool
    {
        return $this->filled('site_key') && $this->filled('secret_key');
    }

    public function threshold(): float
    {
        // Not `?:` — 0.0 is a meaningful threshold ("accept every score"),
        // and v1's `!empty()` check silently turned it back into 0.5.
        return $this->score_threshold ?? self::DEFAULT_SCORE_THRESHOLD;
    }

    protected static function booted(): void
    {
        // Any write to this table can change what the browser is told,
        // including one that clears a key.
        static::saved(fn () => Cache::forget(self::DISPLAY_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::DISPLAY_CACHE_KEY));
    }

    private function filled(string $attribute): bool
    {
        $value = $this->getAttribute($attribute);

        return is_string($value) && trim($value) !== '';
    }
}
