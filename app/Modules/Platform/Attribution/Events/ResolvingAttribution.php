<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attribution\Events;

/**
 * "Does this installation name ProjectSend to the people it serves?" —
 * asked once per page render and once per outgoing email.
 *
 * The default is true and there is no setting behind it, because on a
 * community installation there is nothing to decide: the answer is yes.
 * Hiding attribution is a white-label feature, and white-labelling is
 * one of the things a hosted customer pays for — so the code that can
 * answer no ships in projectsend/cloud-modules and nowhere else. An
 * installation running no packages does not merely fail the check, it
 * has no listener to run.
 *
 * Scope is deliberate: this governs what *clients and anonymous
 * visitors* see, plus outgoing mail. Staff surfaces — the sidebar
 * version line and /system/about, where the licence and the source
 * link live — are never hidden by it. Whoever operates the
 * installation is not the audience being white-labelled from.
 *
 * Listened to by *string* class name from a package, same as every
 * other hook here — see docs/extension-points-architecture.md.
 */
final class ResolvingAttribution
{
    /**
     * Set false by any listener that hides attribution. Never set back
     * to true: one listener declining to hide is not the others'
     * answer, so this only ever moves in one direction.
     */
    public bool $visible = true;
}
