<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\Social\SocialAccount;
use App\Modules\Identity\Social\SocialGateway;
use App\Modules\Identity\Social\SocialIdentity;
use App\Modules\Identity\Social\SocialProvider;
use App\Modules\Identity\Social\SocialSettings;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSocialGateway;

beforeEach(function () {
    $this->staff = User::factory()->create();

    $settings = SocialSettings::for(SocialProvider::Google);
    $settings->forceFill([
        'provider' => 'google',
        'enabled' => true,
        'client_id' => 'id',
        'client_secret' => 'secret',
    ])->save();
});

function connect(User $user, SocialIdentity $identity): TestResponse
{
    test()->swap(SocialGateway::class, new FakeSocialGateway($identity));

    test()->actingAs($user)->post(route('connected-accounts.connect', ['provider' => $identity->provider->value]));

    return test()->actingAs($user)->get(route('social.callback', ['provider' => $identity->provider->value]));
}

function linkRow(User $user, string $subject, SocialProvider $provider = SocialProvider::Google): SocialAccount
{
    return SocialAccount::query()->create([
        'user_id' => $user->id,
        'provider' => $provider->value,
        'provider_user_id' => $subject,
        'email' => $user->email,
    ]);
}

/*
|--------------------------------------------------------------------------
| Connecting
|--------------------------------------------------------------------------
|
| Connecting from here is the safe way to use a provider that cannot
| verify an address: the account is established by the session, so the
| address is never taken on trust at all.
|
*/

test('a signed-in person connects a provider, unverified address and all', function () {
    $identity = new SocialIdentity(SocialProvider::Google, 'sub-1', 'anything@elsewhere.test', false, 'Someone');

    connect($this->staff, $identity)->assertRedirect(route('connected-accounts.edit'));

    expect(SocialAccount::query()->where('user_id', $this->staff->id)->count())->toBe(1)
        ->and(ActivityLog::query()->where('action', Action::SocialAccountLinked)->exists())->toBeTrue();
});

test('a provider already connected to somebody else is refused', function () {
    $other = User::factory()->client()->create();
    linkRow($other, 'sub-1');

    connect($this->staff, new SocialIdentity(SocialProvider::Google, 'sub-1', 'x@example.test', true, 'X'))
        ->assertRedirect(route('connected-accounts.edit'));

    expect(SocialAccount::query()->where('user_id', $this->staff->id)->exists())->toBeFalse()
        ->and(SocialAccount::query()->where('user_id', $other->id)->exists())->toBeTrue();
});

test('reconnecting a different account at the same provider replaces the link', function () {
    connect($this->staff, new SocialIdentity(SocialProvider::Google, 'sub-1', 'one@example.test', true, 'One'));
    connect($this->staff, new SocialIdentity(SocialProvider::Google, 'sub-2', 'two@example.test', true, 'Two'));

    $links = SocialAccount::query()->where('user_id', $this->staff->id)->get();

    expect($links)->toHaveCount(1)
        ->and($links->first()->provider_user_id)->toBe('sub-2');
});

/*
|--------------------------------------------------------------------------
| Starting the exchange
|--------------------------------------------------------------------------
|
| Connecting starts as an Inertia XHR, and an XHR cannot follow a 302 to
| the provider: the browser refuses the cross-origin hop and nobody goes
| anywhere. Inertia's 409 + X-Inertia-Location pair is what turns the
| same answer into a real top-level navigation.
|
*/

test('connecting from the settings screen navigates the browser, not the XHR', function () {
    test()->swap(SocialGateway::class, new FakeSocialGateway);

    $this->actingAs($this->staff)
        ->post(route('connected-accounts.connect', ['provider' => 'google']), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://provider.test/authorize');
});

test('a plain request is still given the provider redirect itself', function () {
    test()->swap(SocialGateway::class, new FakeSocialGateway);

    $this->actingAs($this->staff)
        ->post(route('connected-accounts.connect', ['provider' => 'google']))
        ->assertRedirect('https://provider.test/authorize');
});

/*
|--------------------------------------------------------------------------
| Disconnecting
|--------------------------------------------------------------------------
*/

test('a local account can disconnect its only provider', function () {
    linkRow($this->staff, 'sub-1');

    $this->actingAs($this->staff)
        ->delete(route('connected-accounts.destroy', ['provider' => 'google']))
        ->assertSessionHasNoErrors();

    expect(SocialAccount::query()->count())->toBe(0)
        ->and(ActivityLog::query()->where('action', Action::SocialAccountUnlinked)->exists())->toBeTrue();
});

// The mirror image of AccountConversion::requiresNewPassword(): an account
// created by a provider holds a random password nobody has ever seen, so
// the provider is the only way in.
test('an account created by a provider cannot remove its last connection', function () {
    $client = User::factory()->client()->create();
    $client->forceFill(['auth_source' => AuthSource::Social])->save();
    linkRow($client, 'sub-1');

    $this->actingAs($client)
        ->delete(route('connected-accounts.destroy', ['provider' => 'google']))
        ->assertSessionHasErrors('provider');

    expect(SocialAccount::query()->count())->toBe(1);
});

test('it can remove one of two connections', function () {
    $client = User::factory()->client()->create();
    $client->forceFill(['auth_source' => AuthSource::Social])->save();
    linkRow($client, 'sub-1');
    linkRow($client, 'gh-1', SocialProvider::Github);

    $this->actingAs($client)
        ->delete(route('connected-accounts.destroy', ['provider' => 'google']))
        ->assertSessionHasNoErrors();

    expect(SocialAccount::query()->where('user_id', $client->id)->count())->toBe(1);
});

test('one account cannot disconnect another account link', function () {
    $other = User::factory()->client()->create();
    linkRow($other, 'sub-1');

    $this->actingAs($this->staff)->delete(route('connected-accounts.destroy', ['provider' => 'google']));

    expect(SocialAccount::query()->where('user_id', $other->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The screen
|--------------------------------------------------------------------------
*/

test('the screen is reachable by clients as well as staff', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/settings/connected-accounts')->assertOk();
    $this->actingAs($this->staff)->get('/settings/connected-accounts')->assertOk();
});

test('a guest is sent to the login page', function () {
    $this->get('/settings/connected-accounts')->assertRedirect(route('login'));
});

test('only usable providers are listed', function () {
    $this->actingAs($this->staff)->get('/settings/connected-accounts')
        ->assertInertia(fn ($page) => $page->has('providers', 1)->where('providers.0.provider', 'google'));
});
