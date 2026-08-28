<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Modules\Clients\Http\Controllers\ClientSettingsController;
use App\Modules\Comments\Http\Controllers\CommentSettingsController;
use App\Modules\Files\Http\Controllers\DownloadSettingsController;
use App\Modules\Files\Http\Controllers\FileRetentionSettingsController;
use App\Modules\Files\Http\Controllers\UploadSettingsController;
use App\Modules\Identity\Http\Controllers\ApiTokensController;
use App\Modules\Identity\Http\Controllers\ConnectedAccountsController;
use App\Modules\Identity\Http\Controllers\LdapSettingsController;
use App\Modules\Identity\Http\Controllers\SecuritySettingsController;
use App\Modules\Identity\Http\Controllers\SocialLoginController;
use App\Modules\Identity\Http\Controllers\SocialLoginSettingsController;
use App\Modules\Identity\Http\Controllers\TwoFactorEnrollmentController;
use App\Modules\Notifications\Http\Controllers\NotificationPreferencesController;
use App\Modules\Platform\Branding\Http\Controllers\BrandingController;
use App\Modules\Platform\Http\Controllers\AboutController;
use App\Modules\Platform\Http\Controllers\CaptchaSettingsController;
use App\Modules\Platform\Http\Controllers\EmailOAuthController;
use App\Modules\Platform\Http\Controllers\EmailSettingsController;
use App\Modules\Platform\Http\Controllers\EmailTemplatesController;
use App\Modules\Platform\Http\Controllers\ExternalStorageSettingsController;
use App\Modules\Platform\Http\Controllers\GettingStartedController;
use App\Modules\Platform\Http\Controllers\LanguageSettingsController;
use App\Modules\Platform\Http\Controllers\PrivacySettingsController;
use App\Modules\Platform\Http\Controllers\PublicListingSettingsController;
use App\Modules\Platform\Http\Controllers\SchedulerMonitoringController;
use App\Modules\Platform\Http\Controllers\SystemSettingsController;
use App\Modules\Platform\Http\Controllers\ThemingSettingsController;
use App\Modules\Platform\Http\Controllers\WhatsNewController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Route::redirect()'s destination needs a leading slash, or Laravel
    // deliberately emits a *relative* Location header (stripping the
    // leading slash it would otherwise generate) instead of an absolute
    // one — see Illuminate\Routing\RedirectController. A relative
    // redirect resolves against the current path's directory, which
    // breaks the moment the redirect lives more than one segment deep
    // (e.g. system/settings -> system/settings/general would resolve to
    // system/system/settings/general). Always use an absolute path here.
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('settings/delete-account', [ProfileController::class, 'deleteAccount'])->name('profile.delete-account');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/two-factor', [TwoFactorEnrollmentController::class, 'show'])->name('two-factor.show');

    // Staff and clients alike: which providers you have connected is a
    // property of your account, not of your role.
    Route::get('settings/connected-accounts', [ConnectedAccountsController::class, 'edit'])
        ->name('connected-accounts.edit');
    Route::post('settings/connected-accounts/{provider}', [SocialLoginController::class, 'connect'])
        ->middleware('throttle:20,1,social-connect')
        ->name('connected-accounts.connect');
    Route::delete('settings/connected-accounts/{provider}', [ConnectedAccountsController::class, 'destroy'])
        ->name('connected-accounts.destroy');

    // Changing the state of the second factor re-proves the first one. A
    // stolen session is exactly the situation 2FA exists to survive, so
    // "disable 2FA" must not be reachable with nothing but that session.
    // `confirm` is deliberately outside this group: it is mid-enrollment,
    // already proves possession of the TOTP secret, and the enrollment it
    // completes was itself password-confirmed by `store`.
    Route::middleware('password.confirm')->group(function () {
        Route::post('settings/two-factor', [TwoFactorEnrollmentController::class, 'store'])->name('two-factor.enable');
        Route::post('settings/two-factor/recovery-codes', [TwoFactorEnrollmentController::class, 'regenerateRecoveryCodes'])
            ->name('two-factor.recovery-codes');
        Route::delete('settings/two-factor', [TwoFactorEnrollmentController::class, 'destroy'])->name('two-factor.disable');
    });

    // Named bucket — see the note in routes/auth.php. A bare `throttle:`
    // shares one counter with every other bare one, so confirming an
    // enrolment code used to draw on the same six as re-sending a
    // verification email.
    Route::post('settings/two-factor/confirm', [TwoFactorEnrollmentController::class, 'confirm'])
        ->middleware('throttle:6,1,two-factor-confirm')->name('two-factor.confirm');

    // API tokens are account-level credentials, so they live in the personal
    // settings section — but staff-only, because /api/v1 is staff-only.
    // Creating and revoking re-prove the password for the same reason the
    // two-factor block above does: a token outlives the session that minted
    // it, so a stolen session must not be enough to mint one.
    Route::middleware('staff')->group(function () {
        // `create` before `{token}`, or the literal segment is swallowed by
        // the id parameter — the same ordering rule as files/orphans in
        // routes/web.php.
        Route::get('settings/api-tokens/create', [ApiTokensController::class, 'create'])->name('api-tokens.create');
        Route::get('settings/api-tokens', [ApiTokensController::class, 'index'])->name('api-tokens.index');
        Route::get('settings/api-tokens/{token}/edit', [ApiTokensController::class, 'edit'])->name('api-tokens.edit');

        Route::middleware('password.confirm')->group(function () {
            Route::post('settings/api-tokens', [ApiTokensController::class, 'store'])->name('api-tokens.store');
            // Editing can widen what an already-issued secret may do, so it
            // re-proves the password exactly as minting does.
            Route::patch('settings/api-tokens/{token}', [ApiTokensController::class, 'update'])->name('api-tokens.update');
            Route::delete('settings/api-tokens/{token}', [ApiTokensController::class, 'destroy'])->name('api-tokens.destroy');
        });
    });

    Route::get('settings/notifications', [NotificationPreferencesController::class, 'edit'])->name('notification-preferences.edit');
    Route::put('settings/notifications', [NotificationPreferencesController::class, 'update'])->name('notification-preferences.update');

    // Read-only, so `staff` alone rather than the `can:edit_settings`
    // group below: the release you are running, its licence and where
    // its source lives are not a configuration secret, and the sidebar
    // footer links every staff member here regardless of what they may
    // edit.
    Route::middleware('staff')->get('system/about', AboutController::class)->name('system.about');

    // Where a new installation's administrator is sent on their first
    // visit, and a page any staff member can come back to. `staff` alone:
    // it is a list of links to screens they can already reach, and
    // QuickStart filters it to the ones they may actually use.
    Route::middleware('staff')
        ->get('system/getting-started', GettingStartedController::class)
        ->name('system.getting-started');

    // Where an administrator is sent after an update, and a page anyone
    // who may read About's environment block can revisit afterwards. The
    // capability keeps it off managed installations, where nobody signed
    // in here performed the update it thanks them for.
    Route::middleware(['staff', 'capability:system.updates', 'can:view_system_info'])
        ->get('system/whats-new', WhatsNewController::class)
        ->name('system.whats-new');

    // System-wide configuration — deliberately outside the account
    // settings section; it lives under its own sidebar entry, one route
    // per section (general today; clients, email, … as modules land).
    Route::middleware(['staff', 'can:edit_settings'])->group(function () {
        Route::redirect('system/settings', '/system/settings/general');
        Route::get('system/settings/general', [SystemSettingsController::class, 'edit'])->name('system-settings.edit');
        Route::patch('system/settings/general', [SystemSettingsController::class, 'update'])->name('system-settings.update');
        // Its own throttle bucket, like every other action route here: a
        // bare `throttle:5,1` is keyed on the domain and the address, so
        // it would share one allowance with everything else that omits a
        // name. The controller enforces manage_updates and the edition,
        // and applies an installation-wide cooldown of its own.
        Route::post('system/settings/check-for-updates', [SystemSettingsController::class, 'checkForUpdates'])
            ->middleware('throttle:5,1,check-for-updates-now')
            ->name('system-settings.check-for-updates');
        Route::get('system/settings/security', [SecuritySettingsController::class, 'edit'])->name('system-settings.security.edit');
        Route::patch('system/settings/security', [SecuritySettingsController::class, 'update'])->name('system-settings.security.update');
        Route::get('system/settings/clients', [ClientSettingsController::class, 'edit'])->name('system-settings.clients.edit');
        Route::patch('system/settings/clients', [ClientSettingsController::class, 'update'])->name('system-settings.clients.update');
        Route::get('system/settings/uploads', [UploadSettingsController::class, 'edit'])->name('system-settings.uploads.edit');
        Route::patch('system/settings/uploads', [UploadSettingsController::class, 'update'])->name('system-settings.uploads.update');
        Route::get('system/settings/downloads', [DownloadSettingsController::class, 'edit'])->name('system-settings.downloads.edit');
        Route::patch('system/settings/downloads', [DownloadSettingsController::class, 'update'])->name('system-settings.downloads.update');
        Route::get('system/settings/file-retention', [FileRetentionSettingsController::class, 'edit'])->name('system-settings.file-retention.edit');
        Route::patch('system/settings/file-retention', [FileRetentionSettingsController::class, 'update'])->name('system-settings.file-retention.update');
        Route::get('system/settings/comments', [CommentSettingsController::class, 'edit'])->name('system-settings.comments.edit');
        Route::patch('system/settings/comments', [CommentSettingsController::class, 'update'])->name('system-settings.comments.update');
        Route::get('system/settings/email', [EmailSettingsController::class, 'edit'])->name('system-settings.email.edit');
        Route::patch('system/settings/email', [EmailSettingsController::class, 'update'])->name('system-settings.email.update');
        Route::post('system/settings/email/test', [EmailSettingsController::class, 'sendTest'])->name('system-settings.email.test');
        // The OAuth mailbox behind the Microsoft 365 provider. Its own
        // throttle buckets like every action route here; the callback is
        // a GET because it is the provider redirecting the admin's
        // browser back, session and all.
        Route::post('system/settings/email/oauth/connect', [EmailOAuthController::class, 'connect'])
            ->middleware('throttle:20,1,mail-oauth-connect')
            ->name('system-settings.email.oauth.connect');
        Route::get('system/settings/email/oauth/callback', [EmailOAuthController::class, 'callback'])
            ->middleware('throttle:20,1,mail-oauth-callback')
            ->name('system-settings.email.oauth.callback');
        Route::delete('system/settings/email/oauth', [EmailOAuthController::class, 'disconnect'])
            ->name('system-settings.email.oauth.disconnect');
        // Deliberately outside any capability: group. LDAP is an
        // administrator's setting, available in every edition, not an
        // edition difference.
        Route::get('system/settings/ldap', [LdapSettingsController::class, 'edit'])->name('system-settings.ldap.edit');
        Route::patch('system/settings/ldap', [LdapSettingsController::class, 'update'])->name('system-settings.ldap.update');
        Route::post('system/settings/ldap/test', [LdapSettingsController::class, 'test'])->name('system-settings.ldap.test');

        // Outside any capability: group for the same reason as LDAP above.
        Route::get('system/settings/social-login', [SocialLoginSettingsController::class, 'edit'])
            ->name('system-settings.social-login.edit');
        Route::patch('system/settings/social-login/{provider}', [SocialLoginSettingsController::class, 'update'])
            ->name('system-settings.social-login.update');

        // Outside any capability: group for the same reason as LDAP above.
        // Only the option of using the platform's own keys is an edition
        // difference, and that is gated per field inside the controller.
        Route::get('system/settings/captcha', [CaptchaSettingsController::class, 'edit'])->name('system-settings.captcha.edit');
        Route::patch('system/settings/captcha', [CaptchaSettingsController::class, 'update'])->name('system-settings.captcha.update');
        Route::post('system/settings/captcha/test', [CaptchaSettingsController::class, 'test'])->name('system-settings.captcha.test');

        Route::get('system/settings/privacy', [PrivacySettingsController::class, 'edit'])->name('system-settings.privacy.edit');
        Route::patch('system/settings/privacy', [PrivacySettingsController::class, 'update'])->name('system-settings.privacy.update');
        Route::get('system/settings/public-listing', [PublicListingSettingsController::class, 'edit'])->name('system-settings.public-listing.edit');
        Route::patch('system/settings/public-listing', [PublicListingSettingsController::class, 'update'])->name('system-settings.public-listing.update');
        Route::get('system/settings/languages', [LanguageSettingsController::class, 'edit'])->name('system-settings.languages.edit');
        Route::patch('system/settings/languages', [LanguageSettingsController::class, 'update'])->name('system-settings.languages.update');
        Route::get('system/settings/theming', [ThemingSettingsController::class, 'edit'])->name('system-settings.theming.edit');
        Route::patch('system/settings/theming', [ThemingSettingsController::class, 'update'])->name('system-settings.theming.update');
        Route::get('system/settings/theming/email-preview/{key}', [ThemingSettingsController::class, 'previewEmail'])
            ->name('system-settings.theming.email-preview');

        // Every field on this page is Community-only (Capability::StorageConfigure)
        // — unlike email settings, there's no capability-independent
        // sub-field to keep serving in Cloud, so the whole surface is
        // gated at the route level instead of per-field in the controller.
        Route::middleware('capability:storage.configure')->group(function () {
            Route::get('system/settings/storage', [ExternalStorageSettingsController::class, 'edit'])->name('system-settings.storage.edit');
            Route::patch('system/settings/storage', [ExternalStorageSettingsController::class, 'update'])->name('system-settings.storage.update');
            Route::post('system/settings/storage/test', [ExternalStorageSettingsController::class, 'testConnection'])->name('system-settings.storage.test');
        });

        // Both editions since 2026-08-28, and gated all-or-nothing like
        // Storage above: read included, so a staff member who may not
        // change the logo never sees the page either. A managed
        // installation on a plan without branding has the capability
        // subtracted from its environment, and these 404 for it — the
        // instance refusing is the enforcement, not the portal's screen.
        Route::middleware('capability:branding.customize')->group(function () {
            Route::get('system/settings/branding', [BrandingController::class, 'edit'])->name('branding.edit');
            Route::post('system/settings/branding', [BrandingController::class, 'store'])->name('branding.store');
            Route::delete('system/settings/branding', [BrandingController::class, 'destroy'])->name('branding.destroy');

            // POST rather than PATCH: the form carries a file, so it is
            // multipart, and PHP only populates $_FILES for POST.
            Route::post('system/settings/branding/watermark', [BrandingController::class, 'updateWatermark'])->name('branding.watermark.update');
            Route::delete('system/settings/branding/watermark', [BrandingController::class, 'destroyWatermark'])->name('branding.watermark.destroy');

            // The live sample on the settings screen: a GET with the values
            // currently in the form, so it updates as they are adjusted
            // rather than only after a save.
            Route::get('system/settings/branding/watermark/sample', [BrandingController::class, 'watermarkSample'])->name('branding.watermark.sample');
        });

        // Same all-or-nothing shape as Storage above (Capability::SchedulerMonitoring).
        Route::middleware('capability:scheduler.monitoring')->group(function () {
            Route::get('system/settings/scheduler', [SchedulerMonitoringController::class, 'index'])->name('system-settings.scheduler.index');
            Route::post('system/settings/scheduler/failed-jobs/{uuid}/retry', [SchedulerMonitoringController::class, 'retryFailedJob'])->name('system-settings.scheduler.retry');
            Route::delete('system/settings/scheduler/failed-jobs/{uuid}', [SchedulerMonitoringController::class, 'destroyFailedJob'])->name('system-settings.scheduler.destroy');
            Route::delete('system/settings/scheduler/failed-jobs', [SchedulerMonitoringController::class, 'destroyAllFailedJobs'])->name('system-settings.scheduler.destroy-all');
            Route::patch('system/settings/scheduler/retention', [SchedulerMonitoringController::class, 'updateRetention'])->name('system-settings.scheduler.retention');
        });
    });

    // Distinct from edit_settings, matching v1's exact permission split.
    Route::middleware(['staff', 'can:edit_email_templates'])->group(function () {
        Route::get('system/settings/email-templates', [EmailTemplatesController::class, 'index'])->name('email-templates.index');
        Route::get('system/settings/email-templates/{slot}', [EmailTemplatesController::class, 'edit'])->name('email-templates.edit');
        Route::patch('system/settings/email-templates/{slot}', [EmailTemplatesController::class, 'update'])->name('email-templates.update');
        Route::delete('system/settings/email-templates/{slot}', [EmailTemplatesController::class, 'destroy'])->name('email-templates.destroy');
        Route::get('system/settings/email-templates/{slot}/preview', [EmailTemplatesController::class, 'preview'])->name('email-templates.preview');
    });
});
