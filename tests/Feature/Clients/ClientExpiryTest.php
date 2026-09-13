<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\AccountConversion;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| Access ends when the date passes, without waiting for the sweep
|--------------------------------------------------------------------------
*/

test('a client whose date has passed cannot sign in, even while still marked active', function () {
    $client = User::factory()->client()->create(['email' => 'late@example.com']);
    $client->forceFill(['expires_at' => now()->subMinute()])->save();

    expect($client->refresh()->active)->toBeTrue();

    $this->post('/login', ['email' => 'late@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Your account has expired.']);

    $this->assertGuest();
});

test('a client whose date is still ahead signs in normally', function () {
    $client = User::factory()->client()->create(['email' => 'early@example.com']);
    $client->forceFill(['expires_at' => now()->addDay()])->save();

    $this->post('/login', ['email' => 'early@example.com', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($client);
});

test('an open session ends on the first request after the date passes', function () {
    $client = User::factory()->client()->create();
    $client->forceFill(['expires_at' => now()->addHour()])->save();

    $this->actingAs($client)->get('/my-files')->assertOk();

    $this->travel(2)->hours();

    $this->actingAs($client)->get('/my-files')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Your account has expired.']);
});

test('an expired account\'s API token stops working', function () {
    // Only clients are given a date, and tokens are staff-only — so this
    // is the gate proven on the one account type that can hold a token.
    $staff = User::factory()->create();
    $token = $staff->createToken('t', [Permission::ManageClients->value])->plainTextToken;
    $staff->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->withToken($token)->getJson('/api/v1/clients')->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| The hourly sweep
|--------------------------------------------------------------------------
*/

test('the sweep deactivates expired clients and nobody else', function () {
    $expired = User::factory()->client()->create(['name' => 'Gone']);
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();

    $future = User::factory()->client()->create();
    $future->forceFill(['expires_at' => now()->addDay()])->save();

    $never = User::factory()->client()->create();

    // Only client accounts are swept; a staff row carrying a stray date is
    // refused at the door by maySignIn(), but not switched off here.
    $staff = User::factory()->create();
    $staff->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('projectsend:expire-client-accounts')->assertSuccessful();

    expect($expired->refresh()->active)->toBeFalse()
        ->and($future->refresh()->active)->toBeTrue()
        ->and($never->refresh()->active)->toBeTrue()
        ->and($staff->refresh()->active)->toBeTrue();

    $entries = ActivityLog::query()->where('action', Action::ClientExpired)->get();
    expect($entries)->toHaveCount(1)
        ->and($entries->first()->context)->toBe(['name' => 'Gone', 'id' => $expired->id]);
});

test('the sweep does not log a client that is already inactive', function () {
    $client = User::factory()->client()->create(['active' => false]);
    $client->forceFill(['expires_at' => now()->subDay()])->save();

    $this->artisan('projectsend:expire-client-accounts')->assertSuccessful();

    expect(ActivityLog::query()->where('action', Action::ClientExpired)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Staff screens
|--------------------------------------------------------------------------
*/

test('a date set on the create screen means the end of that day where the creator is', function () {
    $this->admin->forceFill(['timezone' => 'America/Argentina/Buenos_Aires'])->save();

    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'Seasonal',
        'email' => 'seasonal@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
        'expires_at' => '2030-01-10',
    ])->assertSessionHasNoErrors();

    $client = User::query()->where('email', 'seasonal@example.com')->sole();

    // 23:59:59 in Buenos Aires (UTC-3) is 02:59:59 the next morning in UTC.
    expect($client->expires_at?->utc()->toDateTimeString())->toBe('2030-01-11 02:59:59');
});

test('a client cannot be created already expired', function () {
    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'Too Late',
        'email' => 'too-late@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
        'expires_at' => now()->subDays(2)->toDateString(),
    ])->assertSessionHasErrors('expires_at');

    expect(User::query()->where('email', 'too-late@example.com')->exists())->toBeFalse();
});

test('saving the edit screen without touching the date leaves the stored instant alone', function () {
    // Set by somebody in Tokyo, then re-saved by somebody in Buenos Aires
    // who only changed the name. Re-deriving the posted day would move the
    // expiry by twelve hours.
    $client = User::factory()->client()->create();
    $stored = Carbon::parse('2030-06-15 14:59:59', 'UTC');
    $client->forceFill(['expires_at' => $stored])->save();

    $this->admin->forceFill(['timezone' => 'America/Argentina/Buenos_Aires'])->save();

    $shown = null;
    $this->actingAs($this->admin)->get("/clients/{$client->id}")->assertInertia(
        function (AssertableInertia $page) use (&$shown) {
            $shown = $page->toArray()['props']['client']['expires_at'];
        },
    );

    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => 'Renamed',
        'email' => $client->email,
        'active' => true,
        'expires_at' => $shown,
    ])->assertSessionHasNoErrors();

    expect($client->refresh()->expires_at?->utc()->toDateTimeString())->toBe('2030-06-15 14:59:59')
        ->and($client->name)->toBe('Renamed');
});

test('an expired client cannot be switched back on without a new date', function () {
    $client = User::factory()->client()->create(['active' => false]);
    $client->forceFill(['expires_at' => now()->subDays(3)])->save();

    $shown = now()->subDays(3)->toDateString();

    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => $client->name,
        'email' => $client->email,
        'active' => true,
        'expires_at' => $shown,
    ])->assertSessionHasErrors('expires_at');

    expect($client->refresh()->active)->toBeFalse();

    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => $client->name,
        'email' => $client->email,
        'active' => true,
        'expires_at' => now()->addMonth()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($client->refresh()->active)->toBeTrue()
        ->and($client->hasExpired())->toBeFalse();
});

test('clearing the date removes the expiry', function () {
    $client = User::factory()->client()->create();
    $client->forceFill(['expires_at' => now()->addWeek()])->save();

    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => $client->name,
        'email' => $client->email,
        'active' => true,
        'expires_at' => '',
    ])->assertSessionHasNoErrors();

    expect($client->refresh()->expires_at)->toBeNull();
});

test('the list calls an expired client expired before the sweep has run', function () {
    $client = User::factory()->client()->create();
    $client->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->actingAs($this->admin)->get('/clients')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('clients.0.expired', true)
            ->where('clients.0.active', true),
    );
});

test('converting a client to staff drops the expiry', function () {
    $client = User::factory()->client()->create();
    $client->forceFill(['expires_at' => now()->addWeek()])->save();

    $roleId = (int) \App\Modules\Identity\Models\Role::query()->where('name', SystemRole::AccountManager->value)->value('id');

    app(AccountConversion::class)->toStaff($client, $roleId, [], 'a-brand-new-password-123');

    expect($client->refresh()->expires_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
*/

function expiryToken(User $admin): string
{
    return $admin->createToken('t', [
        Permission::ManageClients->value,
        Permission::CreateClients->value,
        Permission::EditClients->value,
    ])->plainTextToken;
}

test('the API creates, shows and clears an expiry', function () {
    $token = expiryToken($this->admin);

    $id = $this->withToken($token)->postJson('/api/v1/clients', [
        'name' => 'Api Client',
        'email' => 'api-expiry@example.com',
        'password' => 'super-secret-password',
        'expires_at' => '2030-03-01T12:00:00Z',
    ])->assertCreated()
        ->assertJsonPath('data.expires_at', '2030-03-01T12:00:00+00:00')
        ->json('data.id');

    $this->withToken($token)->patchJson("/api/v1/clients/{$id}", ['expires_at' => null])
        ->assertOk()
        ->assertJsonPath('data.expires_at', null);
});

test('the API refuses to reactivate an expired client without a new date', function () {
    $token = expiryToken($this->admin);
    $client = User::factory()->client()->create(['active' => false]);
    $client->forceFill(['expires_at' => now()->subDay()])->save();

    $this->withToken($token)->patchJson("/api/v1/clients/{$client->id}", ['active' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expires_at');

    $this->withToken($token)->patchJson("/api/v1/clients/{$client->id}", ['active' => true, 'expires_at' => null])
        ->assertOk()
        ->assertJsonPath('data.active', true);
});

test('an API rename is not refused over a date it did not send', function () {
    $token = expiryToken($this->admin);
    $client = User::factory()->client()->create();
    $client->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->withToken($token)->patchJson("/api/v1/clients/{$client->id}", ['name' => 'Just A Rename'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Just A Rename');
});

test('a JSON number where a date belongs is a validation error, not a 500', function () {
    // `date` accepts some numbers (20301231 parses as a calendar day)
    // and does not convert them, and DateInput::instant() is strictly
    // typed to a string. A form post is always text; a JSON body is not.
    $token = expiryToken($this->admin);
    $client = User::factory()->client()->create();

    $this->withToken($token)->postJson('/api/v1/clients', [
        'name' => 'Numeric',
        'email' => 'numeric@example.com',
        'password' => 'super-secret-password',
        'expires_at' => 20301231,
    ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');

    $this->withToken($token)->patchJson("/api/v1/clients/{$client->id}", ['expires_at' => 20301231])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expires_at');
});
