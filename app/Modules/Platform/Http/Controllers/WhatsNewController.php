<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Updates\UpdateWelcome;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The screen an administrator lands on the first time they open
 * ProjectSend after an update: what it is now running, an invitation to
 * the place where the people are, and then what the release actually
 * brought.
 *
 * That order is the design. The thanks and the invitation are for the
 * person who just did the work — a moment that exists exactly once per
 * update and is otherwise spent staring at a dashboard that looks
 * identical to yesterday's. The release notes are underneath because
 * they are the part that can be read later, or not at all.
 */
class WhatsNewController extends Controller
{
    public function __construct(private readonly UpdateWelcome $welcome) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        // Read before dismissing: everything below describes the update
        // that the next line forgets.
        $props = [
            'version' => $this->welcome->version(),
            'previousVersion' => $this->welcome->previousVersion(),
            'justUpdated' => $this->welcome->isFresh(),
            'releases' => $this->welcome->releases(),
        ];

        // Only for the person it was addressed to. Another administrator
        // opening the page from a link is reading, not receiving, and must
        // not consume a greeting meant for somebody else.
        if ($this->welcome->isWaitingFor($user)) {
            $this->welcome->dismiss();
        }

        return Inertia::render('system/whats-new', $props);
    }
}
