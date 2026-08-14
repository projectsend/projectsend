<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Social\SocialProvider;
use App\Modules\Identity\Social\SocialSettings;
use App\Modules\Platform\Capabilities\Edition;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function socialPayload(array $overrides = []): array
{
    return array_merge([
        'enabled' => true,
        'client_id' => 'a-client-id',
        'client_secret' => 'super-secret-client-secret',
        'issuer_url' => null,
        'tenant_id' => null,
        'require_verified_email' => true,
        'allowed_domains' => null,
        'auto_provision' => false,
        'auto_approve' => false,
    ], $overrides);
}

function patchProvider(SocialProvider $provider, array $overrides = []): TestResponse
{
    return test()->actingAs(test()->admin)->patch(
        route('system-settings.social-login.update', ['provider' => $provider->value]),
        socialPayload($overrides),
    );
}

/*
|--------------------------------------------------------------------------
| The credential
|--------------------------------------------------------------------------
|
| v1 stored eight of these in plain text and echoed each one into the
| settings form's HTML value attribute.
|
*/

test('the client secret is encrypted at rest', function () {
    patchProvider(SocialProvider::Google)->assertRedirect();

    $raw = DB::table('social_login_providers')->where('provider', 'google')->value('client_secret');

    expect($raw)->not->toBe('super-secret-client-secret')
        ->and(SocialSettings::for(SocialProvider::Google)->client_secret)->toBe('super-secret-client-secret');
});

test('the client secret is never sent to the browser', function () {
    patchProvider(SocialProvider::Google);

    $this->actingAs($this->admin)->get('/system/settings/social-login')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('providers.0.has_client_secret', true)
            ->missing('providers.0.client_secret'));
});

test('a blank submitted secret keeps the stored one', function () {
    patchProvider(SocialProvider::Google);

    patchProvider(SocialProvider::Google, ['client_secret' => '', 'client_id' => 'changed-id'])->assertRedirect();

    $settings = SocialSettings::for(SocialProvider::Google);

    expect($settings->client_id)->toBe('changed-id')
        ->and($settings->client_secret)->toBe('super-secret-client-secret');
});

/*
|--------------------------------------------------------------------------
| Configuration that would be unsafe
|--------------------------------------------------------------------------
*/

// The nOAuth mitigation. A shared endpoint means any Microsoft tenant can
// assert any address, which is the whole reason the tenant is pinned.
test('the shared Microsoft endpoints are refused', function (string $tenant) {
    patchProvider(SocialProvider::Microsoft, ['tenant_id' => $tenant])
        ->assertSessionHasErrors('tenant_id');
})->with(['common', 'organizations', 'consumers']);

test('a specific Microsoft tenant is accepted', function () {
    patchProvider(SocialProvider::Microsoft, ['tenant_id' => 'e1f2a3b4-0000-0000-0000-abcdefabcdef'])
        ->assertSessionHasNoErrors();

    expect(SocialSettings::for(SocialProvider::Microsoft)->issuer())
        ->toBe('https://login.microsoftonline.com/e1f2a3b4-0000-0000-0000-abcdefabcdef/v2.0');
});

test('an enabled Microsoft provider without a tenant is refused', function () {
    patchProvider(SocialProvider::Microsoft, ['tenant_id' => null])
        ->assertSessionHasErrors('tenant_id');
});

// The document names the page we send people to and the endpoint we post a
// client secret to. Over plaintext, someone on the path chooses both.
test('a plaintext OIDC issuer is refused', function () {
    patchProvider(SocialProvider::Oidc, ['issuer_url' => 'http://idp.example.test'])
        ->assertSessionHasErrors('issuer_url');
});

test('an enabled generic OIDC provider without an issuer is refused', function () {
    patchProvider(SocialProvider::Oidc, ['issuer_url' => null])
        ->assertSessionHasErrors('issuer_url');
});

test('an unknown provider key is a 404', function () {
    $this->actingAs($this->admin)
        ->patch('/system/settings/social-login/myspace', socialPayload())
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Usability gate
|--------------------------------------------------------------------------
*/

// Half-configured settings must behave exactly as "switched off" rather
// than throwing halfway through a redirect.
test('a provider is only usable once it is complete', function () {
    $settings = SocialSettings::for(SocialProvider::Google);

    $settings->forceFill(['provider' => 'google', 'enabled' => false, 'client_id' => 'a', 'client_secret' => 'b'])->save();
    expect($settings->usable())->toBeFalse();

    $settings->forceFill(['enabled' => true, 'client_id' => null])->save();
    expect($settings->refresh()->usable())->toBeFalse();

    $settings->forceFill(['client_id' => 'a', 'client_secret' => null])->save();
    expect($settings->refresh()->usable())->toBeFalse();

    $settings->forceFill(['client_secret' => 'b'])->save();
    expect($settings->refresh()->usable())->toBeTrue();
});

test('only usable providers are offered to a visitor', function () {
    expect(SocialSettings::available())->toBe([]);

    patchProvider(SocialProvider::Google);

    expect(SocialSettings::available())->toBe([['provider' => 'google', 'label' => 'Google']]);

    patchProvider(SocialProvider::Google, ['enabled' => false, 'client_secret' => '']);

    expect(SocialSettings::available())->toBe([]);
});

/*
|--------------------------------------------------------------------------
| The domain allow-list
|--------------------------------------------------------------------------
*/

test('the allow-list is tolerant about how it is written', function () {
    $settings = SocialSettings::for(SocialProvider::Google);
    $settings->forceFill(['provider' => 'google', 'allowed_domains' => ' Acme.test , @acme.co.test '])->save();

    expect($settings->allowsDomain('someone@acme.test'))->toBeTrue()
        ->and($settings->allowsDomain('someone@ACME.CO.TEST'))->toBeTrue()
        ->and($settings->allowsDomain('someone@notacme.test'))->toBeFalse()
        ->and($settings->allowsDomain('nonsense'))->toBeFalse();
});

test('a blank allow-list allows anything', function () {
    expect(SocialSettings::for(SocialProvider::Google)->allowsDomain('anyone@anywhere.test'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Reach
|--------------------------------------------------------------------------
*/

// Social login is an administrator's setting, not an edition difference —
// the same decision LDAP encodes.
test('the screen is reachable in both editions', function (Edition $edition) {
    config()->set('projectsend.edition', $edition);

    $this->actingAs($this->admin)->get('/system/settings/social-login')->assertOk();
})->with([
    'community' => [Edition::Community],
    'cloud' => [Edition::Cloud],
]);

test('a staff member without edit_settings cannot reach or change it', function () {
    $other = staffWithPermissions(['upload']);

    $this->actingAs($other)->get('/system/settings/social-login')->assertForbidden();
    $this->actingAs($other)
        ->patch(route('system-settings.social-login.update', ['provider' => 'google']), socialPayload())
        ->assertForbidden();
});

test('clients cannot reach it at all', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/system/settings/social-login')->assertRedirect(route('dashboard'));
});

test('every provider gets a card, configured or not', function () {
    $this->actingAs($this->admin)->get('/system/settings/social-login')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('providers', count(SocialProvider::cases()))
            ->where('providers.0.redirect_uri', route('social.callback', ['provider' => 'google'])));
});
