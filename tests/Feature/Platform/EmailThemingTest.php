<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\Notifications\ClientWelcomeNotification;
use App\Modules\Platform\Attribution\Events\ResolvingAttribution;
use App\Modules\Platform\Notifications\ThemedMailChannel;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Theming\EmailThemeService;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Event;

/**
 * Renders the actual markdown mail HTML through the real, container-bound
 * mail channel — the same path every Notification's toMail() funnels
 * through — rather than mocking, since the whole point of ThemedMailChannel
 * is that no individual Notification class has to know about theming.
 */
function renderThemedNotificationHtml(): string
{
    $client = new User(['name' => 'Client', 'email' => 'client@example.com']);
    $client->exists = true;
    $client->id = 1;

    $mail = (new ClientWelcomeNotification)->toMail($client);

    $channel = app(MailChannel::class);
    $method = (new ReflectionClass($channel))->getMethod('buildMarkdownHtml');
    $method->setAccessible(true);

    return (string) ($method->invoke($channel, $mail))([]);
}

test('the mail channel is bound to ThemedMailChannel', function () {
    expect(app(MailChannel::class))->toBeInstanceOf(ThemedMailChannel::class);
});

test('selecting the minimal email theme renders its distinct header markup and css', function () {
    app(Settings::class)->set(Setting::EmailTheme, 'minimal');

    $html = renderThemedNotificationHtml();

    expect($html)->toContain('header-label')
        ->and($html)->toContain('letter-spacing: 0.08em');
});

test('selecting the drive email theme renders its distinct css', function () {
    app(Settings::class)->set(Setting::EmailTheme, 'drive');

    $html = renderThemedNotificationHtml();

    expect($html)->toContain('#1a73e8')
        ->and($html)->not->toContain('header-label');
});

test('the default email theme omits the minimal-only header markup', function () {
    app(Settings::class)->set(Setting::EmailTheme, 'default');

    $html = renderThemedNotificationHtml();

    expect($html)->not->toContain('header-label')
        ->and($html)->toContain('box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1)');
});

test('an unknown or unavailable stored email theme resolves to default rather than a broken send', function () {
    app(Settings::class)->set(Setting::EmailTheme, 'does-not-exist');

    expect(app(EmailThemeService::class)->currentThemeKey())->toBe('default');

    $html = renderThemedNotificationHtml();

    expect($html)->toContain('box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1)');
});

test('the branded email theme renders its own css and the stock logo', function () {
    app(Settings::class)->set(Setting::EmailTheme, 'branded');

    expect(app(EmailThemeService::class)->currentThemeKey())->toBe('branded');

    $html = renderThemedNotificationHtml();

    expect($html)->toContain('#3b0764')
        ->and($html)->toContain('<img')
        ->and($html)->toContain('apple-touch-icon.png');
});

test('every email theme carries the attribution line in its footer', function (string $theme) {
    app(Settings::class)->set(Setting::EmailTheme, $theme);

    $html = renderThemedNotificationHtml();

    // Styled, not bare: the published footer partial reuses the `.footer p`
    // and `.footer a` vocabulary all four themes already define, so a theme
    // that forgot to define them would render this unstyled.
    expect($html)->toContain('Powered by ProjectSend')
        ->and($html)->toContain(config('projectsend.links.website'));
})->with(['default', 'minimal', 'drive', 'branded']);

test('a listener that hides attribution strips the line from outgoing mail', function () {
    app(Settings::class)->set(Setting::EmailTheme, 'default');

    Event::listen(ResolvingAttribution::class, function (ResolvingAttribution $event): void {
        $event->visible = false;
    });

    $html = renderThemedNotificationHtml();

    // The rest of the footer — the site's own copyright line — stays.
    expect($html)->not->toContain('Powered by ProjectSend')
        ->and($html)->toContain('All rights reserved');
});
