<?php

declare(strict_types=1);

namespace App\Modules\Files\Storage;

use App\Models\User;

/**
 * Dispatched once per upload, before bytes are written, to decide which
 * Laravel disk they should land on. Defaults to the local 'files' disk —
 * a fresh or Cloud install has nothing listening, so behavior is
 * unchanged from before this event existed.
 *
 * A listener (see ExternalStorageConfigApplier) sets $disk to
 * 'files_external' when the community-only external storage settings are
 * active. Deliberately a plain event with a mutable property, not an
 * interface a listener must implement — core never references any
 * specific feature's class this way, so nothing here breaks if no
 * listener is registered at all (see docs/extension-points-architecture.md).
 */
class ResolvingUploadDisk
{
    public string $disk = 'files';

    public function __construct(
        public readonly User $uploader,
    ) {}
}
