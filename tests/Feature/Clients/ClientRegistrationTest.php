<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaProvider;
use App\Modules\Platform\Captcha\CaptchaSettings;
use App\Modules\Platform\Captcha\CaptchaVerifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // Setup complete.
    User::factory()->create();

    // Settings outlive the per-test rollback, so the captcha this file
    // switches on below must be switched off again for everything else.
    app(Settings::class)->set(Setting::CaptchaProvider, 'none');
    Captcha::forgetDisplayCache();

    // The circuit breaker is a cache entry with a 60-second life, so it
    // outlives not just a test but the file that opened it.
    CaptchaVerifier::forgetOutage();
});

test('registration is hidden while the setting is off', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();

    // And the login screen offers no registration link.
    $this->get('/login')->assertInertia(
        fn (AssertableInertia $page) => $page->where('canRegister', false),
    );
});

test('with auto-approve on, a self-registered client can log in immediately', function () {
    app(Settings::class)->set(Setting::ClientsCanRegister, true);
    app(Settings::class)->set(Setting::ClientsAutoApprove, true);

    $this->get('/login')->assertInertia(
        fn (AssertableInertia $page) => $page->where('canRegister', true),
    );

    $this->post('/register', [
        'name' => 'Self Client',
        'email' => 'self@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertRedirect(route('login'));

    $client = User::query()->where('email', 'self@example.com')->sole();
    expect($client->type)->toBe(UserType::Client)
        ->and($client->active)->toBeTrue()
        ->and($client->account_requested)->toBeFalse()
        ->and($client->role?->name)->toBe('Client')
        ->and(ActivityLog::query()->where('action', Action::ClientSelfRegistered)->exists())->toBeTrue();

    $this->post('/login', ['email' => 'self@example.com', 'password' => 'super-secret-password']);
    $this->assertAuthenticated();
});

test('without auto-approve, the account waits for approval and cannot log in', function () {
    app(Settings::class)->set(Setting::ClientsCanRegister, true);

    $this->post('/register', [
        'name' => 'Waiting Client',
        'email' => 'waiting@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertRedirect(route('login'));

    $client = User::query()->where('email', 'waiting@example.com')->sole();
    expect($client->active)->toBeFalse()
        ->and($client->account_requested)->toBeTrue();

    // Login with the correct password explains the pending state.
    $this->from('/login')->post('/login', ['email' => 'waiting@example.com', 'password' => 'super-secret-password'])
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toBe(__('Your account request has not been approved yet.'));
    $this->assertGuest();
});

test('approving a request activates the account', function () {
    $admin = User::query()->sole();
    $pending = User::factory()->pendingClient()->create();

    $this->actingAs($admin)->get('/account-requests')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('clients/requests')
            ->has('requests', 1),
    );

    $this->actingAs($admin)->post("/account-requests/{$pending->id}/approve")->assertRedirect();

    $pending->refresh();
    expect($pending->active)->toBeTrue()
        ->and($pending->account_requested)->toBeFalse()
        ->and(ActivityLog::query()->where('action', Action::ClientApproved)->exists())->toBeTrue();

    $this->post('/login', ['email' => $pending->email, 'password' => 'password']);
    $this->assertAuthenticated();
});

test('denying a request deletes the account and records it', function () {
    $admin = User::query()->sole();
    $pending = User::factory()->pendingClient()->create(['name' => 'Denied Person']);

    $this->actingAs($admin)->delete("/account-requests/{$pending->id}")->assertRedirect();

    expect(User::query()->find($pending->id))->toBeNull();

    $entry = ActivityLog::query()->where('action', Action::ClientDenied)->sole();
    expect($entry->context)->toBe(['name' => 'Denied Person']);
});

test('approve and deny only apply to pending clients', function () {
    $admin = User::query()->sole();
    $activeClient = User::factory()->client()->create();

    $this->actingAs($admin);
    $this->post("/account-requests/{$activeClient->id}/approve")->assertNotFound();
    $this->delete("/account-requests/{$activeClient->id}")->assertNotFound();
    $this->post("/account-requests/{$admin->id}/approve")->assertNotFound();
});

test('clients cannot see the requests queue', function () {
    $this->actingAs(User::factory()->client()->create());

    $this->get('/account-requests')->assertRedirect(route('dashboard'));
});

test('registration enforces the captcha for every provider', function (string $provider, string $host, array $success) {
    $settings = app(Settings::class);
    $settings->set(Setting::ClientsCanRegister, true);
    $settings->set(Setting::CaptchaOnRegistration, true);
    $settings->set(Setting::CaptchaKeySource, 'own');

    CaptchaSettings::for(CaptchaProvider::from($provider))->fill([
        'site_key' => 'site-key',
        'secret_key' => 'secret-key',
    ])->save();

    $settings->set(Setting::CaptchaProvider, $provider);
    Captcha::forgetDisplayCache();

    $payload = [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'Password!234',
        'password_confirmation' => 'Password!234',
    ];

    // One sequence for the whole test: re-arming Http::fake() mid-test
    // would leave the first stub matching and the second one dead.
    Http::fakeSequence($host)
        ->push(['success' => false, 'error-codes' => ['invalid-input-response']])
        ->push($success);

    // No token at all — refused before the provider is even asked.
    $this->post('/register', $payload)->assertSessionHasErrors('captcha_token');
    expect(User::query()->where('email', 'ada@example.com')->exists())->toBeFalse();
    Http::assertNothingSent();

    // A token the provider rejects.
    $this->post('/register', $payload + ['captcha_token' => 'a-token'])->assertSessionHasErrors('captcha_token');
    expect(User::query()->where('email', 'ada@example.com')->exists())->toBeFalse();

    // And one it accepts. v1 dropped the result entirely for reCAPTCHA v3
    // and Turnstile, so registration was unprotected on two of the three
    // providers, and verified twice on the third — which failed every time
    // and made registering impossible.
    $this->post('/register', $payload + ['captcha_token' => 'a-token'])->assertSessionHasNoErrors();
    expect(User::query()->where('email', 'ada@example.com')->exists())->toBeTrue();

    // Two verifications for two submitted tokens — never two for one,
    // which is what broke v1.
    Http::assertSentCount(2);
})->with([
    'turnstile' => ['turnstile', 'challenges.cloudflare.com/*', ['success' => true, 'action' => 'register']],
    'recaptcha v2' => ['recaptcha_v2', 'www.google.com/*', ['success' => true]],
    'recaptcha v3' => ['recaptcha_v3', 'www.google.com/*', ['success' => true, 'action' => 'register', 'score' => 0.9]],
]);
