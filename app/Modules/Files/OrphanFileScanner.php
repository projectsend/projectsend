<?php

declare(strict_types=1);

namespace App\Modules\Files;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Thumbnails\ImageRendition;
use App\Modules\Files\Uploads\UploadExtensionPolicy;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\ExternalStorageSettings;
use Illuminate\Support\Facades\Storage;

/**
 * Finds files sitting on disk with no corresponding File row — v1
 * parity for the import_orphans permission. Scans the local 'files'
 * disk, and 'files_external' too once external storage is active
 * (ExternalStorageConfigApplier::isActive()) — an upload can land on
 * either, so an orphan can too. See docs/unused-permissions-audit.md.
 */
class OrphanFileScanner
{
    public function __construct(
        private readonly UploadExtensionPolicy $extensionPolicy,
        private readonly ExternalStorageConfigApplier $externalStorage,
    ) {}

    /**
     * Every disk this install could possibly have orphans on right now,
     * keyed by disk name, with a human-readable label and location for
     * display — so the page can say exactly where it looked.
     *
     * @return array<string, array{label: string, location: string}>
     */
    public function scannedDisks(): array
    {
        $disks = [
            'files' => [
                'label' => 'Local storage',
                'location' => rtrim(Storage::disk('files')->path(''), '/'),
            ],
        ];

        if ($this->externalStorage->isActive()) {
            $settings = ExternalStorageSettings::current();
            $root = $settings->root !== null && $settings->root !== '' ? '/'.trim($settings->root, '/') : '';

            $disks['files_external'] = [
                'label' => 'External storage',
                'location' => 's3://'.$settings->bucket.$root,
            ];
        }

        return $disks;
    }

    /**
     * Sorted by disk then path so pagination over the result is stable
     * across requests — an install can have thousands of these, so the
     * caller paginates rather than rendering the whole list at once.
     *
     * $viewer is optional — null for callers with no session user (e.g. a
     * scheduled command), in which case 'allowed' is unused by the caller
     * and just reported as true rather than requiring a real user to
     * evaluate UploadExtensionPolicy against.
     *
     * @return list<array{disk: string, path: string, size: int, last_modified: int, allowed: bool}>
     */
    public function scan(?User $viewer = null, ?string $search = null): array
    {
        $needle = $search !== null && $search !== '' ? mb_strtolower($search) : null;
        $orphans = [];

        foreach (array_keys($this->scannedDisks()) as $diskName) {
            $disk = Storage::disk($diskName);
            $knownPaths = array_flip($this->knownPaths($diskName));

            foreach ($disk->allFiles() as $path) {
                if ($this->isExcluded($path) || isset($knownPaths[$path])) {
                    continue;
                }

                if ($needle !== null && ! str_contains(mb_strtolower($path), $needle)) {
                    continue;
                }

                $orphans[] = [
                    'disk' => $diskName,
                    'path' => $path,
                    'size' => $disk->size($path),
                    'last_modified' => $disk->lastModified($path),
                    'allowed' => $viewer !== null ? $this->extensionPolicy->isAllowed($viewer, basename($path)) : true,
                ];
            }
        }

        usort($orphans, fn (array $a, array $b): int => [$a['disk'], $a['path']] <=> [$b['disk'], $b['path']]);

        return $orphans;
    }

    /**
     * Re-validated at import/delete time — never trust a client-supplied
     * disk/path just because it was in an earlier scan response. $disk
     * must be one of scannedDisks()'s current keys — a client could
     * otherwise name any configured Laravel disk (e.g. 'public').
     */
    public function isOrphan(string $disk, string $path): bool
    {
        if (! array_key_exists($disk, $this->scannedDisks())) {
            return false;
        }

        if ($this->isExcluded($path) || ! Storage::disk($disk)->exists($path)) {
            return false;
        }

        return ! in_array($path, $this->knownPaths($disk), true);
    }

    public function isAllowedFor(User $viewer, string $path): bool
    {
        return $this->extensionPolicy->isAllowed($viewer, basename($path));
    }

    /**
     * A 0-byte file is virtually certain to be a failed or interrupted
     * write rather than real content — importing it would only give a
     * client an empty download, so unlike a merely restricted-extension
     * orphan (still worth adopting once permitted), an empty one is never
     * importable. Still listed and still deletable, same as a restricted
     * one.
     */
    public function isImportable(User $viewer, string $disk, string $path): bool
    {
        return $this->isOrphan($disk, $path)
            && $this->isAllowedFor($viewer, $path)
            && Storage::disk($disk)->size($path) > 0;
    }

    /**
     * Soft-deleted rows still count as "known" — a path stays claimed as
     * long as any row references it, even one only awaiting the erasure
     * grace period, so a scan never offers to double-adopt it.
     *
     * @return list<string>
     */
    private function knownPaths(string $disk): array
    {
        return array_values(array_filter(
            File::withTrashed()->where('disk', $disk)->pluck('path')->all(),
            fn (mixed $path): bool => is_string($path),
        ));
    }

    /**
     * Path prefixes that are derived artifacts, never orphaned uploads, so
     * never candidates regardless of what's in the files table: every image
     * rendition's cache directory (taken from ImageRendition so a new
     * rendition can't be forgotten here — previews used to be) plus the
     * download-bundle job's 'zips'. Thumbnails and previews are always local;
     * zips would be too if that job ever ran against 'files_external', so the
     * exclusion applies per-disk.
     *
     * @return list<string>
     */
    private function excludedPrefixes(): array
    {
        $prefixes = array_map(
            static fn (ImageRendition $rendition): string => $rendition->directory().'/',
            ImageRendition::cases(),
        );

        $prefixes[] = 'zips/';

        return $prefixes;
    }

    private function isExcluded(string $path): bool
    {
        foreach ($this->excludedPrefixes() as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
