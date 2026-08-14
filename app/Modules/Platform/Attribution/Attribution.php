<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attribution;

use App\Modules\Platform\Attribution\Events\ResolvingAttribution;
use Illuminate\Support\Facades\Event;

/**
 * Whether this installation names ProjectSend on the surfaces its
 * clients and visitors see, and in the mail it sends.
 *
 * Deliberately *not* a singleton, and deliberately not memoised. The
 * answer is read twice per web request at most — once for the Inertia
 * shared prop, once for the generator meta — and once per outgoing
 * email. A queue worker stays alive for hours, so a cached answer here
 * would keep sending mail with a footer the operator turned off that
 * morning; the same reason Settings and EmailThemeService are read at
 * send time rather than at boot.
 */
final class Attribution
{
    public function visible(): bool
    {
        $event = new ResolvingAttribution;

        Event::dispatch($event);

        return $event->visible;
    }
}
