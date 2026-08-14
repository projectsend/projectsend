<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PDOException;

/**
 * Cache::rememberForever() for the handful of settings that are read while
 * the application boots, rather than while it serves a request.
 *
 * The rule it exists to enforce: **booting must never require this
 * application's own database.** Serving a request may — nothing useful can
 * happen without the database anyway — but booting must not, because the
 * commands that create the database in the first place have to boot too.
 *
 * Without this, a manual install is impossible on any cache store that
 * lives in the database. `PlatformServiceProvider::boot()` applies the
 * stored mail and storage settings on every process start; on the database
 * cache store that read is itself a query against a `cache` table that
 * `php artisan migrate` has not created yet, so the very first command
 * INSTALL.md asks for — `php artisan key:generate` — dies with "Table
 * 'cache' doesn't exist", and so does `migrate`, the one command that would
 * fix it. The application cannot be installed at all. Same for a database
 * that simply is not reachable yet: `key:generate` should not need one.
 *
 * The failure is swallowed rather than cached, so nothing has to be flushed
 * once the install completes — the next boot simply reads for real.
 *
 * Deliberately NOT used by request-path settings readers (Settings,
 * EmailTemplateResolver). A database failure there should stay loud: those
 * run long after the install, where "quietly fell back to defaults" hides a
 * real outage instead of enabling a legitimate first run.
 */
final class BootSettingsCache
{
    private static bool $warned = false;

    /**
     * @template TValue of array<array-key, mixed>
     *
     * @param  Closure(): TValue  $read  Reads the real value from the database.
     * @param  TValue  $whenUnavailable  Returned as-is when the database cannot answer.
     * @return TValue
     */
    public static function rememberForever(string $key, Closure $read, array $whenUnavailable): array
    {
        try {
            return Cache::rememberForever($key, $read);
        } catch (PDOException $e) {
            // QueryException extends PDOException, so this covers both a
            // missing table and a connection that could not be opened.
            self::warnOnce($key, $e);

            return $whenUnavailable;
        }
    }

    /**
     * Once per process: an install that has not been migrated yet would
     * otherwise log this on every artisan command, and a genuine database
     * outage would log it on every request.
     */
    private static function warnOnce(string $key, PDOException $e): void
    {
        if (self::$warned) {
            return;
        }

        self::$warned = true;

        Log::warning('Falling back to default settings: the database could not be read during boot.', [
            'key' => $key,
            'reason' => $e->getMessage(),
        ]);
    }
}
