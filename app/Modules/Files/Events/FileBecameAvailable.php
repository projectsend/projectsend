<?php

declare(strict_types=1);

namespace App\Modules\Files\Events;

use App\Modules\Files\Models\File;

/**
 * A file that could not be handed to anyone now can be.
 *
 * Dispatched by FileAvailability::markAvailable(), from all three ways a
 * file gets there: a clean scan, a scan this installation gave up waiting
 * for, and an administrator releasing a quarantined file.
 *
 * It exists so that "tell the recipients" is written once rather than at
 * each of those three, and so the private package can hook the same
 * moment — the same reasoning FileWasStored is dispatched under.
 */
final class FileBecameAvailable
{
    public function __construct(
        public readonly File $file,
    ) {}
}
