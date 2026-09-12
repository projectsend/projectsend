<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Clients\Models\Invitation;
use App\Modules\Clients\Notifications\ClientInvitationNotification;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Erasure\AvailableEmailRule;
use App\Modules\Platform\Seats\SeatAllowance;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff sending a client an invitation to register, ahead of the public
 * form — the "New client" button's sibling for an installation that
 * would rather have somebody set their own password than hand them one.
 */
class InvitationController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Settings $settings,
        private readonly ClientStorageUsage $storageUsage,
        private readonly SeatAllowance $seats,
    ) {}

    public function create(): Response
    {
        return Inertia::render('clients/invite', [
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            // Resolved, not raw — see ClientsController::create()'s note on
            // the same prop: this is what will actually happen, and the
            // form's own field mirrors this resolution to draw its hint.
            'default_storage_quota_mb' => $this->storageUsage->defaultQuotaMb(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new AvailableEmailRule],
            'name' => ['nullable', 'string', 'max:255'],
            'group_id' => ['required', 'integer', Rule::in([0, ...Group::query()->pluck('id')->all()])],
            'storage_quota_mb' => ['nullable', 'integer', 'min:0'],
        ]);

        // Asked here as well as at redemption. An outstanding invitation
        // is not a client and is not counted as one — the same rule a
        // pending account request follows — so this refuses sending a link
        // a full installation could not honour, rather than reserving
        // anything. The redemption door still guards, because the seat can
        // be taken by somebody else in the days between.
        $this->seats->guardClient();

        $group = $validated['group_id'] > 0
            ? Group::query()->whereKey($validated['group_id'])->first()
            : null;

        $invitation = Invitation::issue(
            email: $validated['email'],
            name: $validated['name'] ?? null,
            group: $group,
            invitedBy: $request->user(),
            expiresAt: now()->addHours((int) $this->settings->get(Setting::ClientInvitationExpiryHours)),
            // The `integer` rule above validates the shape but does not
            // cast it — this arrives as a numeric string from the request,
            // same as group_id, and issue() takes a real int.
            storageQuotaMb: (int) ($validated['storage_quota_mb'] ?? 0),
        );

        Notification::route('mail', $invitation->email)->notify(
            new ClientInvitationNotification($invitation->name ?? $invitation->email, $invitation->token),
        );

        $this->activity->log(Action::ClientInvited, context: ['email' => $invitation->email]);

        return redirect()->route('clients.index')->with('success', __('Invitation sent.'));
    }
}
