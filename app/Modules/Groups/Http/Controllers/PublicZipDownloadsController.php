<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Delivery\FileDelivery;
use App\Modules\Files\Http\Controllers\ZipDownloadsController;
use App\Modules\Files\Jobs\BuildZipDownloadJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ZipDownload;
use App\Modules\Files\Scanning\FileAvailability;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Files\Uploads\StoreUploadedFile;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\PublicListing\PublicFileScope;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\ContentDisposition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Download selected as zip" for visitors on a public page (#1840).
 *
 * The signed-in version (ZipDownloadsController) checks every file against
 * the account asking. A visitor has no account, so two things stand in for
 * it, and both matter:
 *
 *  - **The page decides what is in the zip.** The request names the page
 *    (index, group or folder, by slug) and the files picked on it, by slug.
 *    Only files that page offers right now are kept (PublicFileScope).
 *    The browser never gets to say "and this file too": a slug from
 *    anywhere else simply matches nothing.
 *  - **The session owns the zip.** Its id is remembered in the visitor's
 *    session, and status and download answer 404 to anyone else. Ids are
 *    sequential, so without this anyone could fetch any visitor's archive.
 *
 * Off unless an administrator turns it on (Setting::PublicZipDownloadsEnabled),
 * and gone with the zip capability. Anybody at all can ask for a build, and
 * a build holds the zips worker and disk space, so on top of the normal
 * size and count caps there is one pending build per session and a small
 * ceiling on visitor builds pending at once across the whole installation.
 */
class PublicZipDownloadsController extends Controller
{
    /** Session key holding the ids of this visitor's zips. */
    private const SESSION_KEY = 'public_zip_downloads';

    /**
     * Visitor builds waiting or running at once, installation-wide. Low on
     * purpose: these share one queue worker with signed-in users' zips,
     * and a crowd of anonymous requests must not become everyone's wait.
     */
    private const MAX_PENDING_VISITOR_BUILDS = 3;

    /**
     * How much disk visitors' archives may hold between them, as a
     * multiple of the per-archive limit. An archive stays on disk for a day
     * (PurgeZipDownloadsCommand), and the limits above only slow one
     * visitor down: many addresses asking for the largest allowed zip
     * could otherwise fill the disk before the purge runs.
     */
    private const VISITOR_DISK_BUDGET_MULTIPLE = 10;

    /** The same ceiling where the per-archive limit is switched off. */
    private const VISITOR_DISK_BUDGET_UNLIMITED_FALLBACK = 10 * 1024 * 1024 * 1024;

    /**
     * How much of that budget one address may hold in a day. A visitor
     * who sends no session cookie gets a fresh session every request, so
     * the per-session rule alone let two addresses spend the whole budget
     * and switch visitor zips off for everyone until the next day.
     */
    private const VISITOR_DISK_SHARE_PER_ADDRESS = 2;

    public function __construct(
        private readonly Settings $settings,
        private readonly PublicFileScope $scope,
        private readonly ActivityLogger $activity,
        private readonly DownloadAllowance $allowance,
        private readonly FileAvailability $availability,
        private readonly FileDelivery $delivery,
    ) {}

    public function store(Request $request, string $publicSlug): JsonResponse
    {
        $this->guard($publicSlug);

        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:'.implode(',', [ZipDownload::SCOPE_INDEX, ZipDownload::SCOPE_GROUP, ZipDownload::SCOPE_FOLDER])],
            'slug' => ['nullable', 'string', 'max:255'],
            // Bounded before it becomes a whereIn, as in ZipDownloadsController.
            'files' => ['required', 'array', 'min:1', 'max:'.ZipDownloadsController::MAX_FILES],
            'files.*' => ['string', 'max:255'],
        ]);

        $page = $this->scope->fromPage($validated['scope'], $validated['slug'] ?? null);
        abort_if($page === null, 404);

        // One at a time per visitor, for the reason ZipDownloadsController
        // gives for one per user. An hour old counts as abandoned.
        $mine = $this->sessionZipIds($request);
        $addressKey = $this->addressKey($request);
        $addressPending = Cache::get("{$addressKey}:pending");
        abort_if(
            ($mine !== [] && $this->pendingVisitorBuilds()->whereIn('id', $mine)->exists())
                || (is_int($addressPending) && $this->pendingVisitorBuilds()->whereKey($addressPending)->exists()),
            429,
            __('A zip download is already being prepared. Wait for that one to finish before starting another.'),
        );

        abort_if(
            $this->pendingVisitorBuilds()->count() >= self::MAX_PENDING_VISITOR_BUILDS,
            429,
            __('Too many zip downloads are being prepared right now. Try again in a few minutes.'),
        );

        $offered = $this->scope->files($page['type'], $page['id']);
        abort_if($offered === null, 404);

        // Only what the page offers. Anything else in the request is
        // dropped without a word, so a slug from a private file looks
        // exactly like one that does not exist.
        $files = $offered->whereIn('files.slug', array_values(array_unique($validated['files'])))->get();

        abort_if($files->isEmpty(), 422, __('None of the selected items could be found.'));

        $files = $files->filter(fn (File $file): bool => $this->availability->isAvailable($file)
            && $this->allowance->allows($file, null));

        abort_if($files->isEmpty(), 422, __('Those files have reached their download limit.'));

        $totalSize = (int) $files->sum('size');
        $maxBytes = (int) $this->settings->get(Setting::MaxZipDownloadSizeMb) * 1024 * 1024;

        abort_if(
            $maxBytes > 0 && $totalSize > $maxBytes,
            422,
            __('That selection is :size. Zip downloads are limited to :limit — select fewer files and try again.', [
                'size' => Number::fileSize($totalSize, precision: 1),
                'limit' => Number::fileSize($maxBytes),
            ]),
        );

        $budget = $maxBytes > 0 ? $maxBytes * self::VISITOR_DISK_BUDGET_MULTIPLE : self::VISITOR_DISK_BUDGET_UNLIMITED_FALLBACK;
        $held = (int) ZipDownload::query()
            ->whereNull('requested_by')
            ->whereNotNull('public_scope_type')
            ->whereIn('status', [ZipDownload::STATUS_PENDING, ZipDownload::STATUS_READY])
            ->where('created_at', '>', now()->subDay())
            ->sum('total_size');

        $heldByAddress = (int) Cache::get("{$addressKey}:bytes", 0);

        abort_if(
            $held + $totalSize > $budget
                || $heldByAddress + $totalSize > intdiv($budget, self::VISITOR_DISK_BUDGET_MULTIPLE) * self::VISITOR_DISK_SHARE_PER_ADDRESS,
            429,
            __('Too many zip downloads are being prepared right now. Try again in a few minutes.'),
        );

        $zipDownload = ZipDownload::query()->create([
            'requested_by' => null,
            'public_scope_type' => $page['type'],
            'public_scope_id' => $page['id'],
            'status' => ZipDownload::STATUS_PENDING,
            'file_ids' => $files->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'folder_ids' => [],
            'file_count' => $files->count(),
            // An estimate until the build writes the real size, so the disk
            // budget above counts archives still being built.
            'total_size' => $totalSize,
        ]);

        // The last few only: a session is not a place to keep a history.
        $request->session()->put(self::SESSION_KEY, array_slice([...$mine, $zipDownload->id], -20));
        // The pending build for an hour, the age at which one counts as
        // abandoned; the bytes for a day, as long as the archive lives
        // (PurgeZipDownloadsCommand).
        Cache::put("{$addressKey}:pending", $zipDownload->id, now()->addHour());
        Cache::put("{$addressKey}:bytes", $heldByAddress + $totalSize, now()->addDay());

        BuildZipDownloadJob::dispatch($zipDownload->id);

        return response()->json([
            'id' => $zipDownload->id,
            'status_url' => route('public.zip-downloads.show', [$publicSlug, $zipDownload->id]),
            'download_url' => route('public.zip-downloads.download', [$publicSlug, $zipDownload->id]),
        ]);
    }

    public function show(Request $request, string $publicSlug, ZipDownload $zipDownload): JsonResponse
    {
        $this->guard($publicSlug);
        $this->authorizeVisitor($request, $zipDownload);

        return response()->json([
            'status' => $zipDownload->status,
            'file_count' => $zipDownload->file_count,
            'error' => $zipDownload->status === ZipDownload::STATUS_FAILED ? $zipDownload->error : null,
            // Names only: public pages hand out no ids.
            'skipped_files' => array_map(fn (array $file): array => ['name' => $file['name']], $zipDownload->skipped_files ?? []),
        ]);
    }

    public function download(Request $request, string $publicSlug, ZipDownload $zipDownload): Response
    {
        $this->guard($publicSlug);
        $this->authorizeVisitor($request, $zipDownload);

        $path = $zipDownload->path;
        abort_unless($zipDownload->status === ZipDownload::STATUS_READY && $path !== null, 404);

        $contained = $zipDownload->contained_file_ids;
        abort_if($contained === null, 423, __('This archive was prepared before an update. Download the selection again.'));

        // Asked on every fetch, as ZipDownloadsController asks the policy:
        // the archive waits up to a day, and in that time the folder can be
        // made private or a file taken out of it. Every file in the archive
        // must still be offered by its page, or none of it is handed over.
        $offered = $this->scope->files((string) $zipDownload->public_scope_type, $zipDownload->public_scope_id);
        $contained = array_values(array_unique($contained));

        abort_if(
            $offered === null || $offered->whereIn('files.id', $contained)->count() !== count($contained),
            403,
            __('A file in this archive is no longer public.'),
        );

        abort_if(
            File::query()->whereIn('id', $contained)->whereNotIn('scan_status', ScanStatus::availableValues())->exists(),
            423,
            __('A file in this archive is no longer available. Download the selection again.'),
        );

        if ($zipDownload->delivered_at === null) {
            $this->deliverOnce($zipDownload);
        } else {
            // Fetching the same archive again is the same delivery, which
            // a broken connection needs. Not when it holds a file with a
            // download limit: that delivery already spent the allowance,
            // and anyone holding this visitor's session cookie could
            // otherwise take copy after copy for as long as the archive
            // lives. A signed-in user's zip is tied to an account; this one
            // only to a cookie.
            abort_if(
                File::query()->whereIn('id', $contained)->whereNotNull('download_limit')->where('download_limit', '>', 0)->exists(),
                410,
                __('This zip has already been downloaded. Select the files again to download them.'),
            );
        }

        return $this->delivery->serve(
            $path,
            'application/zip',
            ContentDisposition::attachment($this->filenameFor($zipDownload)),
            Storage::disk('files')->size($path),
        );
    }

    /**
     * Spend the download limits and log every file, once — the same rule,
     * and the same reasons, as ZipDownloadsController::deliverOnce(). Each
     * file is logged as a public download, as it would be on its own.
     */
    private function deliverOnce(ZipDownload $zipDownload): void
    {
        $contained = File::query()->whereIn('id', $zipDownload->contained_file_ids ?? [])->get();

        abort_if(
            $contained->contains(fn (File $file): bool => ! $this->allowance->allows($file, null)),
            403,
            __('Those files have reached their download limit.'),
        );

        $lostTheRace = false;

        $taken = $this->allowance->takeAll($contained, null, function () use ($zipDownload, $contained, &$lostTheRace): void {
            $claimed = ZipDownload::query()
                ->whereKey($zipDownload->id)
                ->whereNull('delivered_at')
                ->update(['delivered_at' => now()]);

            // Another fetch of this archive got here first: this one is a
            // re-fetch that started before that one finished.
            if ($claimed === 0) {
                $lostTheRace = true;

                return;
            }

            foreach ($contained as $file) {
                $this->activity->log(Action::PublicFileDownloaded, subject: $file);
            }
        });

        abort_unless($taken, 403, __('Those files have reached their download limit.'));

        // The same rule as a later re-fetch in download(): it is free,
        // except for an archive holding a limited file. Without this, many
        // fetches sent at once all loaded the archive as undelivered, and
        // every one was handed a copy for a single counted download.
        abort_if(
            $lostTheRace && $contained->contains(fn (File $file): bool => $file->hasDownloadLimit()),
            410,
            __('This zip has already been downloaded. Select the files again to download them.'),
        );
    }

    /**
     * Named after the page it came from: "Drawings.zip" for a folder or
     * group, "download.zip" for the index.
     */
    private function filenameFor(ZipDownload $zipDownload): string
    {
        $name = match ($zipDownload->public_scope_type) {
            ZipDownload::SCOPE_FOLDER => Folder::query()->whereKey($zipDownload->public_scope_id)->value('name'),
            ZipDownload::SCOPE_GROUP => Group::query()->whereKey($zipDownload->public_scope_id)->value('name'),
            default => null,
        };

        return is_string($name) && $name !== '' ? StoreUploadedFile::sanitizeFilename($name).'.zip' : 'download.zip';
    }

    /**
     * The right base address, and the feature switched on. 404 rather than
     * 403 when it is off, so an installation that does not offer this does
     * not advertise that it could.
     */
    private function guard(string $publicSlug): void
    {
        abort_unless($publicSlug === $this->settings->get(Setting::PublicListingSlug), 404);
        abort_unless($this->settings->get(Setting::PublicZipDownloadsEnabled) === true, 404);
    }

    /**
     * Only the session that asked for a zip may see it. A signed-in user's
     * zip is never reachable here at all, whatever its id.
     */
    private function authorizeVisitor(Request $request, ZipDownload $zipDownload): void
    {
        abort_unless(
            $zipDownload->isPublic() && in_array($zipDownload->id, $this->sessionZipIds($request), true),
            404,
        );
    }

    /**
     * A cache key for the visitor's address. Keyed, not the address itself,
     * so the cache never holds a visitor's IP in the clear.
     */
    private function addressKey(Request $request): string
    {
        return 'public-zip:'.hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
    }

    /**
     * @return list<int>
     */
    private function sessionZipIds(Request $request): array
    {
        $ids = $request->session()->get(self::SESSION_KEY, []);

        return is_array($ids) ? array_values(array_map(intval(...), $ids)) : [];
    }

    /**
     * @return Builder<ZipDownload>
     */
    private function pendingVisitorBuilds(): Builder
    {
        return ZipDownload::query()
            ->whereNull('requested_by')
            ->whereNotNull('public_scope_type')
            ->where('status', ZipDownload::STATUS_PENDING)
            ->where('created_at', '>', now()->subHour());
    }
}
