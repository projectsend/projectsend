<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\AccountLookup;

/**
 * Which account an address names.
 *
 * **This file cannot, on its own, prove the bug it was written for.** The
 * suite runs on SQLite (`phpunit.xml`), whose `=` is byte-exact, so the
 * collation that folds `é` into `e` does not exist here and the takeover
 * never reproduces. A test written the obvious way — seed an account,
 * sign in through OIDC with the accented address, assert refused — passes
 * on unfixed code, for the wrong reason.
 *
 * So the split is deliberate. These pin the *comparison*, which is where
 * the decision now lives and which is driver-independent.
 * AccountLookupCollationTest beside this one exercises the whole chain and
 * skips unless the connection is MySQL; it is the only one that can see
 * the original defect, and it has to be run deliberately.
 *
 * Reported as GHSA-wgxf-v8cr-37mj.
 */
beforeEach(function () {
    $this->lookup = app(AccountLookup::class);
});

test('an accented domain is a different address', function () {
    // The whole finding in one line. éxample.com is xn--xample-9ua.com,
    // a name somebody else can register and honestly prove they own.
    expect($this->lookup->isSameAddress('administrator@example.com', 'administrator@éxample.com'))
        ->toBeFalse();
});

test('case is still folded, because that is a real requirement', function () {
    // Addresses are stored lowercased and a provider may send any case.
    // Folding case without folding accents is the line being drawn.
    expect($this->lookup->isSameAddress('admin@example.com', 'ADMIN@Example.com'))->toBeTrue();
});

test('surrounding whitespace does not make it a different mailbox', function () {
    expect($this->lookup->isSameAddress('admin@example.com', '  admin@example.com '))->toBeTrue();
});

test('a null on either side matches nothing', function () {
    expect($this->lookup->isSameAddress(null, 'admin@example.com'))->toBeFalse()
        ->and($this->lookup->isSameAddress('admin@example.com', null))->toBeFalse();
});

test('other confusables are refused too', function (string $lookalike) {
    expect($this->lookup->isSameAddress('admin@example.com', $lookalike))->toBeFalse();
})->with([
    'accented o' => ['admin@exämple.com'],
    'cyrillic a' => ['аdmin@example.com'],
    'trailing dot' => ['admin@example.com.'],
    'different tld' => ['admin@example.co'],
]);

/*
|--------------------------------------------------------------------------
| The lookup itself
|--------------------------------------------------------------------------
|
| These pass on SQLite whether or not the fix is present, and are here for
| the ordinary behaviour rather than for the defect.
*/

test('it finds the account that holds the address', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    expect($this->lookup->byEmail('owner@example.com')?->id)->toBe($user->id);
});

test('it finds nothing for an address nobody holds', function () {
    User::factory()->create(['email' => 'owner@example.com']);

    expect($this->lookup->byEmail('somebody@example.com'))->toBeNull();
});

test('a deleted account is out of sight unless asked for', function () {
    // A deleted account keeps its address until erasure, which is what
    // AvailableEmailRule is built on — so the erasure command has to be
    // able to reach it and the sign-in paths must not.
    $user = User::factory()->create(['email' => 'gone@example.com']);
    $user->delete();

    expect($this->lookup->byEmail('gone@example.com'))->toBeNull()
        ->and($this->lookup->byEmail('gone@example.com', withTrashed: true)?->id)->toBe($user->id);
});
