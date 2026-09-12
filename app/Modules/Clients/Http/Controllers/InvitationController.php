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
use App\Support\Pagination;
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
        // Outstanding means pending, expired ones included. An expired
        // invitation is not inert: until it is revoked, whoever holds the
        // link can ask for a fresh one, so a screen that hid them would
        // hide exactly the rows worth acting on.
        $invitations = Invitation::query()
            ->pending()
            ->with(['group:id,name', 'invitedBy:id,name'])
            // Soonest to expire first, which puts the already-expired rows
            // at the top — the ones somebody can still ask to have renewed,
            // and so the ones worth deciding about. Ties broken by id
            // because a batch sent in one minute shares an expiry.
            ->orderBy('expires_at')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'group' => $invitation->group?->name,
                'invited_by' => $invitation->invitedBy?->name,
                'created_at' => $invitation->created_at?->toIso8601String(),
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'expired' => $invitation->isExpired(),
            ]);

        return Inertia::render('clients/invite', [
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            // Resolved, not raw — see ClientsController::create()'s note on
            // the same prop: this is what will actually happen, and the
            // form's own field mirrors this resolution to draw its hint.
            'default_storage_quota_mb' => $this->storageUsage->defaultQuotaMb(),
            'invitations' => $invitations->items(),
            'pagination' => Pagination::meta($invitations),
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

    /**
     * Cancels an invitation nobody has used yet.
     *
     * Until this existed, letting one expire was the only way to take it
     * back — and the expired page's own "send me a new one" button undid
     * that, silently, for anybody still holding the link. Revoking is the
     * decision that button cannot reverse: STATUS_REVOKED is outside
     * pending(), which is the scope both the redemption and the resend
     * doors look through.
     *
     * The row is kept rather than deleted, for the reason
     * Invitation::STATUS_SUPERSEDED is kept: the activity log names who
     * invited this address and when, and that trail should still lead
     * somewhere.
     */
    public function destroy(Invitation $invitation): RedirectResponse
    {
        // Already spent, already superseded, already revoked: there is
        // nothing left to cancel, and saying so is better than reporting a
        // success that changed nothing.
        abort_unless($invitation->status === Invitation::STATUS_PENDING, 404);

        $invitation->forceFill(['status' => Invitation::STATUS_REVOKED])->save();

        $this->activity->log(Action::ClientInvitationRevoked, context: ['email' => $invitation->email]);

        return back()->with('success', __('Invitation revoked.'));
    }
}
