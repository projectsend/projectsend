<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Onboarding\InstallationWelcome;
use App\Modules\Platform\Onboarding\QuickStart;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The screen a brand-new installation opens on: thank you, then the short
 * list of things worth doing first, then — at the very bottom, once there
 * is nothing left to do here — the invitation to the Discord.
 *
 * That order is the point. Somebody who has just installed this has a job
 * in mind, and the fastest way to lose them is to open with a social
 * invitation instead of the thing they came to do. The invitation is
 * still worth making; it just belongs after the work, not in front of it.
 */
class GettingStartedController extends Controller
{
    public function __construct(
        private readonly InstallationWelcome $welcome,
        private readonly QuickStart $quickStart,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        $props = [
            'items' => $this->quickStart->forUser($user),
            'justInstalled' => $this->welcome->pending(),
        ];

        // Only for the person it was addressed to — another staff member
        // reading it later is reading, not receiving.
        if ($this->welcome->isWaitingFor($user)) {
            $this->welcome->dismiss();
        }

        return Inertia::render('system/getting-started', $props);
    }
}
