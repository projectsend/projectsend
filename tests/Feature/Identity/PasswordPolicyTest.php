<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Passwords\PasswordPolicy;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Validation\Rules\Password;

/**
 * The password policy is configurable, and the point of it being one
 * object behind `Password::defaults()` is that every field which sets a
 * password moves together. So these tests deliberately assert the same
 * minimum at several unrelated surfaces rather than trusting that
 * because one respects it, the rest do — that assumption is exactly what
 * would rot when somebody adds the fifteenth password field.
 */
beforeEach(function () {
    // Every HTTP test needs a staff account or EnsureSetupIsComplete
    // redirects the whole app to /setup.
    User::factory()->create();

    // The settings cache survives RefreshDatabase's rollback, so a test
    // asserting anything about a default has to state it rather than
    // inherit whatever the previous test left behind.
    app(Settings::class)->set(Setting::PasswordMinLength, 12);
    app(Settings::class)->set(Setting::PasswordRejectBreached, true);
});

function setMinimumLength(int $length): void
{
    app(Settings::class)->set(Setting::PasswordMinLength, $length);
}

/*
|--------------------------------------------------------------------------
| The rule the policy builds
|--------------------------------------------------------------------------
*/

test('the configured minimum is what Password::defaults() enforces', function () {
    setMinimumLength(16);

    $fails = fn (string $password) => Validator::make(
        ['password' => $password],
        ['password' => [Password::defaults()]],
    )->fails();

    expect($fails(str_repeat('a', 15)))->toBeTrue()
        ->and($fails(str_repeat('a', 16)))->toBeFalse();
});

// The floor is enforced twice on purpose: the settings form refuses the
// save, and the policy clamps on read. This covers the second, which is
// what protects an installation whose row was written by something other
// than the form — a seeder, a fixture, a hand-edited database.
test('a stored minimum below the floor is clamped rather than honoured', function () {
    setMinimumLength(4);

    expect(app(PasswordPolicy::class)->minLength())->toBe(PasswordPolicy::MIN_LENGTH);
});

test('a stored minimum above the ceiling is clamped rather than honoured', function () {
    setMinimumLength(9999);

    expect(app(PasswordPolicy::class)->minLength())->toBe(PasswordPolicy::MAX_LENGTH);
});

/*
|--------------------------------------------------------------------------
| The breach check
|--------------------------------------------------------------------------
*/

// Asserted on the rule object rather than over HTTP: uncompromised()
// calls haveibeenpwned for real, and no test may reach the network.
test('the breach check is attached in production when the setting is on', function () {
    app(Settings::class)->set(Setting::PasswordRejectBreached, true);
    app()->detectEnvironment(fn () => 'production');

    expect(rulesOf(app(PasswordPolicy::class)->rule()))->toContain('uncompromised');
});

test('the breach check is absent when the setting is off', function () {
    app(Settings::class)->set(Setting::PasswordRejectBreached, false);
    app()->detectEnvironment(fn () => 'production');

    expect(rulesOf(app(PasswordPolicy::class)->rule()))->not->toContain('uncompromised');
});

// The production gate predates the setting and has to survive it, or the
// suite starts making network calls the moment somebody flips the default.
test('the breach check never runs outside production, setting or not', function () {
    app(Settings::class)->set(Setting::PasswordRejectBreached, true);

    expect(app()->isProduction())->toBeFalse()
        ->and(rulesOf(app(PasswordPolicy::class)->rule()))->not->toContain('uncompromised');
});

/** The private state of a Password rule, so a test can see what it will check. */
function rulesOf(Password $rule): array
{
    $reflection = new ReflectionObject($rule);
    $found = [];

    foreach (['uncompromised', 'mixedCase', 'numbers', 'symbols', 'letters'] as $property) {
        if ($reflection->hasProperty($property) && $reflection->getProperty($property)->getValue($rule)) {
            $found[] = $property;
        }
    }

    return $found;
}

/*
|--------------------------------------------------------------------------
| The same minimum, at unrelated surfaces
|--------------------------------------------------------------------------
*/

test('client self-registration respects the configured minimum', function () {
    setMinimumLength(20);
    app(Settings::class)->set(Setting::ClientsCanRegister, true);

    $this->post('/register', [
        'name' => 'Someone',
        'email' => 'someone@example.test',
        'password' => str_repeat('a', 19),
        'password_confirmation' => str_repeat('a', 19),
    ])->assertSessionHasErrors('password');
});

test('a self-service password change respects the configured minimum', function () {
    setMinimumLength(20);
    $user = User::factory()->create();

    $this->actingAs($user)->put('/settings/password', [
        'current_password' => 'password',
        'password' => str_repeat('a', 19),
        'password_confirmation' => str_repeat('a', 19),
    ])->assertSessionHasErrors('password');
});

test('an administrator creating a client respects the configured minimum', function () {
    setMinimumLength(20);
    $admin = User::factory()->create();

    $this->actingAs($admin)->post('/clients', [
        'name' => 'A Client',
        'email' => 'a-client@example.test',
        'password' => str_repeat('a', 19),
        'password_confirmation' => str_repeat('a', 19),
    ])->assertSessionHasErrors('password');
});

test('the API respects the configured minimum', function () {
    setMinimumLength(20);
    $admin = User::factory()->create();
    $token = $admin->createToken('test', [Permission::CreateClients->value])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/clients', [
            'name' => 'A Client',
            'email' => 'api-client@example.test',
            'password' => str_repeat('a', 19),
        ])->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| The settings form
|--------------------------------------------------------------------------
*/

test('the security settings page saves both password settings', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->patch('/system/settings/security', [
        'two_factor_enforcement' => 'none',
        'password_min_length' => 20,
        'password_reject_breached' => false,
    ])->assertSessionHasNoErrors();

    expect(app(Settings::class)->get(Setting::PasswordMinLength))->toBe(20)
        ->and(app(Settings::class)->get(Setting::PasswordRejectBreached))->toBeFalse();
});

test('the security settings form refuses a minimum below the floor', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->patch('/system/settings/security', [
        'two_factor_enforcement' => 'none',
        'password_min_length' => PasswordPolicy::MIN_LENGTH - 1,
        'password_reject_breached' => true,
    ])->assertSessionHasErrors('password_min_length');
});

test('the security settings form refuses a minimum above the ceiling', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->patch('/system/settings/security', [
        'two_factor_enforcement' => 'none',
        'password_min_length' => PasswordPolicy::MAX_LENGTH + 1,
        'password_reject_breached' => true,
    ])->assertSessionHasErrors('password_min_length');
});
