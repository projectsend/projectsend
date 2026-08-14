<?php

declare(strict_types=1);

namespace App\Modules\Platform\Notifications;

use App\Modules\Platform\Attribution\Attribution;
use App\Modules\Platform\Theming\EmailThemeRegistry;
use App\Modules\Platform\Theming\EmailThemeService;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\View;

/**
 * Every notification's toMail() funnels through
 * MailChannel::markdownRenderer() before the markdown view is rendered —
 * the single choke point that lets the tenant's chosen email theme
 * (Setting::EmailTheme) apply to all of them without touching each
 * Notification class individually. Bound over the stock MailChannel in
 * PlatformServiceProvider.
 *
 * `$mail_theme`/`$mail_theme_context`/`$mail_attribution` are shared with
 * the view factory (rather than passed as view data) because the
 * published header/footer partials are rendered as components nested
 * inside Markdown::render()'s own view call, which doesn't forward the
 * outer $data array to them.
 *
 * Attribution is resolved here, per message, rather than once at boot:
 * this runs inside a queue worker that stays alive for hours, and a
 * footer cached at boot would keep naming ProjectSend in mail sent long
 * after an operator white-labelled the installation.
 *
 * See docs/theming-email-checklist.md before adding a theme or changing
 * anything here — a new Notification class needs none of this, by
 * design (that's the whole point of the choke point above).
 */
class ThemedMailChannel extends MailChannel
{
    protected function markdownRenderer($message)
    {
        $theme = $message->theme ?? app(EmailThemeService::class)->currentThemeKey();
        $context = app(EmailThemeRegistry::class)->get($theme)?->context;

        View::share('mail_theme', $theme);
        View::share('mail_theme_context', $context !== null ? $context() : []);
        View::share('mail_attribution', app(Attribution::class)->visible());

        return $this->markdown->theme($theme);
    }
}
