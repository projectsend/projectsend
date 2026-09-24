<?php

declare(strict_types=1);

namespace App\Modules\Identity\Erasure\Events;

/**
 * "When somebody deletes their own account, do their files go at once?"
 * Asked by SelfDeletion, with the installation's own setting already in
 * $filesImmediately.
 *
 * A hosted platform answers it for some installations. On the free
 * shared instance the files are only ever the customer's own, and the
 * staff who would keep seeing them through the grace period are us, so
 * cloud-modules makes this true there. The settings screen shows the
 * choice as made by the platform rather than offering a switch that
 * would do nothing.
 *
 * Listened to by *string* class name from a package, same as every other
 * hook here — see docs/extension-points-architecture.md.
 */
final class ResolvingSelfDeletion
{
    /**
     * Whether a listener made the choice rather than the setting.
     */
    public bool $managed = false;

    public function __construct(
        public bool $filesImmediately,
    ) {}

    /**
     * One direction only, the way ResolvingAttribution moves: a listener
     * can make deletion sooner, never later. A package that could turn
     * "delete my files now" into "keep them for a month" would be
     * overruling a promise the person was shown when they confirmed.
     */
    public function deleteFilesImmediately(): void
    {
        $this->filesImmediately = true;
        $this->managed = true;
    }
}
