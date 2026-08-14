<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Groups\Http\Controllers\Concerns\InteractsWithPublicListing;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Theming\PublicThemeRegistry;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\Paginator;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The guest-facing side of a public folder: same no-Gate authorization
 * model as PublicGroupsController — a folder's own `public` flag is the
 * entire authorization. One flattened page per public folder, showing
 * every file in its live subtree regardless of the file's own `public`
 * flag (that's the whole point of a public folder — see
 * File::scopePubliclyVisibleForFolder / File::isEffectivelyPublic), with
 * no further sub-navigation — mirrors how a public group's page already
 * flattens its whole assigned-folder subtree onto one page.
 *
 * A public folder's files still download/preview through the existing
 * public.file/thumbnail/download routes (PublicGroupsController), which
 * already check File::isEffectivelyPublic() — this controller only
 * renders the folder's own listing page.
 */
class PublicFoldersController extends Controller
{
    use InteractsWithPublicListing;

    private const PER_PAGE = 25;

    public function __construct(
        private readonly Settings $settings,
        private readonly PublicThemeRegistry $themes,
        private readonly CapabilityRegistry $capabilities,
        private readonly DownloadAllowance $allowance,
    ) {}

    public function show(string $publicSlug, string $folderSlug): InertiaResponse|RedirectResponse
    {
        $this->guardSlug($publicSlug);

        $folder = Folder::query()->where('slug', $folderSlug)->where('public', true)->firstOrFail();

        $files = $this->allowance->withCounts(File::query()->publiclyVisibleForFolder($folder), null)
            ->with('categories')->orderBy('name')
            ->paginate(self::PER_PAGE)->withQueryString();

        $page = Paginator::resolveCurrentPage();
        if (Pagination::isPastLastPage($files, $page)) {
            $redirectParams = [$publicSlug, $folderSlug];
            $redirectPage = Pagination::redirectPage($files);
            if ($redirectPage !== null) {
                $redirectParams['page'] = $redirectPage;
            }

            return redirect()->route('public.folder', $redirectParams);
        }

        return Inertia::render("public/themes/{$this->themeKey()}/folder", [
            'folder' => [
                'name' => $folder->name,
            ],
            'files' => $files->through(fn (File $file): array => $this->fileProps($file, $publicSlug))->items(),
            'pagination' => Pagination::meta($files),
        ]);
    }
}
