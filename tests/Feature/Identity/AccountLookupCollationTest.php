<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\AccountLookup;
use Illuminate\Support\Facades\DB;

/**
 * The one test that can see GHSA-wgxf-v8cr-37mj.
 *
 * The defect was never in PHP: `where('email', $address)` asked the
 * database what equality means, and the documented collation —
 * `utf8mb4_unicode_ci`, in INSTALL.md and in config/database.php — folds
 * accents. `administrator@example.com` and `administrator@éxample.com`
 * compare equal, and those are two different domains: the second is
 * `xn--xample-9ua.com`, which somebody else can own and honestly verify at
 * an OIDC provider.
 *
 * **The suite runs on SQLite, whose `=` is byte-exact, so none of this
 * exists there.** That is why the vulnerability lived through six releases
 * with a green suite, and why this file skips rather than passing: a test
 * that silently proves nothing is worse than one that says it did not run.
 *
 * To run it:
 *
 *   docker compose exec -T -e DB_CONNECTION=mysql -e DB_HOST=db \
 *     -e DB_DATABASE=collation_check -e DB_USERNAME=root -e DB_PASSWORD=root \
 *     app vendor/bin/pest tests/Feature/Identity/AccountLookupCollationTest.php
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        test()->markTestSkipped('Needs MySQL: SQLite compares byte-exactly and cannot show a collation fault.');
    }
});

test('the database really does fold the accent', function () {
    // Asserted rather than assumed, because everything below is only
    // meaningful if this is true of the connection actually in use. An
    // installation on utf8mb4_bin has never had the bug.
    $folds = DB::selectOne("SELECT _utf8mb4'a@example.com' COLLATE utf8mb4_unicode_ci = _utf8mb4'a@éxample.com' COLLATE utf8mb4_unicode_ci AS c")->c;

    expect((int) $folds)->toBe(1);
});

test('the raw query matches an address it should not', function () {
    // The defect itself, shown rather than described: this is exactly what
    // SocialAuthenticator used to run.
    User::factory()->create(['email' => 'administrator@example.com']);

    $found = User::query()->where('email', 'administrator@éxample.com')->first();

    expect($found)->not->toBeNull('the collation no longer folds — the rest of this file is moot');
});

test('the lookup refuses the address the collation would have accepted', function () {
    // The fix. Same database, same collation, same row present.
    User::factory()->create(['email' => 'administrator@example.com']);

    expect(app(AccountLookup::class)->byEmail('administrator@éxample.com'))->toBeNull();
});

test('and still finds the real one', function () {
    $user = User::factory()->create(['email' => 'administrator@example.com']);

    expect(app(AccountLookup::class)->byEmail('administrator@example.com')?->id)->toBe($user->id)
        ->and(app(AccountLookup::class)->byEmail('ADMINISTRATOR@Example.com')?->id)->toBe($user->id);
});
