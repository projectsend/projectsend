<?php

declare(strict_types=1);

namespace App\Modules\Platform\Installation;

/**
 * How this copy of ProjectSend was put on the server, which is the only
 * thing that decides what "upgrade" means for the person reading the
 * screen. See Installation for how it is established.
 */
enum InstallationKind: string
{
    /** Runs from an image: upgrading is pulling a new one. */
    case Container = 'container';

    /** Runs from files on a server someone administers: upgrading is INSTALL.md's sequence. */
    case Manual = 'manual';
}
