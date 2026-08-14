<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\Ldap\LdapDirectory;
use App\Modules\Identity\Ldap\LdapSettings;
use Tests\Support\FakeLdapDirectory;

/**
 * A password stored under weaker hashing than this installation now uses
 * has to be upgraded the next time its owner signs in.
 *
 * Laravel does this inside SessionGuard::attempt(), which this
 * application's login form does not use — it verifies with
 * Auth::validate() and starts the session with Auth::login(), and
 * neither re-hashes. Every account the v1 migration carries across
 * arrives at bcrypt cost 8, so without the explicit call in
 * LoginRequest::upgradeHashIfStale() they would stay there permanently.
 */
beforeEach(function () {
    User::factory()->create();
});

/** The work factor baked into a bcrypt digest, e.g. 8 for `$2y$08$…`. */
function bcryptCost(string $hash): int
{
    return (int) explode('$', $hash)[2];
}

/**
 * Move the work factor this installation hashes at.
 *
 * Setting `hashing.bcrypt.rounds` alone is not enough: the hasher is
 * resolved out of the container once and keeps the rounds it was built
 * with, so the config change would be read by nothing and the test would
 * silently measure phpunit.xml's BCRYPT_ROUNDS=4 instead.
 */
function hashAtRounds(int $rounds): void
{
    config(['hashing.bcrypt.rounds' => $rounds]);
    Hash::driver('bcrypt')->setRounds($rounds);
}

/**
 * Store a digest at an arbitrary cost, straight through the query
 * builder.
 *
 * It has to bypass the model: the `hashed` cast runs
 * `Hash::verifyConfiguration()`, which rejects any digest whose work
 * factor differs from the configured one — precisely the state under
 * test. This is also how the v1 migration writes them
 * (`UsersPhase::insertGetId()`), so the fixture matches reality.
 */
function userHashedAt(int $cost, string $plaintext = 'password', array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    DB::table('users')->where('id', $user->id)->update([
        'password' => password_hash($plaintext, PASSWORD_BCRYPT, ['cost' => $cost]),
    ]);

    return $user->refresh();
}

test('a password hashed below the configured cost is upgraded on login', function () {
    hashAtRounds(6);

    $user = userHashedAt(4);
    expect(bcryptCost($user->password))->toBe(4);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect(bcryptCost($user->refresh()->password))->toBe(6);
});

// The v1 migration's exact shape: cost 8 from `password_hash($p,
// PASSWORD_DEFAULT, ['cost' => 8])`, landing in an installation at 12.
test('a migrated v1 hash moves to this installation cost', function () {
    hashAtRounds(12);

    $user = userHashedAt(8, attributes: ['email' => 'migrated@example.test']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    expect(bcryptCost($user->refresh()->password))->toBe(12);
});

test('a password already at the configured cost is left alone', function () {
    hashAtRounds(6);

    $user = userHashedAt(6);
    $before = $user->password;

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    expect($user->refresh()->password)->toBe($before);
});

test('a failed login never rewrites the stored hash', function () {
    hashAtRounds(12);

    $user = userHashedAt(4);
    $before = $user->password;

    $this->post('/login', ['email' => $user->email, 'password' => 'not-the-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($user->refresh()->password)->toBe($before);
});

/**
 * The one case this must never touch.
 *
 * On the directory branch the submitted plaintext is the *LDAP* password
 * and the local hash is a `Str::password(64)` placeholder nobody holds.
 * Re-hashing there would write the directory credential into the local
 * column and mint a second way into the account — one that keeps working
 * after LDAP is switched off.
 */
test('an LDAP login leaves the local hash untouched', function () {
    hashAtRounds(12);

    LdapSettings::current()->forceFill([
        'active' => true,
        'host' => 'ldap.example.test',
        'base_dn' => 'dc=example,dc=test',
    ])->save();

    $this->swap(LdapDirectory::class, new FakeLdapDirectory([
        'directory@example.test' => ['password' => 'directory-pass'],
    ]));

    $client = User::factory()->client()->create([
        'email' => 'directory@example.test',
        'auth_source' => AuthSource::Ldap,
    ]);

    // Stand in for the placeholder a provisioned account carries: a
    // digest at a stale cost that nobody knows the plaintext of, so the
    // only way it could be rewritten is by this login.
    DB::table('users')->where('id', $client->id)->update([
        'password' => password_hash('nobody-holds-this', PASSWORD_BCRYPT, ['cost' => 4]),
    ]);

    $before = $client->refresh()->password;

    $this->post('/login', ['email' => 'directory@example.test', 'password' => 'directory-pass'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($client);
    expect($client->refresh()->password)->toBe($before)
        ->and(bcryptCost($client->password))->toBe(4);
});
