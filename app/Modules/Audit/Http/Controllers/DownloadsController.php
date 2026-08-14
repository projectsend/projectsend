<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogScope;
use App\Modules\Audit\DownloadPresenter;
use App\Modules\Files\Models\File;
use App\Support\Pagination;
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
    ) {}

    public function index(Request $request): Response
    {
        $viewer = $request->user();
        assert($viewer !== null);

        // A download row names the file and says who fetched it from which
        // IP, so it needs the viewer's library scope applied — not just
        // `view_actions_log`. See ActivityLogScope for the full reasoning.
        $entries = $this->scope
            ->apply(ActivityLog::query(), $viewer)
            ->where('subject_type', (new File)->getMorphClass())
            ->whereIn('action', [Action::FileDownloaded, Action::ShareLinkDownloaded, Action::PublicFileDownloaded])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
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
        ]);
    }
}
