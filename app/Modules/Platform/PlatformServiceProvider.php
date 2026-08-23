<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Files\Storage\ResolvingUploadDisk;
use App\Modules\Notifications\NotificationTypeDefinition;
use App\Modules\Notifications\NotificationTypeRegistry;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Captcha\Console\DisableCaptchaCommand;
use App\Modules\Platform\Captcha\Console\TestCaptchaCommand;
use App\Modules\Platform\Localization\LocaleRegistry;
use App\Modules\Platform\Localization\TimezoneRegistry;
use App\Modules\Platform\Mail\Console\RefreshMailOAuthTokensCommand;
use App\Modules\Platform\Mail\MicrosoftGraphTransport;
use App\Modules\Platform\News\Console\FetchNewsCommand;
use App\Modules\Platform\Notifications\ThemedMailChannel;
use App\Modules\Platform\Scheduling\Console\PurgeFailedJobsCommand;
use App\Modules\Platform\Scheduling\RecordsScheduledTaskRuns;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\MailConfigApplier;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Theming\Console\GenerateThemePreviewDataCommand;
use App\Modules\Platform\Theming\EmailThemeRegistry;
use App\Modules\Platform\Theming\PublicThemeRegistry;
use App\Modules\Platform\Updates\Console\CheckForUpdatesCommand;
use App\Modules\Platform\Updates\Console\UpdateCommand;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocaleRegistry::class);
        $this->app->singleton(TimezoneRegistry::class);

        $this->app->singleton(Settings::class);

        // Singletons: themes register into these once per process (here,
        // and from the private cloud-modules package's ThemesServiceProvider
        // when installed) — a fresh instance per resolution would lose
        // whatever an earlier provider's boot() already registered.
        $this->app->singleton(PublicThemeRegistry::class);
        $this->app->singleton(EmailThemeRegistry::class);

        // Not a singleton: `PROJECTSEND_EDITION` never changes within a
        // running process (a real deployment recreates the container to
        // switch editions), so re-reading config() on each resolution costs
        // nothing there — but MailConfigApplier::apply() now resolves this
        // during boot() below on every request, and a cached singleton
        // instance would permanently bake in whatever edition was active at
        // the first boot, making tests' config()->set('projectsend.edition',
        // ...) (the established pattern — see EnsureCapabilityMiddlewareTest)
        // silently no-op for the rest of that test.
        $this->app->bind(CapabilityRegistry::class, function (): CapabilityRegistry {
            $edition = config('projectsend.edition');

            return new CapabilityRegistry(
                $edition instanceof Edition ? $edition : Edition::from($edition),
            );
        });

        // Every notification's mail rendering funnels through MailChannel —
        // rebinding it is how Setting::EmailTheme reaches all of them
        // without touching each Notification class individually.
        $this->app->bind(MailChannel::class, ThemedMailChannel::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateThemePreviewDataCommand::class,
                CheckForUpdatesCommand::class,
                UpdateCommand::class,
                FetchNewsCommand::class,
                DisableCaptchaCommand::class,
                TestCaptchaCommand::class,
                PurgeFailedJobsCommand::class,
                RefreshMailOAuthTokensCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Registered before apply() below can select it as the default
        // mailer. The closure resolves lazily on first send, so booting
        // never pays for a transport nobody uses.
        Mail::extend('microsoft-graph', fn (): MicrosoftGraphTransport => $this->app->make(MicrosoftGraphTransport::class));

        // Every process boot (a web request, or a freshly (re)started
        // queue worker) picks up the admin-configured mail provider, if
        // any — a no-op until the Email settings page is actually saved.
        $this->app->make(MailConfigApplier::class)->apply();

        // Same idea for the admin-configured external storage backend —
        // a no-op until the Storage settings page is actually saved. The
        // listener is what actually redirects new uploads away from the
        // local 'files' disk (see ResolvingUploadDisk's docblock).
        $this->app->make(ExternalStorageConfigApplier::class)->apply();
        Event::listen(ResolvingUploadDisk::class, [ExternalStorageConfigApplier::class, 'resolveDisk']);

        // Community-only observability (Capability::SchedulerMonitoring) —
        // registered unconditionally since it's cheap and inert either way;
        // the settings page/route reading these rows is what's actually
        // capability-gated.
        Event::listen(ScheduledTaskFinished::class, [RecordsScheduledTaskRuns::class, 'onFinished']);
        Event::listen(ScheduledTaskFailed::class, [RecordsScheduledTaskRuns::class, 'onFailed']);

        // In-app only — this is an internal "go check the dashboard"
        // nudge, not a mail-worthy event on its own; the dashboard's
        // System card is where the real external release link and
        // upgrade instructions live. url points at the dashboard rather
        // than the GitHub release page itself: notification clicks go
        // through Inertia's router.visit(), which isn't safe for a
        // cross-origin URL.
        $this->app->make(NotificationTypeRegistry::class)->register(new NotificationTypeDefinition(
            key: 'update_available',
            label: 'A new ProjectSend version is available',
            template: 'ProjectSend :latestVersion is available (you have :currentVersion)',
            url: fn (array $data) => route('dashboard'),
        ));

        // In-app only, like update_available — deliberately not mail:
        // this fires precisely when outgoing mail is broken, so an email
        // companion would either vanish into the dead transport or (on
        // the scheduled check) fail the very job reporting the problem.
        $this->app->make(NotificationTypeRegistry::class)->register(new NotificationTypeDefinition(
            key: 'mail_oauth_connection_broken',
            label: 'The connected mailbox can no longer send email',
            template: 'The :provider mailbox connection (:account) stopped working and needs to be reconnected',
            url: fn (array $data) => route('system-settings.email.edit'),
        ));

        // Core's free themes — available in every edition, gated by
        // nothing. A genuinely edition-exclusive theme would instead
        // register into these same singletons from a private package's
        // own ThemesServiceProvider (cloud-modules or community-modules,
        // whichever edition it's exclusive to), when installed — order
        // between core and a package doesn't matter, they register
        // distinct keys. `gallery`/`branded` lived in community-modules
        // briefly (2026-07-31) under the mistaken assumption they were
        // community-exclusive; they're free-for-everyone, so they belong
        // here instead, not gated behind either package.
        $publicThemes = $this->app->make(PublicThemeRegistry::class);
        $publicThemes->register('default', 'Default', __('A clean, neutral layout that works well for any kind of file sharing.'));
        $publicThemes->register('compact', 'Compact', __('A dense, spreadsheet-style list that fits more files on screen — best for large collections and frequent uploaders.'));
        $publicThemes->register('drive', 'Drive', __('A spacious, colorful layout inspired by cloud storage apps, with clear file-type icons and generous spacing.'));
        $publicThemes->register('gallery', 'Gallery', __('A full-width photo grid built for visual browsing — the best choice for photographers and image-heavy collections.'));

        $emailThemes = $this->app->make(EmailThemeRegistry::class);
        $emailThemes->register('default', 'Default', __("ProjectSend's classic email look — simple and neutral, and pairs well with any public/portal theme."));
        $emailThemes->register('minimal', 'Minimal', __('A stripped-down, understated design with no extra styling — pairs with the Compact look.'));
        // Same key as the public/portal 'drive' theme above — every
        // theme should ship as a matched pair across both surfaces (see
        // ThemeRegistry's docblock), so a tenant that picks one "look"
        // gets it consistently everywhere, not a mismatched public site
        // and inbox.
        $emailThemes->register('drive', 'Drive', __('Blue accents and clean structure inspired by cloud storage apps — pairs with the Drive look.'));
        // Paired with 'gallery' above (mismatched key names predate the
        // same-key convention, see ThemeRegistry's docblock). No custom
        // logo integration — just the stock ProjectSend mark; Cloud's
        // Branding module (private cloud-modules package) is a
        // completely separate, edition-exclusive feature.
        $emailThemes->register(
            'branded',
            'Branded',
            __('A bold header built around your logo — pairs with the Gallery look for a polished, on-brand inbox.'),
            null,
            fn (): array => ['logo_url' => asset('apple-touch-icon.png')],
        );
    }
}
