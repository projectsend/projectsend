<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

/**
 * Every application setting, typed and with its default in code — the
 * replacement for v1's ~230 loose option rows read per request. The
 * database stores overrides only; an absent row means the default.
 *
 * Add a case here (plus type and default below) when a feature gains a
 * setting. Settings are per-install.
 */
enum Setting: string
{
    case SiteName = 'site_name';

    // Client self-registration (v1 parity: clients_can_register /
    // clients_auto_approve). Consumed by the Clients module.
    case ClientsCanRegister = 'clients_can_register';
    case ClientsAutoApprove = 'clients_auto_approve';

    // Registration group options (v1 parity): auto-join a group on
    // signup (0 = off), and which groups registrants may request
    // membership to (none | public | all). Consumed by Clients/Groups.
    case ClientsAutoGroup = 'clients_auto_group';
    case ClientsCanSelectGroup = 'clients_can_select_group';

    // Days a denied membership request blocks re-requesting (0 = none).
    case ClientsMembershipDenyCooldownDays = 'clients_membership_deny_cooldown_days';

    // Whether the client portal offers inline preview at all — the whole
    // affordance, images included, not just the media types. Staff are
    // never gated by it: it exists so an installation can decide that a
    // client either downloads a file or does not get it, without taking
    // the tool away from the people who administer the library. See
    // PreviewKind and FileThumbnailController::preview.
    case ClientsCanPreviewFiles = 'clients_can_preview_files';

    // Maximum upload size in MB (0 = unlimited).
    case MaxFileSizeMb = 'max_file_size_mb';

    // Prefills a new client's storage_quota_mb at creation time only —
    // not a runtime fallback (0 = unlimited). Consumed by ClientsController.
    case DefaultClientStorageQuotaMb = 'default_client_storage_quota_mb';

    // Security: who must have two-factor authentication enabled
    // (none | staff | clients | all) — see TwoFactorEnforcement.
    case TwoFactorEnforcement = 'two_factor_enforcement';

    // The two halves of the password policy, read by PasswordPolicy and
    // applied through Password::defaults() at every field that *sets* a
    // password. Deliberately not a composition policy: NIST SP 800-63B
    // advises against mandatory uppercase/number/symbol rules and for
    // length plus breach-checking, which is what these two express.
    //
    // The minimum is clamped to 8..128 on read, so no stored value can
    // take an installation below Laravel's own default.
    case PasswordMinLength = 'password_min_length';

    // Whether a new password is checked against the haveibeenpwned breach
    // corpus. The check is k-anonymous (only a SHA-1 prefix leaves the
    // server) and fails open, so this exists for outbound egress and
    // latency on isolated installs, not for correctness.
    case PasswordRejectBreached = 'password_reject_breached';

    // Master switch for transactional email (file shared, account
    // approved/denied, …). Off by default — a fresh install's mailer
    // isn't configured yet. SMTP transport itself is .env-only (no
    // credential storage in this table); see Setting::EmailNotificationsEnabled
    // usage in the Email settings page.
    case EmailNotificationsEnabled = 'email_notifications_enabled';

    // Freeform recipient list for admin-facing notifications (new client
    // registration, client upload) — not tied to specific User rows,
    // since "admin" isn't a role here, just whoever staff configure.
    // Seeded with the first administrator's email at setup/CLI creation.
    case AdminNotificationEmails = 'admin_notification_emails';

    // Security: who is restricted to Setting::AllowedUploadExtensions
    // (none | clients | all) — see UploadTypeRestriction. v1 parity:
    // "Limit file types uploading to".
    case UploadTypeRestriction = 'upload_type_restriction';

    // Lowercase extensions (no dots) accepted for upload when the
    // restriction above applies to the uploader. v1 parity: allowed_file_types.
    case AllowedUploadExtensions = 'allowed_upload_extensions';

    // File comments. Governed by settings rather than permission keys
    // because the roles screen is capability-gated off in the cloud
    // edition, and because anonymous authors have no role to hold a key
    // at all — see CommentAuthors' docblock.
    //
    // CommentsScope decides which files accept comments (CommentScope);
    // CommentsAuthors decides who may write one (CommentAuthors).
    // PublicCommentsEnabled is separate from both on purpose: without it,
    // a logged-in client on a public file could publish a world-visible
    // comment that no administrator ever decided to allow.
    case CommentsScope = 'comments_scope';
    case CommentsAuthors = 'comments_authors';
    case PublicCommentsEnabled = 'public_comments_enabled';
    // Only meaningful when CommentsAuthors is `everyone` — an anonymous
    // comment stays invisible until a moderator approves it.
    case CommentsGuestModeration = 'comments_guest_moderation';
    // How long after posting an author may still edit or delete their own
    // comment (0 disables editing entirely).
    case CommentsEditWindowMinutes = 'comments_edit_window_minutes';

    // Largest zip download that can be asked for, in MB (0 = unlimited).
    // The cap is on total size rather than on file count because bytes are
    // what a build actually costs — worker time, the temp copies a remote
    // disk needs, and the archive itself. The file count has its own,
    // deliberately fixed, rail in ZipDownloadsController::MAX_FILES.
    case MaxZipDownloadSizeMb = 'max_zip_download_size_mb';

    // Whether a download's IP is recorded in the activity log (all |
    // anonymous_only | none). Only affects Action::FileDownloaded /
    // ShareLinkDownloaded entries — see ActivityLogger::shouldRecordIp().
    // v1 parity: privacy_record_downloads_ip_address.
    case DownloadIpLogging = 'download_ip_logging';

    // How long API request telemetry is kept before pruning. Distinct from
    // the activity log, which is an audit trail and is never pruned — see
    // the api_request_logs migration for why the two are separate.
    case ApiRequestLogRetentionDays = 'api_request_log_retention_days';

    // How long a permanently failed queue job is kept, and how long a
    // notification somebody has already read is kept. Both tables grow
    // with use and neither ever shrank on its own: the failed-jobs list
    // waited for somebody to press "Delete all failed", and nothing at
    // all pruned notifications. An installation nobody tends should not
    // pay for that with its database.
    //
    // 0 means keep indefinitely in both cases, the same explicit choice
    // ApiRequestLogRetentionDays offers.
    case FailedJobRetentionDays = 'failed_job_retention_days';
    case NotificationRetentionDays = 'notification_retention_days';

    // How many days a deleted account is retained (soft-deleted) before
    // PurgeErasuresCommand permanently erases it. Consumed by
    // ErasureSchedule, which every deletion path calls.
    case AccountErasureGraceDays = 'account_erasure_grace_days';

    // What the unattended erasure does with the files and folders a purged
    // account owned: 'cascade_delete' removes them, 'reassign' hands them to
    // AccountErasureReassignTo. The admin-initiated delete asks this per
    // account; the cron job cannot, so it follows this setting. Consumed by
    // AccountEraser.
    case AccountErasureContentAction = 'account_erasure_content_action';

    // The active account that inherits a purged account's content when
    // AccountErasureContentAction is 'reassign'. 0 = none chosen; if it is
    // unset or no longer valid at erase time, AccountEraser cascade-deletes
    // instead, so content is never left orphaned.
    case AccountErasureReassignTo = 'account_erasure_reassign_to';

    // Whether PurgeExpiredFilesCommand's daily run actually deletes
    // anything (off by default — deletion is destructive, so an admin
    // must opt in) and how many days after a file's own expires_at it
    // waits before doing so. Consumed by FileRetentionSettingsController
    // and PurgeExpiredFilesCommand.
    case ExpiredFilesAutoDeleteEnabled = 'expired_files_auto_delete_enabled';
    case ExpiredFilesDeleteAfterDays = 'expired_files_delete_after_days';

    // Whether PurgeOrphanFilesCommand's daily run actually deletes
    // anything (off by default) and how many days after an orphan is
    // first found it waits before doing so. Hardcoded off — regardless
    // of this stored value — whenever external storage is active; this
    // feature only ever operates on the local disk (see
    // PurgeOrphanFilesCommand and FileRetentionSettingsController).
    case OrphanFilesAutoDeleteEnabled = 'orphan_files_auto_delete_enabled';
    case OrphanFilesDeleteAfterDays = 'orphan_files_delete_after_days';

    // Emits <meta name="robots" content="noindex"> site-wide when true.
    // v1 parity: privacy_noindex_site.
    case DiscourageSearchIndexing = 'discourage_search_indexing';

    // Toggles only the browsable *directory* (the front page listing
    // every public group/file) — a specific public group's page and
    // downloads work off its own `public` flag regardless of this
    // setting, same as a share link isn't gated by any global toggle.
    // v1 parity: the public_listing_* feature, rescoped to v2's
    // group/file public flags instead of tokens. See PublicGroupsController.
    case PublicListingEnabled = 'public_listing_enabled';

    // The same switch as ClientsCanPreviewFiles, for the anonymous side:
    // whether a public file page offers to show the file as well as hand
    // it over. v1 parity: public_listing_enable_preview. See
    // PublicGroupsController::preview.
    case PublicListingPreviewEnabled = 'public_listing_preview_enabled';

    // The configurable base URL segment for the public listing (e.g.
    // "public" -> /public, /public/{group-slug}). Consumed by
    // PublicGroupsController's guard against every request's first path
    // segment.
    case PublicListingSlug = 'public_listing_slug';

    // The active theme (a ThemeRegistry key) shared by the guest public
    // pages and the client portal — one selection covers both, matching
    // v1's single selected_clients_template. See PublicThemeRegistry.
    case Theme = 'theme';

    // The active theme for outgoing notification emails — a header/footer
    // skin, independent of the per-notification subject/body editor
    // (EmailTemplate). See EmailThemeRegistry.
    case EmailTheme = 'email_theme';

    // Which of the installed translation catalogues the language switcher
    // actually offers. Installing a pack (dropping lang/{locale}.json in)
    // makes a language *available*; this makes it *offered*, so an
    // operator can run an English-and-Spanish site without the other
    // fourteen catalogues showing up on their clients' screens. English
    // is always offered whatever this says — it is the translation key,
    // not a catalogue. See LocaleRegistry.
    case EnabledLocales = 'enabled_locales';

    // The language everyone starts in — the last word after a signed-in
    // person's own preference and the browser's Accept-Language header,
    // and therefore what an anonymous visitor with no matching header
    // actually sees. English is only the default because it is the
    // default default; an operator whose clients are all Spanish should
    // not have to make each of them switch. Empty means "whatever
    // APP_LOCALE says", so an existing install that set the environment
    // variable keeps working until someone chooses here. Always resolved
    // through LocaleRegistry::defaultLocale(), which refuses to hand back
    // a language that is not enabled.
    case DefaultLocale = 'default_locale';

    // The clock everyone reads the application by — v1 parity: its
    // `timezone` option. Unlike v1, nothing here calls
    // date_default_timezone_set(): storage and computation stay UTC and
    // this is applied only where a date is shown or a calendar day is
    // asked for. That is what makes it safe for a signed-in user to
    // override it with their own (users.timezone) without any two
    // viewers disagreeing about what is actually stored.
    //
    // Empty means "whatever APP_TIMEZONE says", exactly like
    // DefaultLocale above — an install that set the environment variable
    // keeps working until someone chooses here. Always resolved through
    // TimezoneRegistry, which refuses to hand back an identifier tzdata
    // no longer knows.
    case Timezone = 'timezone';

    // Community-edition only (Capability::SystemUpdates) — self-hosted
    // opt-out for the daily "is a newer version available" check. There
    // is no in-app self-updater (see CheckForUpdatesCommand's docblock
    // for why); this only controls whether the dashboard's System card
    // is allowed to tell the admin a newer release exists.
    case CheckForUpdates = 'check_for_updates';

    // Cached result of the last update check — never written directly by
    // a settings form, only by CheckForUpdatesCommand. Empty string means
    // "no successful check yet" (fresh install, or checks disabled).
    case LatestKnownVersion = 'latest_known_version';
    case LatestVersionCheckedAt = 'latest_version_checked_at';

    // The rest of the cached release, for the "what's new" modal
    // (LatestReleaseInfo) — title/notes/url/date of the GitHub release
    // LatestKnownVersion came from. Notes is the release's raw Markdown
    // body, rendered as plain text on the frontend (never as HTML —
    // it's third-party content).
    case LatestReleaseTitle = 'latest_release_title';
    case LatestReleaseNotes = 'latest_release_notes';
    case LatestReleaseUrl = 'latest_release_url';
    case LatestReleasePublishedAt = 'latest_release_published_at';

    // The version `projectsend:update` last brought this database in line
    // with — written by that command only, never by a settings form.
    //
    // Its whole purpose is to be compared against config('projectsend.version')
    // as read by the *web* process, which is not the same number when a
    // manual install replaced its files and never reloaded PHP-FPM: OPcache
    // keeps serving the code it compiled before the update, silently, while
    // artisan reports the new version to the person trying to work out why.
    // See RunningCodeState.
    //
    // Written downwards as well as upwards, because the command runs on
    // every container boot and on every update.sh run — including the ones
    // that go backwards.
    case AppliedVersion = 'applied_version';
    case AppliedVersionAt = 'applied_version_at';

    // An update finished and the administrator has not been shown what it
    // brought yet — the two versions it moved between, so the page can
    // name every release in the gap rather than only the newest.
    //
    // Written by UpdateInstallation, and only for a genuine update of an
    // existing installation: a fresh install has nothing to catch up on,
    // and a container that reboots has not updated anything. Cleared when
    // the page is read. Both empty means nothing is waiting.
    case UpdateWelcomeFrom = 'update_welcome_from';
    case UpdateWelcomeTo = 'update_welcome_to';

    // This installation has just been installed and its administrator has
    // not been shown the getting-started page yet.
    //
    // Raised where the first administrator is created — the setup screen
    // and the provisioning command both — and cleared when the page is
    // read. False by default, which is what keeps an installation that
    // updates into this feature from being congratulated on an
    // installation it completed a year ago.
    case GettingStartedPending = 'getting_started_pending';

    // Cached result of the last dashboard news feed fetch — never written
    // directly by a settings form, only by FetchNewsCommand. Both editions
    // see this (unlike CheckForUpdates above, which is Community-only);
    // gated on the view_news permission, not a capability. Each item's
    // `content` is HTML-Purified at fetch time (see FetchNewsCommand), so
    // it's already safe to render — unlike LatestReleaseNotes above, this
    // one legitimately needs inline HTML (links to the full changelog).
    case NewsItems = 'news_items';
    case NewsLastFetchedAt = 'news_last_fetched_at';

    // CAPTCHA on public forms (v1 parity: captcha_method). Which service
    // is active — 'none' or a CaptchaProvider value — and, on cloud only,
    // whether the platform's own keys are used ('managed') or this
    // installation's ('own'). The credentials themselves are not here:
    // this store has no encrypted type, so they live in captcha_providers.
    case CaptchaProvider = 'captcha_provider';
    case CaptchaKeySource = 'captcha_key_source';

    // Which forms actually ask for a token. Separate switches because an
    // installation that takes no self-registrations, or does not want a
    // challenge in front of its own staff login, should be able to say so
    // without turning the whole feature off.
    case CaptchaOnLogin = 'captcha_on_login';
    case CaptchaOnRegistration = 'captcha_on_registration';
    case CaptchaOnPasswordReset = 'captcha_on_password_reset';
    case CaptchaOnPublicComments = 'captcha_on_public_comments';

    public function type(): SettingType
    {
        return match ($this) {
            self::SiteName,
            self::ClientsCanSelectGroup,
            self::TwoFactorEnforcement,
            self::UploadTypeRestriction,
            self::DownloadIpLogging,
            self::CommentsScope,
            self::CommentsAuthors,
            self::PublicListingSlug,
            self::DefaultLocale,
            self::Timezone,
            self::Theme,
            self::EmailTheme,
            self::LatestKnownVersion,
            self::LatestVersionCheckedAt,
            self::LatestReleaseTitle,
            self::LatestReleaseNotes,
            self::LatestReleaseUrl,
            self::LatestReleasePublishedAt,
            self::AppliedVersion,
            self::AppliedVersionAt,
            self::UpdateWelcomeFrom,
            self::UpdateWelcomeTo,
            self::NewsLastFetchedAt,
            self::CaptchaProvider,
            self::CaptchaKeySource,
            self::AccountErasureContentAction => SettingType::String,

            self::ClientsCanRegister,
            self::ClientsAutoApprove,
            self::EmailNotificationsEnabled,
            self::DiscourageSearchIndexing,
            self::PublicListingEnabled,
            self::ClientsCanPreviewFiles,
            self::PublicListingPreviewEnabled,
            self::CheckForUpdates,
            self::ExpiredFilesAutoDeleteEnabled,
            self::PublicCommentsEnabled,
            self::CommentsGuestModeration,
            self::OrphanFilesAutoDeleteEnabled,
            self::CaptchaOnLogin,
            self::CaptchaOnRegistration,
            self::CaptchaOnPasswordReset,
            self::CaptchaOnPublicComments,
            self::PasswordRejectBreached,
            self::GettingStartedPending => SettingType::Boolean,

            self::ClientsAutoGroup,
            self::ClientsMembershipDenyCooldownDays,
            self::MaxFileSizeMb,
            self::MaxZipDownloadSizeMb,
            self::DefaultClientStorageQuotaMb,
            self::AccountErasureGraceDays,
            self::AccountErasureReassignTo,
            self::ApiRequestLogRetentionDays,
            self::FailedJobRetentionDays,
            self::NotificationRetentionDays,
            self::ExpiredFilesDeleteAfterDays,
            self::CommentsEditWindowMinutes,
            self::OrphanFilesDeleteAfterDays,
            self::PasswordMinLength => SettingType::Integer,

            self::AdminNotificationEmails,
            self::AllowedUploadExtensions,
            self::EnabledLocales,
            self::NewsItems => SettingType::Json,
        };
    }

    // Widen this union as settings with new types are added — PHPStan
    // enforces that it matches what the cases actually return.
    /**
     * @return string|bool|int|list<string>|list<array{title: string, date: string, content: string, link: string}>
     */
    public function default(): string|bool|int|array
    {
        return match ($this) {
            self::SiteName => 'ProjectSend',
            self::TwoFactorEnforcement => 'none',
            // 12 rather than Laravel's 8: this was the value security audit
            // finding 14 settled on, and the setting exists so an
            // installation can move it, not so an upgrade can lower it.
            self::PasswordMinLength => 12,
            self::PasswordRejectBreached => true,
            self::ClientsCanSelectGroup => 'none',
            self::CaptchaProvider => 'none',
            // Inert without Capability::CaptchaManagedKeys, so this is the
            // default only in the edition where it means anything. A cloud
            // tenant is protected from the first request rather than after
            // finding the screen.
            self::CaptchaKeySource => 'managed',

            self::ClientsCanRegister,
            self::ClientsAutoApprove,
            self::EmailNotificationsEnabled,
            self::DiscourageSearchIndexing,
            self::PublicListingEnabled,
            self::ExpiredFilesAutoDeleteEnabled,
            self::PublicCommentsEnabled,
            self::OrphanFilesAutoDeleteEnabled => false,

            self::CheckForUpdates,
            self::CommentsGuestModeration,
            // On, so that an installation updating into these switches
            // keeps the preview it already had rather than losing it to a
            // setting nobody has seen yet.
            self::ClientsCanPreviewFiles,
            self::PublicListingPreviewEnabled,
            // On by default, but only ever consulted once a provider is
            // configured — so a fresh install is not protecting forms it
            // has no keys for.
            self::CaptchaOnLogin,
            self::CaptchaOnRegistration,
            self::CaptchaOnPasswordReset,
            self::CaptchaOnPublicComments => true,

            self::ClientsAutoGroup => 0,
            self::ClientsMembershipDenyCooldownDays => 30,
            self::MaxFileSizeMb => 2048,
            self::MaxZipDownloadSizeMb => 2048,
            self::DefaultClientStorageQuotaMb => 0,
            self::AccountErasureGraceDays => 30,
            self::AccountErasureReassignTo => 0,
            self::ApiRequestLogRetentionDays => 30,
            self::FailedJobRetentionDays => 30,
            self::NotificationRetentionDays => 90,
            self::ExpiredFilesDeleteAfterDays => 30,
            self::OrphanFilesDeleteAfterDays => 30,
            self::CommentsEditWindowMinutes => 15,

            self::AdminNotificationEmails => [],

            // English only out of the box: a fresh install offering
            // sixteen languages nobody asked for is noise, and an
            // operator who wants more turns them on deliberately.
            self::EnabledLocales => ['en'],

            self::UploadTypeRestriction => 'all',
            self::DownloadIpLogging => 'all',
            // Erasure removes a person's data, so deleting the files they
            // uploaded is the sensible zero-config default; reassign is opt-in
            // and needs a fallback account chosen.
            self::AccountErasureContentAction => 'cascade_delete',
            // Commenting is on for every file out of the box, but only
            // between people who are logged in: reaching the public
            // requires PublicCommentsEnabled, which is off by default.
            self::CommentsScope => 'all',
            self::CommentsAuthors => 'staff_and_clients',
            self::PublicListingSlug => 'public',
            self::DefaultLocale => '',
            self::Timezone => '',
            self::Theme => 'default',
            self::EmailTheme => 'default',
            self::LatestKnownVersion => '',
            self::LatestVersionCheckedAt => '',
            self::LatestReleaseTitle => '',
            self::LatestReleaseNotes => '',
            self::LatestReleaseUrl => '',
            self::LatestReleasePublishedAt => '',
            // Empty means no update has ever been applied through the
            // command — a fresh install, or one that predates it. Never a
            // notice, in either direction.
            self::AppliedVersion => '',
            self::AppliedVersionAt => '',
            self::UpdateWelcomeFrom => '',
            self::UpdateWelcomeTo => '',
            // False, so that an installation which updates into this
            // feature is not welcomed to an installation it finished a
            // year ago. Only creating the first administrator raises it.
            self::GettingStartedPending => false,
            self::NewsLastFetchedAt => '',

            self::NewsItems => [],

            // Documents, images, audio, video, archives. Deliberately
            // excludes server-executable extensions (php, cgi, …) and
            // browser-renderable-with-script types (htm, html, svg) —
            // FileThumbnailController::preview() serves files inline
            // using the stored mime type, so an allowed .html/.svg
            // would execute script in the previewer's browser.
            self::AllowedUploadExtensions => [
                'pdf', 'doc', 'docx', 'docm', 'dot', 'dotx', 'rtf', 'txt', 'csv', 'odt', 'ott',
                'ppt', 'pptx', 'pptm', 'pps', 'ppsx', 'odp',
                'xls', 'xlsx', 'xlsm', 'xltx', 'ods',
                'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tif', 'tiff', 'webp', 'heic', 'psd', 'ai', 'eps',
                'mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac',
                'mp4', 'mov', 'avi', 'mpg', 'mpeg', 'webm', 'mkv', 'wmv',
                'zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'xz', 'iso',
            ],
        };
    }
}
