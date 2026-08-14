<?php

declare(strict_types=1);

namespace App\Modules\Platform\Storage;

/**
 * How much of a container rebuild the uploads directory would survive.
 * See StorageDurability for how each one is established.
 */
enum StorageDurabilityLevel: string
{
    /** On a host path outside Docker's control. Nothing to say. */
    case Durable = 'durable';

    /** In a Docker-managed named volume: survives containers, not Docker. */
    case DockerVolume = 'docker_volume';

    /** On the container's own filesystem: destroyed by the next rebuild. */
    case Ephemeral = 'ephemeral';

    /** /proc/self/mountinfo could not be read — say so rather than guess. */
    case Unknown = 'unknown';
}
