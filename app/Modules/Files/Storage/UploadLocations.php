<?php

declare(strict_types=1);

namespace App\Modules\Files\Storage;

use App\Models\User;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\StorageLocation;
use Illuminate\Support\Collection;

/**
 * Where a new upload goes.
 *
 * A client with storage locations of their own uploads to the first of
 * them that can take it, or to another of them they picked for this
 * upload. Everyone else, staff always included, uploads to the default
 * location. A location only counts while it accepts uploads and, for a
 * bucket, while it is configured and this edition permits buckets at all.
 */
class UploadLocations
{
    public function __construct(
        private readonly ExternalStorageConfigApplier $storage,
    ) {}

    /**
     * The locations a client may upload to, the preferred one first. Empty
     * for staff, and for a client whose uploads follow the default.
     *
     * @return Collection<int, StorageLocation>
     */
    public function usableFor(User $uploader): Collection
    {
        if (! $uploader->isClient() || ! $this->storage->permitted()) {
            return collect();
        }

        $writable = $this->storage->writableDisks();

        return $uploader->storageLocations()->get()
            ->filter(fn (StorageLocation $location): bool => $location->accepts_uploads && in_array($location->disk, $writable, true))
            ->values();
    }

    /**
     * The disk a new upload by this person is written to. A requested
     * location that is not one of theirs, or no longer usable, is ignored
     * rather than refused: the upload session validated it when it was
     * created, and anything that changed since is the administrator's doing.
     */
    public function diskFor(User $uploader, ?int $requestedLocationId = null): string
    {
        $usable = $this->usableFor($uploader);

        if ($usable->isEmpty()) {
            return $this->storage->defaultDisk();
        }

        $requested = $requestedLocationId === null ? null : $usable->firstWhere('id', $requestedLocationId);

        return ($requested ?? $usable->firstOrFail())->disk;
    }
}
