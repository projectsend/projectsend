<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Mail\MailOAuthConnection;
use App\Modules\Platform\Settings\MailConfigApplier;
use App\Modules\Platform\Settings\MailProvider;
use App\Modules\Platform\Settings\MailProviderSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/** A Google token response — no refresh_token: Google only sends one on the initial consent. */
function gmailRefreshResponse(): array
{
    return [
        'access_token' => 'g-access-2',
        'expires_in' => 3599,
    ];
}

/** The Gmail provider selected and its account connected, ready to send. */
function connectGmailAccount(): MailOAuthConnection
{
    MailProviderSettings::current()->fill(['provider' => 'gmail', 'from_name' => 'Portal'])->save();

    $connection = MailOAuthConnection::for(MailProvider::Gmail);
    $connection->fill([
        'client_id' => 'g-client-1.apps.googleusercontent.com',
        'client_secret' => 'g-secret-1',
        'account_email' => 'portal@gmail.test',
        'access_token' => 'g-access-1',
        'refresh_token' => 'g-refresh-1',
        'token_expires_at' => now()->addHour(),
    ])->save();

    app(MailConfigApplier::class)->flush();
    app(MailConfigApplier::class)->apply();

    return $connection;
}

test('connect sends the admin to Google with offline access and a forced consent screen', function () {
    MailProviderSettings::current()->fill(['provider' => 'gmail'])->save();
    MailOAuthConnection::for(MailProvider::Gmail)
        ->fill(['client_id' => 'g-client-1.apps.googleusercontent.com', 'client_secret' => 'g-secret-1'])
        ->save();

    $this->actingAs($this->admin);

    $response = $this->post('/system/settings/email/oauth/connect');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    assert(is_string($location));

    // offline + consent is what guarantees a refresh token — without
    // them Google silently re-auths and the connection runs out of
    // borrowed time an hour later.
    expect($location)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
        ->and($location)->toContain('access_type=offline')
        ->and($location)->toContain(urlencode('select_account consent'))
        ->and($location)->toContain(urlencode('https://www.googleapis.com/auth/gmail.send'))
        ->and($location)->toContain(urlencode(route('system-settings.email.oauth.callback')))
        ->and($location)->toContain('state='.session('mail_oauth.state'));
});

test('a successful callback stores the tokens and reads the account from the email claim', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response([
            'access_token' => 'g-access-1',
            'refresh_token' => 'g-refresh-1',
            'expires_in' => 3599,
            'id_token' => fakeIdToken('portal@gmail.test'),
        ]),
    ]);

    MailProviderSettings::current()->fill(['provider' => 'gmail', 'from_name' => 'Portal'])->save();
    MailOAuthConnection::for(MailProvider::Gmail)
        ->fill(['client_id' => 'g-client-1.apps.googleusercontent.com', 'client_secret' => 'g-secret-1'])
        ->save();

    $this->actingAs($this->admin);

    $this->withSession(['mail_oauth.state' => 'state-1', 'mail_oauth.provider' => 'gmail'])
        ->get('/system/settings/email/oauth/callback?state=state-1&code=g-code-1')
        ->assertRedirect(route('system-settings.email.edit'))
        ->assertSessionHas('success');

    $connection = MailOAuthConnection::for(MailProvider::Gmail);

    expect($connection->access_token)->toBe('g-access-1')
        ->and($connection->refresh_token)->toBe('g-refresh-1')
        ->and($connection->account_email)->toBe('portal@gmail.test');

    expect(config('mail.default'))->toBe('gmail-api')
        ->and(config('mail.from.address'))->toBe('portal@gmail.test');
});

test('sending posts the rendered message to Gmail as base64url raw with the access token', function () {
    Http::fake([
        'gmail.googleapis.com/*' => Http::response(['id' => 'msg-1']),
    ]);

    connectGmailAccount();

    Mail::mailer('gmail-api')->raw('Hello from the portal', function ($message) {
        $message->to('client@example.com')->subject('A gmail subject');
    });

    Http::assertSent(function ($request): bool {
        if ($request->url() !== 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send') {
            return false;
        }

        $raw = $request['raw'];

        if (! is_string($raw) || str_contains($raw, '+') || str_contains($raw, '/') || str_contains($raw, '=')) {
            return false; // must be base64url, unpadded
        }

        $mime = base64_decode(strtr($raw, '-_', '+/'), true);

        return $request->hasHeader('Authorization', 'Bearer g-access-1')
            && is_string($mime)
            && str_contains($mime, 'A gmail subject')
            && str_contains($mime, 'client@example.com');
    });
});

test('an expired access token is refreshed first, keeping the original refresh token Google never re-sends', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(gmailRefreshResponse()),
        'gmail.googleapis.com/*' => Http::response(['id' => 'msg-1']),
    ]);

    $connection = connectGmailAccount();
    $connection->fill(['token_expires_at' => now()->subMinute()])->save();

    Mail::mailer('gmail-api')->raw('Hello', function ($message) {
        $message->to('client@example.com')->subject('Refresh path');
    });

    $connection = MailOAuthConnection::for(MailProvider::Gmail);

    expect($connection->access_token)->toBe('g-access-2')
        ->and($connection->refresh_token)->toBe('g-refresh-1');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://oauth2.googleapis.com/token')
        && $request['grant_type'] === 'refresh_token');
});

test('a Gmail refusal surfaces its status and message as a send failure', function () {
    Http::fake([
        'gmail.googleapis.com/*' => Http::response([
            'error' => ['code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'Request had insufficient authentication scopes.'],
        ], 403),
    ]);

    connectGmailAccount();

    expect(fn () => Mail::mailer('gmail-api')->raw('Hello', function ($message) {
        $message->to('client@example.com')->subject('Refused');
    }))->toThrow(TransportException::class, 'PERMISSION_DENIED');
});

test('both OAuth connections coexist; the selected provider decides which transport is active', function () {
    connectGmailAccount();

    // A Microsoft connection also stored, but not selected.
    MailOAuthConnection::for(MailProvider::Microsoft365)->fill([
        'client_id' => 'ms-client-1',
        'client_secret' => 'ms-secret-1',
        'account_email' => 'portal@example.test',
        'refresh_token' => 'ms-refresh-1',
    ])->save();

    app(MailConfigApplier::class)->flush();
    app(MailConfigApplier::class)->apply();

    expect(config('mail.default'))->toBe('gmail-api')
        ->and(config('mail.from.address'))->toBe('portal@gmail.test');

    MailProviderSettings::current()->fill(['provider' => 'microsoft365'])->save();

    app(MailConfigApplier::class)->flush();
    app(MailConfigApplier::class)->apply();

    expect(config('mail.default'))->toBe('microsoft-graph')
        ->and(config('mail.from.address'))->toBe('portal@example.test');
});
