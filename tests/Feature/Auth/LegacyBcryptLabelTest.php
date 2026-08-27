<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * An account migrated from v1 can carry a bcrypt digest labelled `$2a$`
 * or `$2b$` instead of `$2y$`. All three name the same algorithm and
 * `password_verify()` reads any of them, but Laravel's hasher asks
 * `password_get_info()` first, gets "unknown", and throws before it looks
 * at the password — so the sign-in form answers 500 rather than either
 * letting the person in or turning them away.
 *
 * That was projectsend/projectsend#1706: every migrated account on the
 * reporter's installation hit an error page. The v1 migration tool now
 * relabels on import; the migration exercised here repairs the
 * installations that were migrated before it did.
 */
beforeEach(function () {
    // The app redirects everything to /setup until a staff user exists.
    User::factory()->create();
});

/** Run the repair migration on its own, against rows this test planted. */
function runBcryptRelabelMigration(): void
{
    (require base_path('database/migrations/2026_09_06_090000_relabel_bcrypt_hashes_the_hasher_will_not_read.php'))->up();
}

/** Store `$password` on `$user` under a bcrypt label other than `$2y$`. */
function storeUnder(User $user, string $label, string $password): string
{
    $digest = $label.substr(password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), 4);

    DB::table('users')->where('id', $user->id)->update(['password' => $digest]);

    return $digest;
}

it('errors at the login form on a digest the hasher will not read', function () {
    // The reported bug, reproduced. Asserted so that nobody "simplifies"
    // the migration away on the grounds that bcrypt is bcrypt.
    $user = User::factory()->create();
    storeUnder($user, '$2a$', 'the-password-they-had');

    $this->withoutExceptionHandling();

    expect(fn () => $this->post('/login', [
        'email' => $user->email,
        'password' => 'the-password-they-had',
    ]))->toThrow(RuntimeException::class, 'This password does not use the Bcrypt algorithm.');
});

it('relabels the affected digests and leaves the password itself alone', function () {
    foreach (['$2a$', '$2b$'] as $label) {
        $user = User::factory()->create();
        $before = storeUnder($user, $label, 'the-password-they-had');

        runBcryptRelabelMigration();

        $after = DB::table('users')->where('id', $user->id)->value('password');

        expect($after)->toStartWith('$2y$')
            // Only the four label bytes moved.
            ->and(substr((string) $after, 4))->toBe(substr($before, 4));
    }
});

it('lets a repaired account sign in with the password it already had', function () {
    $user = User::factory()->create();
    storeUnder($user, '$2a$', 'the-password-they-had');

    runBcryptRelabelMigration();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'the-password-they-had',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

it('still turns away a repaired account given the wrong password', function () {
    $user = User::factory()->create();
    storeUnder($user, '$2b$', 'the-password-they-had');

    runBcryptRelabelMigration();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'not-the-password-they-had',
    ]);

    $this->assertGuest();
});

it('leaves a $2x$ digest alone', function () {
    // $2x$ is not another spelling of $2y$ — it asks for the old, broken
    // handling of bytes above 127 on purpose, so relabelling it would
    // lock out anybody whose password is not plain ASCII.
    $user = User::factory()->create();
    $before = storeUnder($user, '$2x$', 'contraseña');

    runBcryptRelabelMigration();

    expect(DB::table('users')->where('id', $user->id)->value('password'))->toBe($before);
});

it('leaves a malformed digest alone rather than renaming it to no effect', function () {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['password' => '$2a$08$truncated']);

    runBcryptRelabelMigration();

    expect(DB::table('users')->where('id', $user->id)->value('password'))->toBe('$2a$08$truncated');
});

it('leaves an account that was always v2 untouched', function () {
    $user = User::factory()->create();
    $before = DB::table('users')->where('id', $user->id)->value('password');

    runBcryptRelabelMigration();

    expect(DB::table('users')->where('id', $user->id)->value('password'))->toBe($before);
});
