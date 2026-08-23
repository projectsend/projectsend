<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Erasure\ErasureSchedule;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/*
|--------------------------------------------------------------------------
| Reusing a deleted account's email address (#1648)
|--------------------------------------------------------------------------
|
| The unique index on users.email spans soft-deleted rows on purpose: an
| email address is a login identity and stays reserved while the account
| holding it waits out its erasure grace period. What changed is that the
| wait now ends — every deletion path schedules the erasure — and that the
| staff screens explain the conflict instead of claiming the address "has
| already been taken" by nothing anyone can see.
|
*/

beforeEach(function () {
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::AccountErasureGraceDays, 30);
});

/** The client-creation payload every case here varies only the email of. */
function clientPayload(string $email): array
{
    return [
        'name' => 'Replacement',
        'email' => $email,
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ];
}

test('once the purge has run, the address can be registered again', function () {
    $client = User::factory()->client()->create(['email' => 'reuse@example.com']);
    $this->actingAs($this->admin)->delete("/clients/{$client->id}");

    // Still inside the grace period: reserved.
    $this->actingAs($this->admin)->post('/clients', clientPayload('reuse@example.com'))
        ->assertSessionHasErrors('email');

    $this->travel(31)->days();
    $this->artisan('projectsend:purge-erasures')->assertSuccessful();

    $this->actingAs($this->admin)->post('/clients', clientPayload('reuse@example.com'))
        ->assertSessionDoesntHaveErrors();

    expect(User::query()->where('email', 'reuse@example.com')->sole()->name)->toBe('Replacement');
});

test('the staff screens name the date a deleted account\'s address becomes available', function () {
    $client = User::factory()->client()->create(['email' => 'held@example.com']);
    $this->actingAs($this->admin)->delete("/clients/{$client->id}");

    $date = now()->addDays(30)->toFormattedDateString();

    $this->actingAs($this->admin)->post('/clients', clientPayload('held@example.com'))
        ->assertSessionHasErrors([
            'email' => "This email address belongs to a deleted account that is scheduled for permanent erasure. The address becomes available on {$date}. To free it sooner, erase the account with the projectsend:erase-account console command.",
        ]);
});

test('an account deleted before scheduling existed points at the erase command', function () {
    // A bare model delete is exactly what the application did before
    // erasure scheduling: trashed, no erase_after, reserved forever.
    $legacy = User::factory()->client()->create(['email' => 'legacy@example.com']);
    $legacy->delete();

    $this->actingAs($this->admin)->post('/clients', clientPayload('legacy@example.com'))
        ->assertSessionHasErrors([
            'email' => 'This email address belongs to a deleted account that has no erasure scheduled. Run the projectsend:erase-account console command to erase it and free the address.',
        ]);
});

test('a living account keeps the stock message', function () {
    User::factory()->client()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->admin)->post('/clients', clientPayload('taken@example.com'))
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);
});

test('public registration stays generic about deleted accounts', function () {
    // Whether an address ever had an account here is nobody's business at
    // the public form: the deleted-account explanation is for staff only.
    app(Settings::class)->set(Setting::ClientsCanRegister, true);
    app(Settings::class)->set(Setting::CaptchaProvider, 'none');
    Captcha::forgetDisplayCache();

    // Built directly rather than through the admin screen: /register is
    // behind the guest middleware, so this test must never sign anyone in.
    $client = User::factory()->client()->create(['email' => 'gone@example.com']);
    app(ErasureSchedule::class)->apply($client);
    $client->delete();

    $this->post('/register', clientPayload('gone@example.com'))
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);
});

test('the api reports the reserved address as a validation error on email', function () {
    $token = $this->admin->createToken('t', [
        Permission::ManageClients->value,
        Permission::CreateClients->value,
    ])->plainTextToken;

    $client = User::factory()->client()->create(['email' => 'held@example.com']);
    $this->actingAs($this->admin)->delete("/clients/{$client->id}");

    $date = now()->addDays(30)->toFormattedDateString();

    $this->withToken($token)
        ->postJson('/api/v1/clients', [
            'name' => 'Replacement',
            'email' => 'held@example.com',
            'password' => 'super-secret-password',
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.email.0',
            "This email address belongs to a deleted account that is scheduled for permanent erasure. The address becomes available on {$date}. To free it sooner, erase the account with the projectsend:erase-account console command.",
        );
});

test('the admin console command explains the reserved address too', function () {
    $client = User::factory()->client()->create(['email' => 'held@example.com']);
    $this->actingAs($this->admin)->delete("/clients/{$client->id}");

    $this->artisan('projectsend:admin', [
        '--name' => 'New Admin',
        '--email' => 'held@example.com',
        '--password' => 'super-secret-password',
    ])
        ->expectsOutputToContain('scheduled for permanent erasure')
        ->assertFailed();
});
