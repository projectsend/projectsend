<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogScope;
use App\Modules\Audit\DownloadPresenter;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Localization\LocalDay;
use App\Modules\Platform\Localization\TimezoneRegistry;
use App\Support\Pagination;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Installation-wide download history — every FileDownloaded /
 * ShareLinkDownloaded / PublicFileDownloaded entry across every file,
 * newest first. The per-file downloads tab/history (FileDetailsController)
 * covers a single file; this is the "all of them" view linked from the
 * sidebar.
 */
class DownloadsController extends Controller
{
    public function __construct(
        private readonly DownloadPresenter $presenter,
        private readonly ActivityLogScope $scope,
        private readonly TimezoneRegistry $timezones,
    ) {}

    public function index(Request $request): Response
    {
        $viewer = $request->user();
        assert($viewer !== null);

        $filters = $this->validatedFilters($request);

        $entries = $this->filteredQuery($filters, $viewer)
            ->paginate(25)
            ->withQueryString();

        $canOpenFiles = $viewer->can('upload') || $viewer->can('edit_files') || $viewer->can('edit_others_files');

        // Openable, not merely existing — the scope decides, so a row never
        // links to a file the viewer would be refused.
        $openableFileIds = $this->scope->openableFileIds($viewer, $entries->getCollection()->pluck('subject_id'));

        return Inertia::render('activity/downloads', [
            'entries' => $entries->getCollection()->map(function (ActivityLog $entry) use ($openableFileIds, $canOpenFiles): array {
                $openable = $entry->subject_id !== null && isset($openableFileIds[$entry->subject_id]);

                return [
                    ...$this->presenter->present($entry),
                    'file_name' => $entry->subject_name ?? __('(deleted file)'),
                    'file_url' => $openable && $canOpenFiles ? route('files.edit', $entry->subject_id, false) : null,
                ];
            })->all(),
            'pagination' => Pagination::meta($entries),
            'filters' => $filters,
        ]);
    }

    /**
     * @return array{file: ?string, user: ?string, from: ?string, to: ?string}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'file' => ['nullable', 'string', 'max:255'],
            'user' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            'file' => $validated['file'] ?? null,
            'user' => $validated['user'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    /**
     * @param  array{file: ?string, user: ?string, from: ?string, to: ?string}  $filters
     * @return Builder<ActivityLog>
     */
    private function filteredQuery(array $filters, User $viewer): Builder
    {
        $timezone = $this->timezones->resolve($viewer);

        // A download row names the file and says who fetched it from which
        // IP, so it needs the viewer's library scope applied — not just
        // `view_actions_log`. See ActivityLogScope for the full reasoning.
        return $this->scope
            ->apply(ActivityLog::query(), $viewer)
            ->where('subject_type', (new File)->getMorphClass())
            ->whereIn('action', [Action::FileDownloaded, Action::ShareLinkDownloaded, Action::PublicFileDownloaded])
            // Both names are matched on what the entry snapshotted, not on
            // a join: a file or an account deleted since is still findable
            // by the name it went out under, which is often exactly what
            // this page is being asked.
            ->when($filters['file'], fn (Builder $query, string $file) => $query->where('subject_name', 'like', "%{$file}%"))
            // Only rows with a real account can match a name. The two
            // anonymous flavours ("Public link", "Public listing") are
            // labels this page prints, not stored values, so a search for
            // them finds nothing rather than something arbitrary.
            ->when($filters['user'], fn (Builder $query, string $user) => $query->where('actor_name', 'like', "%{$user}%"))
            // The viewer's own calendar day, not the UTC one — see LocalDay.
            ->when(
                $filters['from'] !== null ? LocalDay::start($filters['from'], $timezone) : null,
                fn (Builder $query, Carbon $from) => $query->where('created_at', '>=', $from),
            )
            ->when(
                $filters['to'] !== null ? LocalDay::end($filters['to'], $timezone) : null,
                fn (Builder $query, Carbon $to) => $query->where('created_at', '<=', $to),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
