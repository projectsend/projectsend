<?php

declare(strict_types=1);

use App\Modules\Platform\Settings\BootSettingsCache;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\MailConfigApplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Booting must not require this application's own database.
 *
 * The commands that create the database have to boot too, so anything
 * PlatformServiceProvider::boot() does has to survive a database with no
 * tables in it — or no database at all. Before this was true, a manual
 * install was impossible on the database cache store: `key:generate` died
 * on a missing `cache` table, and so did `migrate`, the one command that
 * would have created it.
 */

/** Puts the cache on the database, then takes the table away. */
function cacheOnAMissingTable(): void
{
    config(['cache.default' => 'database']);
    Cache::purge('database');
    Schema::dropIfExists('cache');
}

test('the mail settings applier survives a cache table that does not exist yet', function () {
    $original = config('mail.mailers.smtp.host');

    cacheOnAMissingTable();

    app(MailConfigApplier::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe($original);
});

test('the storage settings applier survives a cache table that does not exist yet', function () {
    $original = config('filesystems.disks.files_external.bucket');

    cacheOnAMissingTable();

    app(ExternalStorageConfigApplier::class)->apply();

    expect(config('filesystems.disks.files_external.bucket'))->toBe($original);
});

test('external storage is inactive rather than fatal when the cache cannot be read', function () {
    cacheOnAMissingTable();

    expect(app(ExternalStorageConfigApplier::class)->isActive())->toBeFalse();
});

test('both appliers survive a database that cannot be reached at all', function () {
    // What `php artisan key:generate` faces on a server where .env names the
    // database before anyone has created it — or where MySQL is not up yet.
    // Only the cache store is pointed at the dead connection: the goal is to
    // reproduce the failure these appliers actually hit, without tearing down
    // the connection the test itself is running in.
    config([
        'database.connections.unreachable' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'projectsend',
            'username' => 'projectsend',
            'password' => 'secret',
        ],
        'cache.stores.database.connection' => 'unreachable',
        'cache.default' => 'database',
    ]);
    Cache::purge('database');

    app(MailConfigApplier::class)->apply();
    app(ExternalStorageConfigApplier::class)->apply();
})->throwsNoExceptions();

test('the fallback is not cached, so the real value is read once the install completes', function () {
    cacheOnAMissingTable();

    expect(BootSettingsCache::rememberForever('boot.test', fn (): array => ['real' => true], ['real' => false]))
        ->toBe(['real' => false]);

    // Same key, working cache: the failed read must not have poisoned it.
    config(['cache.default' => 'array']);

    expect(BootSettingsCache::rememberForever('boot.test', fn (): array => ['real' => true], ['real' => false]))
        ->toBe(['real' => true]);
});

test('a working cache still reaches the database', function () {
    // The guard must not have turned every read into the default.
    expect(BootSettingsCache::rememberForever('boot.test.live', fn (): array => ['read' => 'yes'], ['read' => 'no']))
        ->toBe(['read' => 'yes']);
});
