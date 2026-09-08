<?php

declare(strict_types=1);

namespace App\Modules\Files\Events;

use App\Models\User;
use App\Modules\Files\Models\File;

/**
 * A file's bytes are on a disk and its row exists.
 *
 * Dispatched from StoreUploadedFile, which every upload path goes through
 * — the chunked flow that staff and clients share, and the synchronous
 * POST beside it — so a listener sees every upload once and does not have
 * to know which route produced it.
 *
 * A notification rather than a filter: nothing here is mutable and no
 * listener can change what was stored. Anything that needs to influence
 * the upload has to do so before the bytes land, which is what
 * ResolvingUploadDisk is for.
 *
 * Fired after the row is created and before the caller has linked a
 * version or answered the request, so a listener sees a complete File and
 * can safely read it back.
 */
class FileWasStored
{
    public function __construct(
        public readonly File $file,
        public readonly User $uploader,
    ) {}
}
