<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Typed access to application settings. All overrides load with one
 * cached query; reads never hit the database afterwards. Writes refresh
 * the cache.
 */
class Settings
{
    private const CACHE_KEY = 'platform.settings';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $overrides = null;

    /**
     * @return string|bool|int|array<array-key, mixed>|null
     */
    public function get(Setting $setting): string|bool|int|array|null
    {
        $overrides = $this->overrides();

        if (! array_key_exists($setting->value, $overrides)) {
            return $setting->default();
        }

        return $this->cast($setting, $overrides[$setting->value]);
    }

    /**
     * @param  string|bool|int|array<array-key, mixed>|null  $value
     */
    public function set(Setting $setting, string|bool|int|array|null $value): void
    {
        StoredSetting::query()->updateOrCreate(
            ['key' => $setting->value],
            ['value' => $this->cast($setting, $value)],
        );

        $this->flush();
    }

    public function flush(): void
    {
        $this->overrides = null;

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function overrides(): array
    {
        return $this->overrides ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => StoredSetting::query()->pluck('value', 'key')->all(),
        );
    }

    /**
     * @return string|bool|int|array<array-key, mixed>|null
     */
    private function cast(Setting $setting, mixed $value): string|bool|int|array|null
    {
        if ($value === null) {
            return null;
        }

        return match ($setting->type()) {
            SettingType::String => is_scalar($value)
                ? (string) $value
                : throw new InvalidArgumentException("Setting [{$setting->value}] expects a string."),
            SettingType::Boolean => (bool) $value,
            SettingType::Integer => is_numeric($value)
                ? (int) $value
                : throw new InvalidArgumentException("Setting [{$setting->value}] expects an integer."),
            SettingType::Json => is_array($value)
                ? $value
                : throw new InvalidArgumentException("Setting [{$setting->value}] expects an array."),
        };
    }
}
