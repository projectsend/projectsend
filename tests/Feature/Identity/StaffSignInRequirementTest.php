<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Social\SocialAccount;
use App\Modules\Identity\Social\SocialGateway;
use App\Modules\Identity\Social\SocialIdentity;
use App\Modules\Identity\Social\SocialProvider;
use App\Modules\Identity\Social\SocialSettings;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeSocialGateway;

/*
|--------------------------------------------------------------------------
| "Staff sign in through this provider, and nothing else" (#1828)
|--------------------------------------------------------------------------
|
| Switching somebody off at the organisation's identity provider should be
| enough to keep them out. It is not while a ProjectSend password of theirs
| still signs in, or can be set again from their mailbox. Clients keep
| their passwords throughout.
*/

beforeEach(function () {
    $this->admin = User::factory()->create(['email' => 'admin@example.test', 'password' => Hash::make('admin-password')]);
    $this->staff = User::factory()->create(['email' => 'staff@example.test', 'password' => Hash::make('staff-password')]);
    $this->client = User::factory()->client()->create(['email' => 'client@example.test', 'password' => Hash::make('client-password')]);

    $this->provider = SocialProvider::Microsoft;
    staffSsoProvider($this->provider);
    staffSsoConnect($this->admin, $this->provider, 'admin-subject');
    staffSsoConnect($this->staff, $this->provider, 'staff-subject');
});

function staffSsoProvider(SocialProvider $provider, bool $enabled = true): SocialSettings
{
    $settings = SocialSettings::for($provider);
    $settings->forceFill([
        'provider' => $provider->value,
        'enabled' => $enabled,
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'tenant_id' => $provider->needsTenantId() ? 'tenant-abc' : null,
        'issuer_url' => $provider->needsIssuerUrl() ? 'https://idp.example.test' : null,
    ])->save();

    return $settings;
}

function staffSsoConnect(User $user, SocialProvider $provider, string $subject): void
{
    SocialAccount::query()->create([
        'user_id' => $user->id,
        'provider' => $provider->value,
        'provider_user_id' => $subject,
        'email' => $user->email,
    ]);
}

function staffSsoRequire(?SocialProvider $provider): void
{
    app(Settings::class)->set(Setting::StaffSignInProvider, $provider->value ?? '');
}

function staffSsoGateway(?SocialIdentity $identity = null): FakeSocialGateway
{
    $fake = new FakeSocialGateway($identity);
    test()->swap(SocialGateway::class, $fake);

    return $fake;
}

/*
| Signing in
*/

test('with the requirement on, a staff password is refused exactly like a wrong one', function () {
    staffSsoRequire($this->provider);

    $right = $this->post('/login', ['email' => 'staff@example.test', 'password' => 'staff-password']);
    $right->assertSessionHasErrors(['email' => __('auth.failed')]);
    expect(auth()->check())->toBeFalse();

    $wrong = $this->post('/login', ['email' => 'staff@example.test', 'password' => 'not-it']);
    $wrong->assertSessionHasErrors(['email' => __('auth.failed')]);
});

test('clients keep signing in with their password', function () {
    staffSsoRequire($this->provider);

    $this->post('/login', ['email' => 'client@example.test', 'password' => 'client-password'])->assertSessionHasNoErrors();

    expect(auth()->id())->toBe($this->client->id);
});

test('staff passwords work while nothing is required', function () {
    staffSsoRequire(null);

    $this->post('/login', ['email' => 'staff@example.test', 'password' => 'staff-password'])->assertSessionHasNoErrors();

    expect(auth()->id())->toBe($this->staff->id);
});

test('a provider that is switched off lets staff passwords back in', function () {
    // Enforced only while the provider can be used, so a broken provider
    // never leaves nobody able to sign in.
    staffSsoRequire($this->provider);
    staffSsoProvider($this->provider, enabled: false);

    $this->post('/login', ['email' => 'staff@example.test', 'password' => 'staff-password'])->assertSessionHasNoErrors();

    expect(auth()->id())->toBe($this->staff->id);
});

test('staff still sign in through the required provider', function () {
    staffSsoRequire($this->provider);
    staffSsoGateway(new SocialIdentity($this->provider, 'staff-subject', 'staff@example.test', true, 'Staff'));

    $this->get(route('social.redirect', ['provider' => $this->provider->value]));
    $this->get(route('social.callback', ['provider' => $this->provider->value]))->assertRedirect();

    expect(auth()->id())->toBe($this->staff->id);
});

test('the sign-in page names the provider staff use', function () {
    staffSsoRequire($this->provider);

    $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('staffSignInProvider', $this->provider->label()));
});

/*
| Getting a password back
*/

test('a reset link cannot give staff a password', function () {
    staffSsoRequire($this->provider);
    $token = Password::broker()->createToken($this->staff);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => 'staff@example.test',
        'password' => 'a-brand-new-password-1',
        'password_confirmation' => 'a-brand-new-password-1',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('a-brand-new-password-1', $this->staff->fresh()->password))->toBeFalse();
});

test('a client can still reset their password', function () {
    staffSsoRequire($this->provider);
    $token = Password::broker()->createToken($this->client);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => 'client@example.test',
        'password' => 'a-brand-new-password-1',
        'password_confirmation' => 'a-brand-new-password-1',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('a-brand-new-password-1', $this->client->fresh()->password))->toBeTrue();
});

test('asking for a reset link answers staff and clients alike', function () {
    // The refusal comes when the link is used, by its holder only. Here it
    // would tell a stranger which addresses are staff.
    staffSsoRequire($this->provider);

    $staff = $this->post(route('password.email'), ['email' => 'staff@example.test']);
    $client = $this->post(route('password.email'), ['email' => 'client@example.test']);

    expect($staff->getSession()->get('status'))->toBe($client->getSession()->get('status'));
});

test('staff cannot set a password from their own settings either', function () {
    staffSsoRequire($this->provider);
    $this->actingAs($this->staff);

    $this->put(route('password.update'), [
        'current_password' => 'staff-password',
        'password' => 'a-brand-new-password-1',
        'password_confirmation' => 'a-brand-new-password-1',
    ])->assertForbidden();

    $this->get(route('password.edit'))->assertInertia(fn (AssertableInertia $page) => $page->where('required_provider', $this->provider->label()));
});

/*
| Confirming it is you
*/

test('the old password no longer confirms anything', function () {
    staffSsoRequire($this->provider);
    $this->actingAs($this->staff);

    $this->post(route('password.confirm.store'), ['password' => 'staff-password'])->assertSessionHasErrors('password');

    expect(session('auth.password_confirmed_at'))->toBeNull();
});

test('the confirmation dialog offers the provider instead of a password', function () {
    staffSsoRequire($this->provider);
    $this->actingAs($this->staff);

    $this->withHeaders(['X-Inertia' => 'true'])
        ->patch(route('profile.update'), ['name' => $this->staff->name, 'email' => 'new-address@example.test'])
        ->assertStatus(423)
        ->assertJsonPath('has_password', false)
        ->assertJsonPath('confirm_with.provider', $this->provider->value);

    expect($this->staff->fresh()->email)->toBe('staff@example.test');
});

test('signing in again at the provider confirms, and asks the provider to make them sign in', function () {
    staffSsoRequire($this->provider);
    $gateway = staffSsoGateway(new SocialIdentity($this->provider, 'staff-subject', 'staff@example.test', true, 'Staff'));
    $this->actingAs($this->staff);

    $this->post(route('password.confirm.provider', ['provider' => $this->provider->value]));
    expect($gateway->lastReauthenticate)->toBeTrue();

    $this->get(route('social.callback', ['provider' => $this->provider->value]))->assertRedirect();
    expect(session('auth.password_confirmed_at'))->not->toBeNull();

    // And what asked for it now goes through.
    $this->withHeaders(['X-Inertia' => 'true'])
        ->patch(route('profile.update'), ['name' => $this->staff->name, 'email' => 'new-address@example.test']);

    expect($this->staff->fresh()->email)->toBe('new-address@example.test');
});

test('somebody else signed in at the provider does not confirm for this session', function () {
    staffSsoRequire($this->provider);
    staffSsoGateway(new SocialIdentity($this->provider, 'admin-subject', 'admin@example.test', true, 'Admin'));
    $this->actingAs($this->staff);

    $this->post(route('password.confirm.provider', ['provider' => $this->provider->value]));
    $this->get(route('social.callback', ['provider' => $this->provider->value]))->assertSessionHas('error');

    expect(session('auth.password_confirmed_at'))->toBeNull();
});

test('an account with a password cannot confirm through a provider instead', function () {
    // A provider is not necessarily as strong as the password, and must not
    // become the weaker of two ways past the same check.
    staffSsoRequire(null);
    $gateway = staffSsoGateway(new SocialIdentity($this->provider, 'staff-subject', 'staff@example.test', true, 'Staff'));
    $this->actingAs($this->staff);

    $this->post(route('password.confirm.provider', ['provider' => $this->provider->value]))->assertSessionHas('error');

    expect($gateway->redirects)->toBe(0)
        ->and(session('auth.password_confirmed_at'))->toBeNull();
});

test('staff cannot disconnect the provider they must sign in with', function () {
    staffSsoRequire($this->provider);
    $this->actingAs($this->staff);

    $this->delete(route('connected-accounts.destroy', ['provider' => $this->provider->value]))->assertSessionHasErrors('provider');

    expect(SocialAccount::query()->where('user_id', $this->staff->id)->exists())->toBeTrue();
});

/*
| Choosing it
*/

test('an administrator can require a provider they are connected to', function () {
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);

    $this->patch(route('system-settings.staff-sign-in.update'), ['provider' => $this->provider->value])->assertSessionHasNoErrors();

    expect(app(Settings::class)->get(Setting::StaffSignInProvider))->toBe($this->provider->value)
        ->and(ActivityLog::query()->where('action', Action::SettingsUpdated)->latest('id')->first()?->context['section'] ?? null)->toBe('staff-sign-in');
});

test('an administrator cannot require a provider they could not sign in with', function () {
    staffSsoProvider(SocialProvider::Google);
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);

    $this->patch(route('system-settings.staff-sign-in.update'), ['provider' => SocialProvider::Google->value])->assertSessionHasErrors('provider');

    expect(app(Settings::class)->get(Setting::StaffSignInProvider))->toBe('');
});

test('a provider that is not set up cannot be required', function () {
    staffSsoProvider($this->provider, enabled: false);
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);

    $this->patch(route('system-settings.staff-sign-in.update'), ['provider' => $this->provider->value])->assertSessionHasErrors('provider');
});

test('choosing it needs a fresh confirmation', function () {
    $this->actingAs($this->admin);

    $this->patch(route('system-settings.staff-sign-in.update'), ['provider' => $this->provider->value])->assertRedirect(route('password.confirm'));

    expect(app(Settings::class)->get(Setting::StaffSignInProvider))->toBe('');
});

test('the recovery command lets staff passwords back in', function () {
    staffSsoRequire($this->provider);

    $this->artisan('projectsend:staff-passwords-on')->assertSuccessful();

    expect(app(Settings::class)->get(Setting::StaffSignInProvider))->toBe('');

    $this->post('/login', ['email' => 'staff@example.test', 'password' => 'staff-password'])->assertSessionHasNoErrors();
    expect(auth()->id())->toBe($this->staff->id);
});

test('deleting the account asks for the provider, not the old password', function () {
    staffSsoRequire($this->provider);
    $this->actingAs($this->staff);

    $this->withHeaders(['X-Inertia' => 'true'])
        ->delete(route('profile.destroy'), ['password' => 'staff-password'])
        ->assertStatus(423)
        ->assertJsonPath('confirm_with.provider', $this->provider->value);

    expect($this->staff->fresh()->active)->toBeTrue();
});

test('turning it on ends every staff "remember me" sign-in, and leaves clients\' alone', function () {
    // Otherwise a remembered staff browser keeps signing back in without the
    // provider ever being asked, and switching somebody off there would not
    // keep them out here.
    $this->staff->forceFill(['remember_token' => 'staff-remembered'])->save();
    $this->client->forceFill(['remember_token' => 'client-remembered'])->save();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);

    $this->patch(route('system-settings.staff-sign-in.update'), ['provider' => $this->provider->value])->assertSessionHasNoErrors();

    expect($this->staff->fresh()->remember_token)->toBeNull()
        ->and($this->client->fresh()->remember_token)->toBe('client-remembered');
});
