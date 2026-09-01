<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Api\ApiUsage;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogScope;
use App\Modules\Audit\ActivityPresenter;
use App\Modules\Audit\DashboardWidgetPreferences;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Delivery\FileDelivery;
use App\Modules\Files\Models\File;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Installation\Installation;
use App\Modules\Platform\Localization\TimezoneRegistry;
use App\Modules\Platform\News\NewsItems;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Storage\StorageDurability;
use App\Modules\Platform\System\SystemEnvironment;
use App\Modules\Platform\Updates\LatestReleaseInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard (brief §6.11: dashboard & reporting live in Audit).
 * Staff widgets are permission-gated individually (v1 keys:
 * view_dashboard_counters, view_statistics, view_actions_log,
 * view_system_info); clients get their own portal variant.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly ClientStorageUsage $storageUsage,
        private readonly LatestReleaseInfo $latestRelease,
        private readonly NewsItems $newsItems,
        private readonly DashboardWidgetPreferences $widgetPrefs,
        private readonly Settings $settings,
        private readonly ApiUsage $apiUsage,
        private readonly StorageDurability $storageDurability,
        private readonly FileDelivery $fileDelivery,
        private readonly Installation $installation,
        private readonly TimezoneRegistry $timezones,
        private readonly SystemEnvironment $environment,
        private readonly ActivityPresenter $presenter,
        private readonly ActivityLogScope $scope,
        private readonly StaffLibraryScope $library,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        if ($user->isClient()) {
            return $this->clientDashboard($user);
        }

        $timezone = $this->timezones->resolve($user);

        [$from, $to, $preset] = $this->resolveTransferRange($request, $timezone);

        $canCounters = $user->can('view_dashboard_counters');
        $canStatistics = $user->can('view_statistics');
        $canActionsLog = $user->can('view_actions_log');
        $canSystem = $user->can('view_system_info') && $this->capabilities->has(Capability::SystemUpdates);
        $canNews = $user->can('view_news');
        $canExpiredFiles = $user->can('view_statistics');

        $prefs = $this->widgetPrefs;

        return Inertia::render('dashboard', [
            'counters' => $canCounters && $prefs->isEnabled($user, 'counters') ? $this->counters() : null,
            'transfers' => $canStatistics && $prefs->isEnabled($user, 'transfers') ? $this->transferSeries($from, $to, $timezone) : null,
            'transfers_range' => $canStatistics && $prefs->isEnabled($user, 'transfers')
                ? ['preset' => $preset, 'from' => $from->toDateString(), 'to' => $to->toDateString()]
                : null,
            'top_clients_by_storage' => $canStatistics && $prefs->isEnabled($user, 'top_clients_by_storage')
                ? $this->topClientsByStorage($user)
                : null,
            'largest_files' => $canStatistics && $prefs->isEnabled($user, 'largest_files') ? $this->largestFiles($user) : null,
            'recent' => $canActionsLog && $prefs->isEnabled($user, 'recent') ? $this->recentActivity($user) : null,
            'system' => $canSystem && $prefs->isEnabled($user, 'system') ? $this->systemInfo() : null,
            // Both editions — informational content, not an update action,
            // so no Capability check alongside the permission (unlike
            // 'system' above).
            'news' => $canNews && $prefs->isEnabled($user, 'news') ? $this->newsItems->current() : null,
            'expired_files' => $canExpiredFiles && $prefs->isEnabled($user, 'expired_files') ? $this->expiredFiles($user) : null,
            // Not permission-gated: every staff member has tokens to look
            // after, and it scopes itself to the viewer's own — the same
            // rule the API dashboard applies.
            'api' => $prefs->isEnabled($user, 'api') ? $this->apiUsage($user) : null,

            // The layout is filtered to only the keys this viewer holds
            // permission for — never reveal a permission-hidden widget's
            // existence to the Widgets modal, same "don't tease
            // unavailable features" convention as EnsureCapability's 404.
            'widget_layout' => $prefs->layoutFor($user, $this->permittedWidgetKeys([
                'counters' => $canCounters,
                'transfers' => $canStatistics,
                'top_clients_by_storage' => $canStatistics,
                'largest_files' => $canStatistics,
                'recent' => $canActionsLog,
                'system' => $canSystem,
                'news' => $canNews,
                'expired_files' => $canExpiredFiles,
                'api' => true,
            ])),
            'dashboard_columns' => $prefs->columnsFor($user),
        ]);
    }

    /**
     * A glance at the viewer's own API usage, with the detail a click away
     * on the API dashboard.
     *
     * @return array<string, mixed>
     */
    private function apiUsage(User $user): array
    {
        return [
            'requests_7d' => $this->apiUsage->requestsSince($user, false, now()->subDays(7)),
            'tokens' => $user->tokens()->count(),
            'expired_tokens' => $user->tokens()->whereNotNull('expires_at')->where('expires_at', '<', now())->count(),
            'last_used_at' => $user->tokens()->max('last_used_at'),
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return list<string>
     */
    private function permittedWidgetKeys(array $permissions): array
    {
        return array_keys(array_filter($permissions));
    }

    /**
     * Reads the Transfers widget's date-range controls off the query
     * string (?range=last_week|last_month|previous_month|custom, plus
     * ?from=&to= for custom). Defaults to last_month — the same rolling
     * 30-day window the widget always showed before the selector existed.
     *
     * Every boundary is built in the viewer's zone, so "last week" ends
     * when their evening does and not at whatever hour UTC midnight falls
     * on for them. The instants are absolute, but they carry that zone —
     * and a Carbon handed to the query builder is formatted in its own
     * zone, offset discarded, so comparing one against a UTC column asks
     * a question nine hours out for a viewer in Tokyo. transferSeries()
     * converts before it compares; the day cursor there keeps them as
     * they are, because that half really is about the viewer's calendar.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveTransferRange(Request $request, string $timezone): array
    {
        $preset = $request->query('range');
        $now = fn (): Carbon => now()->setTimezone($timezone);

        return match ($preset) {
            'last_week' => [$now()->subDays(6)->startOfDay(), $now()->endOfDay(), 'last_week'],
            'previous_month' => (function () use ($now): array {
                $previousMonth = $now()->subMonth();

                return [$previousMonth->copy()->startOfMonth(), $previousMonth->copy()->endOfMonth(), 'previous_month'];
            })(),
            'custom' => $this->resolveCustomTransferRange($request, $timezone),
            default => [$now()->subDays(29)->startOfDay(), $now()->endOfDay(), 'last_month'],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveCustomTransferRange(Request $request, string $timezone): array
    {
        $now = fn (): Carbon => now()->setTimezone($timezone);

        try {
            $from = $request->query('from') !== null ? Carbon::parse($request->query('from'), $timezone)->startOfDay() : null;
            $to = $request->query('to') !== null ? Carbon::parse($request->query('to'), $timezone)->endOfDay() : null;
        } catch (\Throwable) {
            return [$now()->subDays(29)->startOfDay(), $now()->endOfDay(), 'last_month'];
        }

        $from ??= $now()->subDays(29)->startOfDay();
        $to ??= $now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        // A year is plenty for a "custom range" on a daily-granularity
        // chart — anything longer just clips to the most recent year
        // rather than rendering an unreadable multi-year line.
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366)->startOfDay();
        }

        return [$from, $to, 'custom'];
    }

    /**
     * @return array<string, int>
     */
    private function counters(): array
    {
        // Deliberately installation-wide, unlike the three widgets below.
        // A total carries no names — "417 files" tells a scoped viewer
        // nothing about whose they are — and the same reasoning leaves
        // transferSeries() alone. If that ever stops being the line, both
        // move together.

        return [
            'files' => File::query()->count(),
            'files_bytes' => (int) File::query()->sum('size'),
            'clients' => User::query()->where('type', UserType::Client)->count(),
            'groups' => Group::query()->count(),
            'users' => User::query()->where('type', UserType::Staff)->count(),
        ];
    }

    /**
     * Uploads vs downloads per day across the given range, zero-filled.
     * Downloads split into "by clients" and "anonymous" — anonymous
     * covers unauthenticated share-link/public-listing downloads, the
     * traffic an admin has no other visibility into. Staff downloads
     * (an admin grabbing a file to check it) count toward neither
     * bucket; they're not the audience-facing traffic this chart tracks.
     *
     * @return list<array{date: string, uploads: int, downloads_clients: int, downloads_anonymous: int}>
     */
    private function transferSeries(Carbon $from, Carbon $to, string $timezone): array
    {
        // Two forms of the same set: string values for the SQL whereIn,
        // enum cases for filtering the already-cast `action` attribute
        // once loaded — ActivityLog::casts() turns it into an Action
        // instance, which a raw string never loosely equals.
        $downloadActions = [Action::FileDownloaded, Action::ShareLinkDownloaded, Action::PublicFileDownloaded];

        $rows = ActivityLog::query()
            ->whereIn('action', [Action::FileUploaded->value, ...array_map(fn (Action $a): string => $a->value, $downloadActions)])
            // In UTC, because that is what the column is. The query
            // builder formats a Carbon in whatever zone the object holds
            // and drops the offset, so passing the viewer's midnight
            // straight in compares "2026-08-22 00:00:00" against a UTC
            // column — nine hours of somebody else's day, at both ends,
            // for a viewer in Tokyo.
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->get(['action', 'actor_type', 'created_at'])
            // Bucketed by the viewer's calendar day. Grouping on the UTC
            // one puts an evening upload from anywhere west of Greenwich
            // on tomorrow's bar, which the person who made it reads as
            // the chart being a day out.
            ->groupBy(fn (ActivityLog $entry): string => $entry->created_at->copy()->setTimezone($timezone)->format('Y-m-d'));

        $series = [];
        // $from and $to already carry the viewer's zone (see
        // resolveTransferRange), so these day keys line up with the
        // grouping above.
        $cursor = $from->copy()->startOfDay();
        $lastDay = $to->copy()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $date = $cursor->format('Y-m-d');
            $entries = $rows->get($date, collect());
            $downloads = $entries->whereIn('action', $downloadActions);

            $series[] = [
                'date' => $date,
                'uploads' => $entries->where('action', Action::FileUploaded)->count(),
                'downloads_clients' => $downloads->where('actor_type', UserType::Client->value)->count(),
                'downloads_anonymous' => $downloads->whereNull('actor_type')->count(),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * The 5 clients using the most storage, each against their own
     * effective quota (own override, or the site default — see
     * ClientStorageUsage::quotaMb()) so the widget reads the same way
     * the client-facing usage box does.
     *
     * @return list<array{id: int, name: string, used_bytes: int, quota_mb: int}>
     */
    private function topClientsByStorage(User $viewer): array
    {
        // Narrowed by roster, not by library. This widget names *clients*,
        // and files() is the wrong lens for that: a stranger client's
        // upload can be inside a scoped viewer's library — shared with a
        // group one of their own clients is in — which put the stranger's
        // name on the widget. Measured: a client called "Stranger Client
        // Ltd", on nobody's roster, ranked on a scoped dashboard.
        // assignableClientIds is the question actually being asked.
        $clientIds = $this->library->assignableClientIds($viewer);

        $rows = File::query()
            ->select('uploaded_by', DB::raw('SUM(size) as total_bytes'))
            ->whereHas('uploader', fn ($query) => $query->where('type', UserType::Client))
            ->when($clientIds !== null, fn (Builder $query) => $query->whereIn('uploaded_by', $clientIds))
            ->groupBy('uploaded_by')
            ->orderByDesc('total_bytes')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $clients = User::query()->whereIn('id', $rows->pluck('uploaded_by'))->get()->keyBy('id');

        $result = [];

        foreach ($rows as $row) {
            $client = $clients->get($row->uploaded_by);

            if ($client === null) {
                continue;
            }

            $result[] = [
                'id' => (int) $row->uploaded_by,
                'name' => $client->name,
                'used_bytes' => (int) $row->getAttribute('total_bytes'),
                'quota_mb' => $this->storageUsage->quotaMb($client),
            ];
        }

        return $result;
    }

    /**
     * The 10 largest individual files on the installation, regardless of
     * uploader — catches a single space hog that per-client aggregates
     * (topClientsByStorage()) can't surface on their own.
     *
     * Edit/download/uploader links are coarse-gated on the viewer's own
     * permissions only (same level of precision ActivityLogController's
     * own link resolver uses) — not a per-file StaffLibraryScope check,
     * so a client-scoped staff member could still see a link here that
     * 403s if clicked. Accepted, matching existing precedent, rather
     * than adding per-row scope checks to a 10-row dashboard widget.
     *
     * @return list<array{id: int, name: string, size: int, uploader_name: ?string, created_at: string, edit_url: ?string, download_url: ?string, uploader_edit_url: ?string}>
     */
    private function largestFiles(User $viewer): array
    {
        // FilePolicy::view() — the ability both files.edit and
        // files.download authorize against — requires one of these for
        // staff. Client-facing files never appear here as an uploader
        // (clients don't have "view" gated the same way), so this is the
        // one check both links share.
        $canFiles = $viewer->can('upload') || $viewer->can('edit_files') || $viewer->can('edit_others_files');
        $canClients = $viewer->can('edit_clients');
        $staffModule = $this->capabilities->has(Capability::UsersManage) && $viewer->can('manage_users');
        $canStaffUsers = $staffModule && $viewer->can('edit_users');

        // Narrowed to the viewer's library, not just its links. The
        // note above is about a link that 403s; a row that should not be
        // here at all is a different problem, and the file's *name* is
        // the part that leaks — "Q3 delinquent accounts" says plenty
        // without being downloadable. Scoping the query costs one call:
        // StaffLibraryScope builds a scoped user's query once per
        // request, so this is not a per-row check.
        return array_values($this->library->files($viewer)
            ->with('uploader:id,name,type')
            ->orderByDesc('size')
            ->limit(10)
            ->get(['id', 'name', 'size', 'uploaded_by', 'created_at'])
            ->map(function (File $file) use ($canFiles, $canClients, $canStaffUsers): array {
                $uploader = $file->uploader; // uploaded_by is nullOnDelete — may be gone.

                return [
                    'id' => $file->id,
                    'name' => $file->name,
                    'size' => $file->size,
                    'uploader_name' => $uploader?->name,
                    'created_at' => $file->created_at?->toIso8601String() ?? '',
                    'edit_url' => $canFiles ? route('files.edit', $file->id, false) : null,
                    'download_url' => $canFiles ? route('files.download', $file->id, false) : null,
                    'uploader_edit_url' => match (true) {
                        $uploader === null => null,
                        $uploader->type === UserType::Client && $canClients => route('clients.edit', $uploader->id, false),
                        $uploader->type === UserType::Staff && $canStaffUsers => route('users.edit', $uploader->id, false),
                        default => null,
                    },
                ];
            })->all());
    }

    /**
     * The 10 soonest-expired files still awaiting the daily purge, plus
     * enough context (is auto-delete even on, when does the job next run)
     * for the widget to explain what's about to happen to them.
     *
     * @return array{count: int, files: list<array{id: int, name: string, expires_at: ?string, edit_url: ?string}>, auto_delete_enabled: bool, next_run_at: string}
     */
    private function expiredFiles(User $viewer): array
    {
        // Same coarse gate largestFiles() uses for its edit links.
        $canFiles = $viewer->can('upload') || $viewer->can('edit_files') || $viewer->can('edit_others_files');

        return [
            // Both the count and the list read the viewer's library, so
            // the number cannot describe files the list is not allowed to
            // name. Same reason largestFiles() is scoped.
            //
            // Narrower than it looks for a client-scoped viewer:
            // File::scopeVisibleToClient ends in notExpired(), so an
            // expired file belonging to one of their clients is not in
            // their library, and only their own expired uploads reach
            // this list. Rather than widen the boundary — which would
            // mean a library query that keeps expired rows, and
            // scopeVisibleToClient is the single source of truth for
            // client file access — the widget says what it is showing.
            // `scoped` is how it knows to.
            'count' => $this->library->files($viewer)->expired()->count(),
            'files' => array_values($this->library->files($viewer)->expired()->orderBy('expires_at')->limit(10)
                ->get(['id', 'name', 'expires_at'])
                ->map(fn (File $file): array => [
                    'id' => $file->id,
                    'name' => $file->name,
                    'expires_at' => $file->expires_at?->toIso8601String(),
                    'edit_url' => $canFiles ? route('files.edit', $file->id, false) : null,
                ])->all()),
            // Whether this list is "everything expired" or "everything of
            // yours that expired" — a widget whose whole job is warning
            // about what is due to be deleted has to say which it means.
            'scoped' => $viewer->isClientScoped(),
            'auto_delete_enabled' => (bool) $this->settings->get(Setting::ExpiredFilesAutoDeleteEnabled),
            // Schedule::command('projectsend:purge-expired-files')->daily()
            // runs at 00:00 — always "tonight" from whenever this loads.
            'next_run_at' => now()->copy()->startOfDay()->addDay()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(User $viewer): array
    {
        // Narrowed through ActivityLogScope, exactly as the activity page and
        // the download history are. `view_actions_log` is not the whole
        // answer for a client-scoped viewer: a log entry carries the
        // subject's name, so an unscoped one reads out the name of every
        // file in the installation and who touched it, to somebody who gets
        // a 403 on the files themselves. The Client Manager role ships with
        // the permission, so this is the default configuration.
        //
        // Presented through the shared ActivityPresenter, not rebuilt inline —
        // the same sentence-ready shape the activity page and detail panels
        // use. Rebuilding it here once dropped `origin`, which is the only
        // thing that tells an actorless "Anonymous" entry from a "System" one.
        return $this->scope->apply(ActivityLog::query(), $viewer)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (ActivityLog $entry): array => $this->presenter->present($entry))
            ->all();
    }

    /**
     * @return array<string, array<string, bool|string|null>|bool|int|string|null>
     */
    private function systemInfo(): array
    {
        $freeBytes = @disk_free_space(storage_path('app/files'));

        // Cached by CheckForUpdatesCommand (daily) — never a live HTTP
        // call from the request path. null means either no successful
        // check yet, or the current version is already the latest.
        $release = $this->latestRelease->current();

        return [
            ...$this->environment->toArray(),
            'storage_used_bytes' => (int) File::query()->sum('size'),
            'storage_free_bytes' => $freeBytes === false ? -1 : (int) $freeBytes,
            'update_available' => $release !== null,
            'latest_version' => $release['version'] ?? null,
            'release_url' => $release['url'] ?? null,
            // Null unless this is a container whose uploads still go to the
            // local disk — see StorageDurability.
            'storage_durability' => $this->storageDurability->inspect(),
            // Decides which upgrade instructions the card prints — see
            // Installation. Always present, unlike storage_durability, which
            // is null whenever the durability question does not apply.
            'install_kind' => $this->installation->kind()->value,
            // How downloads leave the server, and whether that was
            // detected or stated. Reported even when it is the fast path:
            // "my downloads are handed to the web server" is worth being
            // able to confirm at a glance, not only worth warning about
            // when it is false — the same reasoning as storage_durability.
            'file_delivery' => $this->fileDelivery->describe(),
        ];
    }

    private function clientDashboard(User $client): Response
    {
        // File::scopeVisibleToClient is the single source of truth for
        // client file access, and this page has to agree with the portal it
        // introduces. Restating the assignment half here made it disagree
        // in both directions: it counted expired files, which the scope
        // ends by excluding and /my-files therefore never shows, and it
        // missed everything that reaches a client another way — a file in a
        // folder shared with them, their own portal upload, and a revision,
        // which owns no assignment row and inherits its original's
        // recipients.
        $visibleFiles = File::query()->visibleToClient($client);

        return Inertia::render('portal/dashboard', [
            'files_count' => (clone $visibleFiles)->count(),
            'groups_count' => $client->memberOfGroups()->where('public', true)->count(),
            'storage' => [
                'used_bytes' => $this->storageUsage->usedBytes($client),
                'quota_bytes' => $this->storageUsage->quotaBytes($client) ?: null,
            ],
            'latest_files' => $visibleFiles->orderByDesc('created_at')->limit(5)->get()
                ->map(fn (File $file): array => [
                    'id' => $file->id,
                    'name' => $file->name,
                    'size' => $file->size,
                    'created_at' => $file->created_at?->toIso8601String(),
                ])->all(),
        ]);
    }
}
