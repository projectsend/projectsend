<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Comments\Access\VisibleCommentScope;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Identity\Passwords\PasswordPolicy;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\Social\SocialSettings;
use App\Modules\Identity\UserType;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Platform\Attribution\Attribution;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Installation\Installation;
use App\Modules\Platform\Localization\LocaleRegistry;
use App\Modules\Platform\Localization\TimezoneRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Updates\LatestReleaseInfo;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $capabilities = app(CapabilityRegistry::class);

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => app(Settings::class)->get(Setting::SiteName),
            'quote' => ['message' => trim((string) $message), 'author' => trim((string) $author)],
            'auth' => [
                'user' => $request->user(),
                'permissions' => ($user = $request->user()) !== null
                    ? app(PermissionChecker::class)->grantedKeys($user)
                    : [],
            ],
            'edition' => $capabilities->edition()->value,
            'noindex' => app(Settings::class)->get(Setting::DiscourageSearchIndexing),
            'version' => config('projectsend.version'),
            'links' => config('projectsend.links'),
            // Whether the client- and visitor-facing surfaces name
            // ProjectSend. True everywhere unless a package answers
            // otherwise — see ResolvingAttribution. Staff surfaces
            // ignore this and always show it.
            'attribution' => app(Attribution::class)->visible(),
            'capabilities' => $capabilities->enabledKeys(),
            // Shared rather than passed by each page: the sign-in buttons,
            // the registration form and the Connected accounts nav entry
            // all need the same list, and a nav entry to a screen with
            // nothing on it is worse than no entry.
            'social_login' => SocialSettings::available(),
            // Shared for the same reason: seven unrelated surfaces — three
            // auth pages and the file page of each public theme — need the
            // identical provider and site key. Null when nothing is
            // configured, and never the secret.
            'captcha' => app(Captcha::class)->forDisplay(),
            // Shared for the same reason again: eight forms across the auth
            // pages, the account settings and the staff/client editors all
            // ask somebody to choose a password, and each has to be able to
            // say what this installation will accept *before* the submit
            // rather than only in the error afterwards.
            'password_policy' => app(PasswordPolicy::class)->descriptor(),
            'pending' => $this->pendingCounts($request),
            'update_notice' => $this->updateNotice($request),
            'locale' => app()->getLocale(),
            // The clock this viewer reads dates by, and whether it is a
            // choice or a fallback. The frontend needs both: the first to
            // format with, the second because a viewer still on the
            // fallback is one whose browser we have not asked yet — see
            // timezone-detector.tsx.
            'timezone' => app(TimezoneRegistry::class)->resolve($request->user()),
            'timezone_is_explicit' => $request->user()?->timezone !== null,
            'locales' => app(LocaleRegistry::class)->enabled(),
            'locales_disabled' => $this->disabledLocaleCount($request),
            'translations' => $this->translations(app()->getLocale()),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    /**
     * Pending-approval counts for sidebar badges, computed only for
     * viewers holding the matching approval permission.
     *
     * @return array<string, int>
     */
    protected function pendingCounts(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        $checker = app(PermissionChecker::class);
        $counts = [];

        if ($checker->allows($user, Permission::ApproveAccountRequests)) {
            $counts['account_requests'] = User::query()
                ->where('type', UserType::Client)
                ->where('account_requested', true)
                ->count();
        }

        if ($checker->allows($user, Permission::ApproveGroupsMembershipsRequests)) {
            $counts['membership_requests'] = MembershipRequest::query()
                ->pending()
                ->whereHas('user')
                ->whereHas('group')
                ->count();
        }

        if ($checker->allows($user, Permission::ModerateComments)) {
            // Library-scoped, like the screen it badges: a client-scoped
            // staff member is not shown a number they cannot act on. The
            // permission check inside pendingTotal is therefore redundant
            // here and deliberately kept — the scope owns that rule, and
            // this middleware should not be a second place it lives.
            $counts['comments'] = app(VisibleCommentScope::class)->pendingTotal($user);
        }

        // Unlike the counts above, every authenticated user (staff or
        // client) has their own personal notifications — no permission
        // gate here.
        $counts['notifications_unread'] = InAppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return $counts;
    }

    /**
     * How many installed translation catalogues are currently switched off,
     * for the "N more languages available" line the switcher shows above its
     * link to the Languages screen.
     *
     * Zero for everyone who cannot act on it — clients, anonymous visitors on
     * the public pages and the login screen, and staff without edit_settings.
     * A dead-end link is worse than none, and how an installation is
     * configured is nobody else's business.
     */
    protected function disabledLocaleCount(Request $request): int
    {
        $user = $request->user();

        if ($user === null || ! $user->isStaff() || ! app(PermissionChecker::class)->allows($user, Permission::EditSettings)) {
            return 0;
        }

        $locales = app(LocaleRegistry::class);

        return count($locales->installed()) - count($locales->enabled());
    }

    /**
     * The topbar's persistent "update available" icon — unlike the
     * dashboard System card (informational, gated only on
     * view_system_info), this is the actionable surface, so it's
     * restricted to staff who actually hold manage_updates.
     *
     * Carries install_kind so the dialog can print instructions this
     * particular server can actually follow — see Installation. Attached
     * here rather than shared globally: it describes the deployment, which
     * is nobody's business but the staff who maintain it.
     *
     * @return array{version: string, title: string, notes: string, url: string, published_at: string, install_kind: string}|null
     */
    protected function updateNotice(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || ! app(PermissionChecker::class)->allows($user, Permission::ManageUpdates)) {
            return null;
        }

        $release = app(LatestReleaseInfo::class)->current();

        return $release === null
            ? null
            : [...$release, 'install_kind' => app(Installation::class)->kind()->value];
    }

    /**
     * App strings use English text as the translation key, so "en" ships no
     * messages — the key itself is the fallback.
     *
     * @return array<string, string>
     */
    protected function translations(string $locale): array
    {
        if ($locale === 'en') {
            return [];
        }

        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, string> */
        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
