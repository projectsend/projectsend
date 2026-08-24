<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Mail\MailOAuthConnection;
use App\Modules\Platform\Settings\MailConfigApplier;
use App\Modules\Platform\Settings\MailProvider;
use App\Modules\Platform\Settings\MailProviderSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/** A token endpoint success, as Microsoft shapes it. */
function fakeTokenResponse(array $overrides = []): array
{
    return array_merge([
        'access_token' => 'access-token-1',
        'refresh_token' => 'refresh-token-1',
        'expires_in' => 3600,
        'id_token' => fakeIdToken(),
    ], $overrides);
}

/** The Microsoft 365 provider selected and its mailbox connected, ready to send. */
function connectMicrosoftMailbox(): MailOAuthConnection
{
    MailProviderSettings::current()->fill(['provider' => 'microsoft365', 'from_name' => 'Portal'])->save();

    $connection = MailOAuthConnection::for(MailProvider::Microsoft365);
    $connection->fill([
        'client_id' => 'client-id-1',
        'client_secret' => 'client-secret-1',
        'account_email' => 'portal@example.test',
        'access_token' => 'access-token-1',
        'refresh_token' => 'refresh-token-1',
        'token_expires_at' => now()->addHour(),
    ])->save();

    app(MailConfigApplier::class)->flush();
    app(MailConfigApplier::class)->apply();

    return $connection;
}

test('the client secret and both tokens are encrypted at rest', function () {
    connectMicrosoftMailbox();

    $raw = DB::table('mail_oauth_connections')->first();
    assert($raw !== null);

    expect($raw->client_secret)->not->toBe('client-secret-1')
        ->and($raw->access_token)->not->toBe('access-token-1')
        ->and($raw->refresh_token)->not->toBe('refresh-token-1');

    $reloaded = MailOAuthConnection::for(MailProvider::Microsoft365);

    expect($reloaded->client_secret)->toBe('client-secret-1')
        ->and($reloaded->access_token)->toBe('access-token-1')
        ->and($reloaded->refresh_token)->toBe('refresh-token-1');
});

test('saving the form with the Microsoft 365 provider stores the app registration and skips the SMTP rules', function () {
    $this->actingAs($this->admin);

    $this->patch('/system/settings/email', [
        'email_notifications_enabled' => true,
        'admin_notification_emails' => ['admin@example.com'],
        'provider' => 'microsoft365',
        'client_id' => 'client-id-1',
        'client_secret' => 'client-secret-1',
        'tenant_id' => '',
        'from_name' => 'Portal',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    $connection = MailOAuthConnection::for(MailProvider::Microsoft365);

    expect(MailProviderSettings::current()->provider)->toBe(MailProvider::Microsoft365)
        ->and($connection->client_id)->toBe('client-id-1')
        ->and($connection->client_secret)->toBe('client-secret-1')
        ->and($connection->tenant_id)->toBeNull();
});

test('saving with an OAuth provider keeps the stored SMTP transport for a later switch back', function () {
    MailProviderSettings::current()->fill([
        'host' => 'smtp.example.test',
        'port' => 587,
        'username' => 'mailer',
        'password' => 'secret',
        'from_address' => 'hello@example.com',
    ])->save();

    $this->actingAs($this->admin);

    $this->patch('/system/settings/email', [
        'email_notifications_enabled' => true,
        'admin_notification_emails' => ['admin@example.com'],
        'provider' => 'microsoft365',
        'client_id' => 'client-id-1',
        'client_secret' => 'client-secret-1',
        'from_name' => 'Portal',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    $settings = MailProviderSettings::current();

    expect($settings->host)->toBe('smtp.example.test')
        ->and($settings->username)->toBe('mailer')
        ->and($settings->password)->toBe('secret')
        ->and($settings->from_address)->toBe('hello@example.com');
});

test('a blank client secret keeps the stored one, and a changed client id drops the tokens', function () {
    connectMicrosoftMailbox();
    $this->actingAs($this->admin);

    $payload = fn (array $overrides): array => array_merge([
        'email_notifications_enabled' => true,
        'admin_notification_emails' => ['admin@example.com'],
        'provider' => 'microsoft365',
        'client_id' => 'client-id-1',
        'client_secret' => '',
        'from_name' => 'Portal',
    ], $overrides);

    // Same client id, blank secret: nothing lost.
    $this->patch('/system/settings/email', $payload([]))->assertRedirect()->assertSessionDoesntHaveErrors();

    $connection = MailOAuthConnection::for(MailProvider::Microsoft365);
    expect($connection->client_secret)->toBe('client-secret-1')
        ->and($connection->refresh_token)->toBe('refresh-token-1');

    // New client id: the old app's tokens are dead weight and go.
    $this->patch('/system/settings/email', $payload(['client_id' => 'client-id-2']))->assertRedirect();

    $connection = MailOAuthConnection::for(MailProvider::Microsoft365);
    expect($connection->client_id)->toBe('client-id-2')
        ->and($connection->refresh_token)->toBeNull()
        ->and($connection->access_token)->toBeNull()
        ->and($connection->account_email)->toBeNull();
});

test('connect refuses until an app registration is saved', function () {
    MailProviderSettings::current()->fill(['provider' => 'microsoft365'])->save();
    $this->actingAs($this->admin);

    $this->from('/system/settings/email')->post('/system/settings/email/oauth/connect')
        ->assertRedirect('/system/settings/email')
        ->assertSessionHas('error');
});

test('connect sends the admin to the tenant-scoped consent URL with a state marker', function () {
    MailProviderSettings::current()->fill(['provider' => 'microsoft365'])->save();
    MailOAuthConnection::for(MailProvider::Microsoft365)
        ->fill(['client_id' => 'client-id-1', 'client_secret' => 'client-secret-1', 'tenant_id' => 'tenant-1'])
        ->save();

    $this->actingAs($this->admin);

    $response = $this->post('/system/settings/email/oauth/connect');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    assert(is_string($location));

    expect($location)->toStartWith('https://login.microsoftonline.com/tenant-1/oauth2/v2.0/authorize?')
        ->and($location)->toContain('client_id=client-id-1')
        ->and($location)->toContain(urlencode(route('system-settings.email.oauth.callback')))
        ->and($location)->toContain('Mail.Send');

    $state = session('mail_oauth.state');
    expect($state)->toBeString()->and($location)->toContain('state='.$state);
});

test('an empty tenant falls back to common', function () {
    MailProviderSettings::current()->fill(['provider' => 'microsoft365'])->save();
    MailOAuthConnection::for(MailProvider::Microsoft365)
        ->fill(['client_id' => 'client-id-1', 'client_secret' => 'client-secret-1'])
        ->save();

    $this->actingAs($this->admin);

    $location = $this->post('/system/settings/email/oauth/connect')->headers->get('Location');
    assert(is_string($location));

    expect($location)->toStartWith('https://login.microsoftonline.com/common/');
});

test('the callback refuses a state nobody here issued', function () {
    MailProviderSettings::current()->fill(['provider' => 'microsoft365'])->save();
    $this->actingAs($this->admin);

    $this->withSession(['mail_oauth.state' => 'expected', 'mail_oauth.provider' => 'microsoft365'])
        ->get('/system/settings/email/oauth/callback?state=forged&code=abc')
        ->assertRedirect(route('system-settings.email.edit'))
        ->assertSessionHas('error');

    expect(MailOAuthConnection::for(MailProvider::Microsoft365)->refresh_token)->toBeNull();
});

test('the callback surfaces a consent-screen refusal instead of a generic failure', function () {
    MailProviderSettings::current()->fill(['provider' => 'microsoft365'])->save();
    $this->actingAs($this->admin);

    $response = $this->withSession(['mail_oauth.state' => 'state-1', 'mail_oauth.provider' => 'microsoft365'])
        ->get('/system/settings/email/oauth/callback?state=state-1&error=access_denied&error_description=The+user+cancelled');

    $response->assertRedirect(route('system-settings.email.edit'));

    expect(session('error'))->toContain('The user cancelled');
});

test('a successful callback stores tokens, reads the mailbox from the id_token, and activates the transport', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(fakeTokenResponse()),
    ]);

    MailProviderSettings::current()->fill(['provider' => 'microsoft365', 'from_name' => 'Portal'])->save();
    MailOAuthConnection::for(MailProvider::Microsoft365)
        ->fill(['client_id' => 'client-id-1', 'client_secret' => 'client-secret-1'])
        ->save();

    $this->actingAs($this->admin);

    $this->withSession(['mail_oauth.state' => 'state-1', 'mail_oauth.provider' => 'microsoft365'])
        ->get('/system/settings/email/oauth/callback?state=state-1&code=auth-code-1')
        ->assertRedirect(route('system-settings.email.edit'))
        ->assertSessionHas('success');

    $connection = MailOAuthConnection::for(MailProvider::Microsoft365);

    expect($connection->access_token)->toBe('access-token-1')
        ->and($connection->refresh_token)->toBe('refresh-token-1')
        ->and($connection->account_email)->toBe('portal@example.test')
        ->and($connection->last_error)->toBeNull();

    Http::assertSent(function ($request): bool {
        return str_starts_with($request->url(), 'https://login.microsoftonline.com/common/oauth2/v2.0/token')
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'auth-code-1';
    });

    // The same request already sees the new transport (flush + apply
    // ran), with the From pinned to the connected mailbox.
    expect(config('mail.default'))->toBe('microsoft-graph')
        ->and(config('mail.from.address'))->toBe('portal@example.test');
});

test('the applier leaves the transport alone while the OAuth provider is selected but no mailbox is connected', function () {
    $originalDefault = config('mail.default');

    MailProviderSettings::current()->fill(['provider' => 'microsoft365'])->save();
    MailOAuthConnection::for(MailProvider::Microsoft365)
        ->fill(['client_id' => 'client-id-1', 'client_secret' => 'client-secret-1'])
        ->save();

    app(MailConfigApplier::class)->flush();
    app(MailConfigApplier::class)->apply();

    expect(config('mail.default'))->toBe($originalDefault);
});

test('a stored SMTP host does not hijack the transport while an OAuth provider is selected', function () {
    connectMicrosoftMailbox();

    MailProviderSettings::current()->fill(['host' => 'smtp.example.test'])->save();

    app(MailConfigApplier::class)->flush();
    app(MailConfigApplier::class)->apply();

    expect(config('mail.default'))->toBe('microsoft-graph');
});

test('cloud edition never activates an OAuth transport, even with a usable connection stored', function () {
    config()->set('projectsend.edition', Edition::Cloud);

    $originalDefault = config('mail.default');

    connectMicrosoftMailbox();

    expect(config('mail.default'))->toBe($originalDefault);
});

test('the connect and disconnect routes are community-only', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    $this->actingAs($this->admin);

    $this->post('/system/settings/email/oauth/connect')->assertNotFound();
    $this->get('/system/settings/email/oauth/callback')->assertNotFound();
    $this->delete('/system/settings/email/oauth')->assertNotFound();
});

test('sending posts the rendered message to Graph as base64 MIME with the fresh access token', function () {
    Http::fake([
        'graph.microsoft.com/*' => Http::response(null, 202),
    ]);

    connectMicrosoftMailbox();

    Mail::mailer('microsoft-graph')->raw('Hello from the portal', function ($message) {
        $message->to('client@example.com')->subject('A test subject');
    });

    Http::assertSent(function ($request): bool {
        if ($request->url() !== 'https://graph.microsoft.com/v1.0/me/sendMail') {
            return false;
        }

        $mime = base64_decode($request->body(), true);

        return $request->hasHeader('Authorization', 'Bearer access-token-1')
            && is_string($mime)
            && str_contains($mime, 'A test subject')
            && str_contains($mime, 'client@example.com');
    });
});

test('an expired access token is refreshed (and the rotated refresh token kept) before sending', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(fakeTokenResponse([
            'access_token' => 'access-token-2',
            'refresh_token' => 'refresh-token-2',
        ])),
        'graph.microsoft.com/*' => Http::response(null, 202),
    ]);

    $connection = connectMicrosoftMailbox();
    $connection->fill(['token_expires_at' => now()->subMinute()])->save();

    Mail::mailer('microsoft-graph')->raw('Hello', function ($message) {
        $message->to('client@example.com')->subject('Refresh path');
    });

    $connection = MailOAuthConnection::for(MailProvider::Microsoft365);

    expect($connection->access_token)->toBe('access-token-2')
        ->and($connection->refresh_token)->toBe('refresh-token-2');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/token') && $request['grant_type'] === 'refresh_token');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/me/sendMail'
        && $request->hasHeader('Authorization', 'Bearer access-token-2'));
});

test('a Graph refusal surfaces as a send failure, not a silent success', function () {
    Http::fake([
        'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorSendAsDenied', 'message' => 'Not allowed to send as this user']], 403),
    ]);

    connectMicrosoftMailbox();

    expect(fn () => Mail::mailer('microsoft-graph')->raw('Hello', function ($message) {
        $message->to('client@example.com')->subject('Refused');
    }))->toThrow(TransportException::class, 'Not allowed to send as this user');
});

test('the scheduled refresh keeps a healthy connection fresh', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(fakeTokenResponse([
            'access_token' => 'access-token-2',
            'refresh_token' => 'refresh-token-2',
        ])),
    ]);

    connectMicrosoftMailbox();

    Artisan::call('projectsend:refresh-mail-oauth-tokens');

    $connection = MailOAuthConnection::for(MailProvider::Microsoft365);

    expect($connection->access_token)->toBe('access-token-2')
        ->and($connection->refresh_token)->toBe('refresh-token-2')
        ->and($connection->last_error)->toBeNull()
        ->and($connection->last_refreshed_at)->not->toBeNull();
});

test('a dead grant records the error and notifies settings admins exactly once', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'AADSTS50173: The provided grant has expired.',
        ], 400),
    ]);

    connectMicrosoftMailbox();

    // Somebody without the settings permission must not be alarmed.
    $bystander = staffWithPermissions([]);

    Artisan::call('projectsend:refresh-mail-oauth-tokens');

    $connection = MailOAuthConnection::for(MailProvider::Microsoft365);
    expect($connection->last_error)->toContain('AADSTS50173');

    $notifications = InAppNotification::query()->where('type', 'mail_oauth_connection_broken')->get();
    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()?->user_id)->toBe($this->admin->id)
        ->and($notifications->first()?->user_id)->not->toBe($bystander->id);

    // The broken state is already known — the next run must not nag.
    Artisan::call('projectsend:refresh-mail-oauth-tokens');

    expect(InAppNotification::query()->where('type', 'mail_oauth_connection_broken')->count())->toBe(1);
});

test('a transient token endpoint failure neither flags the connection nor notifies anyone', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['error' => 'temporarily_unavailable'], 503),
    ]);

    connectMicrosoftMailbox();

    Artisan::call('projectsend:refresh-mail-oauth-tokens');

    expect(MailOAuthConnection::for(MailProvider::Microsoft365)->last_error)->toBeNull()
        ->and(InAppNotification::query()->where('type', 'mail_oauth_connection_broken')->count())->toBe(0);
});

test('disconnect drops the tokens but keeps the app registration', function () {
    connectMicrosoftMailbox();
    $this->actingAs($this->admin);

    $this->from('/system/settings/email')->delete('/system/settings/email/oauth')
        ->assertRedirect('/system/settings/email')
        ->assertSessionHas('success');

    $connection = MailOAuthConnection::for(MailProvider::Microsoft365);

    expect($connection->refresh_token)->toBeNull()
        ->and($connection->access_token)->toBeNull()
        ->and($connection->account_email)->toBeNull()
        ->and($connection->client_id)->toBe('client-id-1')
        ->and($connection->client_secret)->toBe('client-secret-1');

    // The transport must not come back on the next boot: apply() layers
    // onto config/mail.php's defaults, so simulate a fresh process by
    // resetting the default before re-applying the (already flushed)
    // resolved settings.
    config()->set('mail.default', 'log');
    app(MailConfigApplier::class)->apply();

    expect(config('mail.default'))->toBe('log');
});

test('the settings page never ships the secret or tokens to the browser', function () {
    connectMicrosoftMailbox();
    $this->actingAs($this->admin);

    $response = $this->get('/system/settings/email');

    $response->assertInertia(fn ($page) => $page
        ->where('mail_oauth_connections.microsoft365.connected', true)
        ->where('mail_oauth_connections.microsoft365.account_email', 'portal@example.test')
        ->where('mail_oauth_connections.microsoft365.has_client_secret', true)
        ->missing('mail_oauth_connections.microsoft365.client_secret')
        ->missing('mail_oauth_connections.microsoft365.access_token')
        ->missing('mail_oauth_connections.microsoft365.refresh_token'),
    );
});
