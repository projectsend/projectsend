<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\Social\SocialAccount;
use App\Modules\Identity\Social\SocialGateway;
use App\Modules\Identity\Social\SocialIdentity;
use App\Modules\Identity\Social\SocialProvider;
use App\Modules\Identity\Social\SocialSettings;
use App\Modules\Identity\TwoFactor\TwoFactorService;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeSocialGateway;

beforeEach(function () {
    // Every HTTP test needs a staff account or EnsureSetupIsComplete
    // redirects the whole app to /setup.
    User::factory()->create();
});

function socialSettings(
    SocialProvider $provider = SocialProvider::Google,
    bool $enabled = true,
    bool $requireVerified = true,
    bool $autoProvision = false,
    bool $autoApprove = false,
    ?string $allowedDomains = null,
): SocialSettings {
    $settings = SocialSettings::for($provider);

    $settings->forceFill([
        'provider' => $provider->value,
        'enabled' => $enabled,
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'tenant_id' => $provider->needsTenantId() ? 'tenant-abc' : null,
        'issuer_url' => $provider->needsIssuerUrl() ? 'https://idp.example.test' : null,
        'require_verified_email' => $requireVerified,
        'allowed_domains' => $allowedDomains,
        'auto_provision' => $autoProvision,
        'auto_approve' => $autoApprove,
    ])->save();

    return $settings;
}

function identity(
    string $email = 'someone@example.test',
    bool $verified = true,
    string $subject = 'subject-1',
    SocialProvider $provider = SocialProvider::Google,
    ?string $name = 'Someone Else',
): SocialIdentity {
    return new SocialIdentity($provider, $subject, $email, $verified, $name);
}

function fakeProvider(?SocialIdentity $identity): FakeSocialGateway
{
    $fake = new FakeSocialGateway($identity);
    test()->swap(SocialGateway::class, $fake);

    return $fake;
}

/**
 * Walk the whole redirect-and-callback round trip, so the session intent
 * the callback requires is written the way a real sign-in writes it.
 */
function signInWith(SocialProvider $provider = SocialProvider::Google): TestResponse
{
    test()->get(route('social.redirect', ['provider' => $provider->value]));

    return test()->get(route('social.callback', ['provider' => $provider->value]));
}

/*
|--------------------------------------------------------------------------
| The takeover v1 shipped
|--------------------------------------------------------------------------
|
| v1 matched whatever address the provider returned against `email` OR
| `user`, verified or not, and signed you in as whoever held it. These are
| the tests that say it cannot happen here.
|
*/

test('an unverified address cannot reach an administrator account', function () {
    socialSettings();
    $admin = User::factory()->create(['email' => 'admin@example.test']);
    fakeProvider(identity(email: 'admin@example.test', verified: false));

    signInWith()->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse()
        ->and(SocialAccount::query()->count())->toBe(0)
        ->and($admin->fresh()->auth_source)->toBe(AuthSource::Local);
});

test('an unverified address cannot reach a client account either', function () {
    socialSettings();
    User::factory()->client()->create(['email' => 'client@example.test']);
    fakeProvider(identity(email: 'client@example.test', verified: false));

    signInWith()->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});

test('a verified address links to the account holding it and signs in', function () {
    socialSettings();
    $client = User::factory()->client()->create(['email' => 'client@example.test']);
    fakeProvider(identity(email: 'client@example.test', verified: true));

    signInWith()->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($client->id)
        ->and(SocialAccount::query()->where('user_id', $client->id)->exists())->toBeTrue();
});

// The escape hatch for a directory that omits the claim. Opt-in, and it
// is the administrator's explicit decision.
test('turning the verified requirement off lets an unverified address link', function () {
    socialSettings(requireVerified: false);
    $client = User::factory()->client()->create(['email' => 'client@example.test']);
    fakeProvider(identity(email: 'client@example.test', verified: false));

    signInWith()->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($client->id);
});

/*
|--------------------------------------------------------------------------
| The subject is the identity, not the address
|--------------------------------------------------------------------------
*/

test('an existing link wins even after the address changes at the provider', function () {
    socialSettings();
    $client = User::factory()->client()->create(['email' => 'old@example.test']);
    SocialAccount::query()->create([
        'user_id' => $client->id,
        'provider' => SocialProvider::Google->value,
        'provider_user_id' => 'subject-1',
        'email' => 'old@example.test',
    ]);

    fakeProvider(identity(email: 'brand-new@example.test', verified: true, subject: 'subject-1'));

    signInWith()->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($client->id);
});

test('a different subject does not inherit another account link', function () {
    socialSettings();
    $victim = User::factory()->create(['email' => 'victim@example.test']);
    SocialAccount::query()->create([
        'user_id' => $victim->id,
        'provider' => SocialProvider::Google->value,
        'provider_user_id' => 'the-real-one',
        'email' => 'victim@example.test',
    ]);

    // Same address, different person at the provider, and unverified.
    fakeProvider(identity(email: 'victim@example.test', verified: false, subject: 'an-impostor'));

    signInWith()->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Microsoft's tenant, and Facebook's silence
|--------------------------------------------------------------------------
*/

test('a Microsoft identity from another tenant is not treated as verified', function () {
    $settings = socialSettings(SocialProvider::Microsoft);
    User::factory()->create(['email' => 'admin@example.test']);

    // What SocialIdentity::fromSocialite() computes when `tid` does not
    // match: the address is present, and worth nothing.
    fakeProvider(identity(
        email: 'admin@example.test',
        verified: false,
        provider: SocialProvider::Microsoft,
    ));

    test()->get(route('social.redirect', ['provider' => 'microsoft']));
    test()->get(route('social.callback', ['provider' => 'microsoft']))->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse()
        ->and($settings->fresh()->tenant_id)->toBe('tenant-abc');
});

test('Facebook can create a new account but never reach an existing one', function () {
    socialSettings(SocialProvider::Facebook, autoProvision: true, autoApprove: true);
    User::factory()->create(['email' => 'admin@example.test']);

    // Facebook is always unverified — there is no claim to read.
    fakeProvider(identity(email: 'admin@example.test', verified: false, provider: SocialProvider::Facebook));
    test()->get(route('social.redirect', ['provider' => 'facebook']));
    test()->get(route('social.callback', ['provider' => 'facebook']))->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();

    // A brand-new address takes nothing over, so it may provision — but
    // it waits for a human, whatever auto_approve says.
    fakeProvider(identity(email: 'nobody@example.test', verified: false, subject: 'fb-1', provider: SocialProvider::Facebook));
    test()->get(route('social.redirect', ['provider' => 'facebook']));
    test()->get(route('social.callback', ['provider' => 'facebook']));

    $created = User::query()->where('email', 'nobody@example.test')->first();

    expect($created)->not->toBeNull()
        ->and($created->active)->toBeFalse()
        ->and($created->account_requested)->toBeTrue()
        ->and(auth()->check())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Provisioning
|--------------------------------------------------------------------------
*/

test('an unknown verified address provisions a client, never staff', function () {
    socialSettings(autoProvision: true, autoApprove: true);
    fakeProvider(identity(email: 'new@example.test', verified: true));

    signInWith()->assertRedirect(route('dashboard'));

    $created = User::query()->where('email', 'new@example.test')->firstOrFail();

    expect($created->isClient())->toBeTrue()
        ->and($created->auth_source)->toBe(AuthSource::Social)
        ->and($created->active)->toBeTrue()
        ->and(auth()->id())->toBe($created->id)
        ->and(ActivityLog::query()->where('action', Action::SocialClientProvisioned)->exists())->toBeTrue();
});

test('provisioning is refused when the provider may not create accounts', function () {
    socialSettings(autoProvision: false);
    fakeProvider(identity(email: 'new@example.test', verified: true));

    signInWith()->assertRedirect(route('login'));

    expect(User::query()->where('email', 'new@example.test')->exists())->toBeFalse();
});

test('a provisioned account waits for approval when the provider says so', function () {
    socialSettings(autoProvision: true, autoApprove: false);
    fakeProvider(identity(email: 'new@example.test', verified: true));

    signInWith()->assertRedirect(route('login'));

    $created = User::query()->where('email', 'new@example.test')->firstOrFail();

    expect($created->account_requested)->toBeTrue()
        ->and(auth()->check())->toBeFalse();
});

test('a provisioned account joins the configured auto group', function () {
    $group = Group::query()->create(['name' => 'Provider folk']);
    app(Settings::class)->set(Setting::ClientsAutoGroup, $group->id);

    socialSettings(autoProvision: true, autoApprove: true);
    fakeProvider(identity(email: 'new@example.test', verified: true));

    signInWith();

    $created = User::query()->where('email', 'new@example.test')->firstOrFail();

    expect($group->members()->where('users.id', $created->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Domain allow-list
|--------------------------------------------------------------------------
*/

test('an address outside the allow-list is refused, sign-in and provisioning alike', function () {
    socialSettings(autoProvision: true, autoApprove: true, allowedDomains: 'acme.test, acme.co.test');

    $client = User::factory()->client()->create(['email' => 'client@example.test']);
    fakeProvider(identity(email: 'client@example.test', verified: true));
    signInWith()->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();

    fakeProvider(identity(email: 'stranger@example.test', verified: true, subject: 'other'));
    signInWith();

    expect(User::query()->where('email', 'stranger@example.test')->exists())->toBeFalse();

    fakeProvider(identity(email: 'welcome@acme.test', verified: true, subject: 'inside'));
    signInWith()->assertRedirect(route('dashboard'));

    expect(auth()->user()->email)->toBe('welcome@acme.test')
        ->and($client->fresh()->email)->toBe('client@example.test');
});

/*
|--------------------------------------------------------------------------
| Account state and the second factor still apply
|--------------------------------------------------------------------------
*/

test('a deactivated account gets the ordinary message, not a social one', function () {
    socialSettings();
    $client = User::factory()->client()->create(['email' => 'client@example.test', 'active' => false]);
    SocialAccount::query()->create([
        'user_id' => $client->id,
        'provider' => SocialProvider::Google->value,
        'provider_user_id' => 'subject-1',
        'email' => 'client@example.test',
    ]);
    fakeProvider(identity(email: 'client@example.test'));

    signInWith()->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Your account has been deactivated.');

    expect(auth()->check())->toBeFalse();
});

test('two-factor still challenges a provider sign-in', function () {
    socialSettings();
    $client = User::factory()->client()->create(['email' => 'client@example.test']);
    $client->forceFill([
        'two_factor_secret' => app(TwoFactorService::class)->generateSecret(),
        'two_factor_confirmed_at' => now(),
    ])->save();
    SocialAccount::query()->create([
        'user_id' => $client->id,
        'provider' => SocialProvider::Google->value,
        'provider_user_id' => 'subject-1',
        'email' => 'client@example.test',
    ]);
    fakeProvider(identity(email: 'client@example.test'));

    signInWith()->assertRedirect(route('two-factor.challenge'));

    expect(auth()->check())->toBeFalse()
        ->and(session('two_factor.login_id'))->toBe($client->id);
});

/*
|--------------------------------------------------------------------------
| The callback is not an open door
|--------------------------------------------------------------------------
*/

test('a callback nobody started is refused without contacting the provider', function () {
    socialSettings();
    $fake = fakeProvider(identity());

    $this->get(route('social.callback', ['provider' => 'google']))->assertRedirect(route('login'));

    expect($fake->identityCalls)->toBe(0)
        ->and(auth()->check())->toBeFalse();
});

test('a callback for a different provider than the one begun is refused', function () {
    socialSettings();
    socialSettings(SocialProvider::Github);
    $fake = fakeProvider(identity());

    $this->get(route('social.redirect', ['provider' => 'google']));
    $this->get(route('social.callback', ['provider' => 'github']))->assertRedirect(route('login'));

    expect($fake->identityCalls)->toBe(0);
});

test('a disabled provider is never contacted', function () {
    socialSettings(enabled: false);
    $fake = fakeProvider(identity());

    $this->get(route('social.redirect', ['provider' => 'google']))->assertRedirect(route('login'));
    $this->get(route('social.callback', ['provider' => 'google']))->assertRedirect(route('login'));

    expect($fake->redirects)->toBe(0)
        ->and($fake->identityCalls)->toBe(0);
});

test('an unknown provider is a 404', function () {
    $this->get('/auth/myspace/redirect')->assertNotFound();
});

test('an identity with no email address cannot sign in', function () {
    socialSettings(autoProvision: true, autoApprove: true);
    fakeProvider(new SocialIdentity(SocialProvider::Google, 'subject-1', null, false, 'No Address'));

    signInWith()->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Staff may sign in, but are never created
|--------------------------------------------------------------------------
*/

test('a staff account signs in through a provider it has connected', function () {
    socialSettings();
    $staff = User::factory()->create(['email' => 'staff@example.test']);
    SocialAccount::query()->create([
        'user_id' => $staff->id,
        'provider' => SocialProvider::Google->value,
        'provider_user_id' => 'subject-1',
        'email' => 'staff@example.test',
    ]);
    fakeProvider(identity(email: 'staff@example.test'));

    signInWith()->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($staff->id);
});

/*
|--------------------------------------------------------------------------
| A refusal has to be readable
|--------------------------------------------------------------------------
|
| Every refusal above redirects to /login carrying a flash. The app-wide
| Toaster is mounted in the authenticated layout, which the login page is
| not — so without the login page rendering this itself, a person would be
| bounced back to a blank form with no idea why. The assertion is that the
| message survives the redirect and reaches the page's props.
|
*/

test('a refusal reaches the login page rather than vanishing', function () {
    socialSettings();
    User::factory()->create(['email' => 'admin@example.test']);
    fakeProvider(identity(email: 'admin@example.test', verified: false));

    signInWith();

    $this->followingRedirects()
        ->get(route('social.callback', ['provider' => 'google']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/login')
            ->where('flash.error', 'That sign-in could not be completed. Please try again.'));
});

test('the takeover refusal explains what to do instead', function () {
    socialSettings();
    User::factory()->create(['email' => 'admin@example.test']);
    fakeProvider(identity(email: 'admin@example.test', verified: false));

    $this->get(route('social.redirect', ['provider' => 'google']));

    $this->followingRedirects()
        ->get(route('social.callback', ['provider' => 'google']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/login')
            ->where('flash.error', 'An account already uses this email address, and Google did not confirm that you own it. Sign in with your password and connect Google from your settings instead.'));
});
