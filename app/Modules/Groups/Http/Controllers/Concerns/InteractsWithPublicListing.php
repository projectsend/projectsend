<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers\Concerns;

use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Thumbnails\ThumbnailGenerator;
use App\Modules\Files\Versions\FileVersionLinks;
use App\Modules\Platform\Settings\Setting;

/**
 * Shared by every guest-facing public listing controller
 * (PublicGroupsController, PublicFoldersController): matching the
 * admin-configurable base URL segment, resolving the active public theme,
 * and shaping a File into the props every public theme's file-listing
 * pages expect. Using classes must have `private readonly Settings
 * $settings`, `private readonly PublicThemeRegistry $themes`, and
 * `private readonly CapabilityRegistry $capabilities` constructor
 * properties.
 */
trait InteractsWithPublicListing
{
    private function guardSlug(string $publicSlug): void
    {
        abort_unless($publicSlug === $this->settings->get(Setting::PublicListingSlug), 404);
    }

    private function themeKey(): string
    {
        $value = $this->settings->get(Setting::Theme);

        return $this->themes->resolve(is_string($value) ? $value : 'default', $this->capabilities);
    }

    /**
     * @return array{name: string, size: int, url: string, download_url: string, thumbnail_url: string|null, mime_type: string, categories: list<array{id: int, name: string, color: string}>}
     */
    private function fileProps(File $file, string $publicSlug): array
    {
        return [
            'name' => $file->name,
            'size' => $file->size,
            // Categories are not internal metadata: whoever can reach a
            // file can see the labels on it, which is what the notice on
            // /categories promises admins. Every caller of this method
            // must eager-load the relation — 25 rows to a page otherwise
            // means 25 extra queries.
            'categories' => array_values($file->categories
                ->map(fn (Category $category): array => [
                    'id' => $category->id, 'name' => $category->name, 'color' => $category->color,
                ])->all()),
            'url' => route('public.file', [$publicSlug, $file->slug]),
            'download_url' => route('public.download', [$publicSlug, $file->slug]),
            'thumbnail_url' => ThumbnailGenerator::supports($file->mime_type)
                ? route('public.thumbnail', [$publicSlug, $file->slug])
                : null,
            'mime_type' => $file->mime_type,
            // A visitor here has no account, so a per-user limit is
            // measured against the whole file — see DownloadAllowance.
            // Blocked means the download route will refuse; the file
            // still appears, so nobody hunts for a link that never broke.
            'download_limit' => app(DownloadAllowance::class)->summaryFor($file, null),
            // For a guest, "can see both files" means both are effectively
            // public and unexpired — the same predicate showFile() 404s on,
            // so the badge can never point at a page that would refuse to
            // load. Resolved by FileVersionLinks, not here.
            'version' => app(FileVersionLinks::class)->for(
                $file,
                null,
                fn (File $other): ?string => $other->slug === '' ? null : route('public.file', [$publicSlug, $other->slug]),
            ),
        ];
    }
}
