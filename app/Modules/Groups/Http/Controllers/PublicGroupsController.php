<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Comments\CommentingRules;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Delivery\InlineFileResponse;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Preview\PreviewKind;
use App\Modules\Files\Thumbnails\ImageAudience;
use App\Modules\Files\Thumbnails\ImageRendition;
use App\Modules\Files\Thumbnails\ThumbnailGenerator;
use App\Modules\Files\Versions\FileVersionLinks;
use App\Modules\Groups\Http\Controllers\Concerns\InteractsWithPublicListing;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Theming\PublicThemeRegistry;
use App\Support\ConcatenatedPagination;
use App\Support\ContentDisposition;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The guest-facing side of a public group: no Gate/policy involved (same
 * reasoning as PublicShareController) — a group's own `public` flag and
 * a file's own effective public status (File::isEffectivelyPublic() —
 * its own flag, or inherited from a public folder; see
 * PublicFoldersController for a public folder's own page) are the entire
 * authorization, matched against the admin-configurable base URL segment
 * (Setting::PublicListingSlug).
 *
 * Setting::PublicListingEnabled only toggles the browsable *directory*
 * (index() — the "list every public group/file" front page). A specific
 * public group's page and its downloads are independent of that switch:
 * once a group (and a file) is flagged public, its direct link works
 * whether or not the directory is enabled — same as a share link isn't
 * gated by any global toggle. Disabling the directory just stops it from
 * being discoverable/browsable at large.
 *
 * index()/show()/showFile()'s props (via fileProps()) are consumed by
 * every public/themes/{key}/{index,group,file}.tsx — see
 * docs/theming-files-checklist.md before changing them or the behavior
 * every theme is expected to preserve.
 */
class PublicGroupsController extends Controller
{
    use InteractsWithPublicListing;

    /**
     * Groups, folders, and files share one page window — three flat,
     * independent, unbounded lists otherwise, the guest-facing (and thus
     * most exposed) instance of the same problem FoldersController::index()
     * and MyFilesController::index() already had. See either one's
     * docblock for the full reasoning.
     */
    private const PER_PAGE = 25;

    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly DownloadAllowance $allowance,
        private readonly ThumbnailGenerator $thumbnails,
        private readonly PublicThemeRegistry $themes,
        private readonly CapabilityRegistry $capabilities,
        private readonly CommentingRules $commenting,
        private readonly InlineFileResponse $inline,
    ) {}

    public function index(Request $request, string $publicSlug): InertiaResponse|RedirectResponse
    {
        $this->guardSlug($publicSlug);
        abort_unless($this->settings->get(Setting::PublicListingEnabled) === true, 404);

        $groups = Group::query()->where('public', true)->orderBy('name');
        // Top of each public subtree only — a public folder nested inside
        // another public folder already appears on its ancestor's own
        // page (PublicFoldersController::show), same "don't list twice"
        // reasoning as standalonePublic() below. Expressed as a query
        // (whereDoesntHave) rather than an in-memory reject() so it stays
        // pageable — the original fetched every public folder unbounded
        // to filter client-side.
        $folders = Folder::query()->where('public', true)
            ->whereDoesntHave('parent', fn (Builder $q) => $q->where('public', true))
            ->orderBy('name');
        $files = $this->allowance->withCounts(File::query()->standalonePublic(), null)->with('categories')->orderBy('name');

        $page = Paginator::resolveCurrentPage();

        // ConcatenatedPagination is model-agnostic — Larastan's Builder
        // generic is invariant, so a heterogeneous array of builders
        // needs an explicit widen/narrow at the call site (sound at
        // runtime since each key's builder only ever queries its own
        // model). Same pattern as FoldersController::index().
        /** @var array<string, Builder<Model>> $sequences */
        $sequences = ['groups' => $groups, 'folders' => $folders, 'files' => $files];

        $sliced = ConcatenatedPagination::slice(
            $sequences,
            $page,
            self::PER_PAGE,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        if (Pagination::isPastLastPage($sliced['paginator'], $page)) {
            $redirectParams = [$publicSlug];
            $redirectPage = Pagination::redirectPage($sliced['paginator']);
            if ($redirectPage !== null) {
                $redirectParams['page'] = $redirectPage;
            }

            return redirect()->route('public.index', $redirectParams);
        }

        /** @var Collection<int, Group> $groupRows */
        $groupRows = $sliced['items']['groups'];
        /** @var Collection<int, Folder> $folderRows */
        $folderRows = $sliced['items']['folders'];
        /** @var Collection<int, File> $fileRows */
        $fileRows = $sliced['items']['files'];

        return Inertia::render("public/themes/{$this->themeKey()}/index", [
            'groups' => $groupRows->map(fn (Group $group): array => [
                'name' => $group->name,
                'description' => $group->description,
                'url' => route('public.show', [$publicSlug, $group->slug]),
            ])->values()->all(),
            'folders' => $folderRows->map(fn (Folder $folder): array => [
                'name' => $folder->name,
                'url' => route('public.folder', [$publicSlug, $folder->slug]),
            ])->values()->all(),
            'files' => $fileRows->map(fn (File $file): array => $this->fileProps($file, $publicSlug))->values()->all(),
            'pagination' => Pagination::meta($sliced['paginator']),
        ]);
    }

    public function show(string $publicSlug, string $groupSlug): InertiaResponse|RedirectResponse
    {
        $this->guardSlug($publicSlug);

        $group = Group::query()->where('slug', $groupSlug)->where('public', true)->firstOrFail();

        $files = $this->allowance->withCounts(File::query()->publiclyVisibleForGroup($group), null)->with('categories')->orderBy('name')
            ->paginate(self::PER_PAGE)->withQueryString();

        $page = Paginator::resolveCurrentPage();
        if (Pagination::isPastLastPage($files, $page)) {
            $redirectParams = [$publicSlug, $groupSlug];
            $redirectPage = Pagination::redirectPage($files);
            if ($redirectPage !== null) {
                $redirectParams['page'] = $redirectPage;
            }

            return redirect()->route('public.show', $redirectParams);
        }

        return Inertia::render("public/themes/{$this->themeKey()}/group", [
            'group' => [
                'name' => $group->name,
                'description' => $group->description,
            ],
            'files' => $files->through(fn (File $file): array => $this->fileProps($file, $publicSlug))->items(),
            'pagination' => Pagination::meta($files),
        ]);
    }

    public function showFile(string $publicSlug, File $file): InertiaResponse
    {
        $this->guardSlug($publicSlug);

        abort_unless($file->isEffectivelyPublic() && ! $file->isExpired(), 404);

        $file->loadMissing('categories');

        return Inertia::render("public/themes/{$this->themeKey()}/file", [
            'file' => [
                'name' => $file->name,
                'description' => $file->description,
                'original_name' => $file->original_name,
                'size' => $file->size,
                'mime_type' => $file->mime_type,
                'categories' => $file->categories
                    ->map(fn (Category $category): array => [
                        'id' => $category->id, 'name' => $category->name, 'color' => $category->color,
                    ])->values()->all(),
                // Only ever names a counterpart that is itself public and
                // unexpired — the same guard this method 404s on above.
                'version' => app(FileVersionLinks::class)->for(
                    $file,
                    null,
                    fn (File $other): ?string => $other->slug === '' ? null : route('public.file', [$publicSlug, $other->slug]),
                ),
            ],
            'thumbnail_url' => ThumbnailGenerator::supports($file->mime_type)
                ? route('public.thumbnail', [$publicSlug, $file->slug])
                : null,
            // Null whenever preview is unavailable, for any of the three
            // reasons — switched off, wrong type, or the download limit
            // spent — so a theme has one thing to check and the setting
            // itself never ships to a visitor's browser. preview() below
            // re-checks all three: this decides what to offer, not what
            // is allowed.
            'preview_url' => $this->previewUrlFor($file, $publicSlug),
            'download_url' => route('public.download', [$publicSlug, $file->slug]),
            // Same decided shape the listings send, so a theme's single
            // file page disables its button for the same reason a row
            // does — see DownloadAllowance::summaryFor.
            'download_limit' => $this->allowance->summaryFor($file, null),
            // The theme mounts its own comment shell against this
            // endpoint; the payload it returns is the same shape the
            // authenticated one uses, so the shared thread component does
            // not know which surface it is on.
            'comments_enabled' => $this->commenting->enabledFor($file),
            'comments_endpoint' => route('public.comments.index', [$publicSlug, $file->slug], false),
        ]);
    }

    public function thumbnail(string $publicSlug, File $file): Response
    {
        $this->guardSlug($publicSlug);

        abort_unless($file->isEffectivelyPublic() && ! $file->isExpired(), 404);
        abort_unless(ThumbnailGenerator::supports($file->mime_type), 404);

        // Always the external variant — nobody reaching a public listing is
        // signed in as staff, and the path has to match what
        // FileThumbnailController would cache for the same viewer or the
        // two surfaces would each rebuild the other's file.
        $thumbnailPath = ThumbnailGenerator::pathFor($file->id, $file->mime_type, ImageAudience::External, ImageRendition::Thumbnail);

        abort_if($thumbnailPath === null, 404);

        $disk = Storage::disk('files');

        if (! $disk->exists($thumbnailPath)) {
            $disk->makeDirectory(dirname($thumbnailPath));
            $this->thumbnails->generate($disk->path($file->path), $disk->path($thumbnailPath), $file->mime_type, ImageAudience::External, ImageRendition::Thumbnail);
        }

        return response('', 200, [
            'X-Accel-Redirect' => '/protected-files/'.$thumbnailPath,
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => ContentDisposition::inline($file->original_name),
        ]);
    }

    /**
     * The anonymous twin of FileThumbnailController::preview: a public
     * file shown rather than handed over.
     *
     * Nothing is rendered or cached here — an anonymous viewer only ever
     * previews the stored bytes. The watermark hook that decorates a
     * client's image preview has no equivalent on this route, for the
     * same reason thumbnail() hardcodes ImageAudience::External: there is
     * no viewer to tell apart.
     */
    public function preview(string $publicSlug, File $file): Response|RedirectResponse
    {
        $this->guardSlug($publicSlug);

        abort_unless($file->isEffectivelyPublic() && ! $file->isExpired(), 404);
        abort_unless($this->settings->get(Setting::PublicListingPreviewEnabled) === true, 404);
        abort_if(PreviewKind::forMime($file->mime_type) === null, 404);

        // 403 rather than 404 for the same reason download() does it, and
        // it is the same allowance being read: preview serves the whole
        // file, so a spent cap has to close this door too or it closes
        // nothing.
        abort_unless($this->allowance->allows($file, null), 403);

        $this->activity->log(Action::PublicFilePreviewed, subject: $file);

        return $this->inline->make($file);
    }

    /**
     * What showFile() offers, which is not the same question as what
     * preview() permits — this one also declines to advertise a preview
     * whose download limit is already spent, so a visitor is not given a
     * button that can only answer 403.
     */
    private function previewUrlFor(File $file, string $publicSlug): ?string
    {
        if ($this->settings->get(Setting::PublicListingPreviewEnabled) !== true) {
            return null;
        }

        if (PreviewKind::forMime($file->mime_type) === null) {
            return null;
        }

        if (! $this->allowance->allows($file, null)) {
            return null;
        }

        return route('public.preview', [$publicSlug, $file->slug]);
    }

    public function download(string $publicSlug, File $file): Response
    {
        $this->guardSlug($publicSlug);

        abort_unless($file->isEffectivelyPublic() && ! $file->isExpired(), 404);

        // 403 rather than 404, unlike the checks above it: the file is
        // genuinely here and genuinely public, it has simply been taken
        // as many times as it was meant to be. Hiding that would send
        // someone hunting for a link that never broke.
        abort_unless($this->allowance->allows($file, null), 403);

        $this->activity->log(Action::PublicFileDownloaded, subject: $file);

        return response('', 200, [
            'X-Accel-Redirect' => '/protected-files/'.$file->path,
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => ContentDisposition::attachment($file->original_name),
            'Content-Length' => (string) $file->size,
        ]);
    }
}
