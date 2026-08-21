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
    /** Runs from the published image: upgrading is pulling a new one. */
    case Container = 'container';

    /**
     * Runs from a container the operator builds themselves, out of a
     * checkout of the repository: upgrading is new code first, then a
     * rebuild. Pulling does nothing here — there is no published image
     * behind these containers to pull.
     */
    case ContainerSource = 'container-source';

    /** Runs from files on a server someone administers: upgrading is INSTALL.md's sequence. */
    case Manual = 'manual';
}
