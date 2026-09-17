<?php

declare(strict_types=1);

namespace App\Modules\Files\Events;

use App\Models\User;

/**
 * Something a client should read before they upload.
 *
 * Asked each time the portal's upload page is rendered, and shown above
 * the uploader in every theme. The upload page is one page for all of
 * them, so this is the one place a rule about what happens to an upload
 * can be said where the upload happens — the announcement band is not,
 * since only one theme's shell draws it.
 *
 * **Core knows nothing about what it says.** The first caller is the
 * hosted edition's shared instance, telling a free customer how long
 * their files are kept, which is a rule of one offering and belongs in
 * that offering's code.
 *
 * Lines rather than one message: two unrelated rules can both apply, and
 * neither listener can know the other exists. Each line is a sentence,
 * already translated.
 */
class ResolvingUploadNotice
{
    /** @var list<string> */
    public array $lines = [];

    public function __construct(
        public readonly User $uploader,
    ) {}

    public function add(string $line): void
    {
        $this->lines[] = $line;
    }
}
