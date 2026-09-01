<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Delivery\StoredFileResponse;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public, unauthenticated side of a share link: no Gate/policy is
 * involved (FilePolicy::view() requires a real User, so it auto-denies
 * guests) — the token itself, checked for expiry and download limit, is
 * the entire authorization.
 */
class PublicShareController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly DownloadAllowance $allowance,
        private readonly StoredFileResponse $bytes,
    ) {}

    public function show(string $token): InertiaResponse
    {
        $shareLink = ShareLink::query()->where('token', $token)->first();
        $file = $shareLink?->shareable;

        if ($shareLink === null || ! $file instanceof File) {
            return Inertia::render('share/show', ['status' => 'not_found']);
        }

        // The file's own expiry counts too, not just the link's: expires_at
        // is how access is revoked everywhere else (clients, public listing),
        // so a link outliving it would be a way around that revocation.
        if ($shareLink->isExpired() || $file->isExpired()) {
            return Inertia::render('share/show', ['status' => 'expired']);
        }

        // Two separate caps reach the same page: the link's own
        // max_downloads, and the file's. A visitor here has no account,
        // so the file's limit is measured against the whole file — see
        // DownloadAllowance.
        if ($shareLink->hasReachedLimit() || ! $this->allowance->allows($file, null)) {
            return Inertia::render('share/show', ['status' => 'limit_reached']);
        }

        $file->loadMissing('categories');

        return Inertia::render('share/show', [
            'status' => 'active',
            'file' => [
                'original_name' => $file->original_name,
                'size' => $file->size,
                // A share link is access to the file, so it shows the same
                // labels every other surface does — see the notice on
                // /categories, which promises exactly that.
                'categories' => $file->categories
                    ->map(fn (Category $category): array => [
                        'id' => $category->id, 'name' => $category->name, 'color' => $category->color,
                    ])->values()->all(),
            ],
            'download_url' => route('share.download', $token),
        ]);
    }

    public function download(string $token): Response|RedirectResponse
    {
        $shareLink = ShareLink::query()->where('token', $token)->first();
        $file = $shareLink?->shareable;

        if ($shareLink === null || ! $file instanceof File || $shareLink->isExpired() || $file->isExpired()) {
            return redirect()->route('share.show', $token);
        }

        // Before the link's counter moves, not after: a download refused
        // by the file's own limit must not spend one of the link's.
        if (! $this->allowance->allows($file, null)) {
            return redirect()->route('share.show', $token);
        }

        // Atomic: only increments if still under the limit, closing the
        // race between two simultaneous requests both passing the check.
        $incremented = ShareLink::query()
            ->whereKey($shareLink->id)
            ->where(fn ($query) => $query->whereNull('max_downloads')->orWhereColumn('downloads_count', '<', 'max_downloads'))
            ->increment('downloads_count');

        if ($incremented === 0) {
            return redirect()->route('share.show', $token);
        }

        $this->activity->log(Action::ShareLinkDownloaded, subject: $file);

        return $this->bytes->attachment($file);
    }
}
