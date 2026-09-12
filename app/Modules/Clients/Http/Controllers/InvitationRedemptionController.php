<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\ClientProvisioning;
use App\Modules\Clients\Models\Invitation;
use App\Modules\Clients\Notifications\ClientInvitationNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A client redeeming the link an invitation emailed them — the invited
 * counterpart to RegistrationController's public form. Reaching the form
 * at all is the whole difference: it is gated by a specific address
 * having a live token rather than by Setting::ClientsCanRegister, and the
 * account that comes out of it is provisioned exactly the way any other
 * self-registration is (ClientProvisioning), so an installation with
 * auto-approve off still puts one in the same queue as everybody else.
 */
class InvitationRedemptionController extends Controller
{
    /**
     * How many times an invitation may be renewed by the person holding
     * it, before a staff member has to send a new one.
     *
     * Without a limit, expiry stops meaning anything: whoever holds a dead
     * link can re-arm it, so the window an operator configured is only as
     * short as the longest anybody bothers to wait. Three is enough for
     * somebody who genuinely keeps missing it, and short enough that a link
     * sitting somewhere it should not be — a forwarded thread, a shared
     * inbox, a mailbox that changed hands — eventually stops answering on
     * its own, as the expiry setting says it will.
     *
     * Revoking is the immediate version of the same decision, and does not
     * wait for this: see InvitationController::destroy().
     */
    private const SELF_RESEND_LIMIT = 3;

    public function __construct(
        private readonly ClientProvisioning $provisioning,
        private readonly ActivityLogger $activity,
        private readonly Settings $settings,
    ) {}

    public function create(Request $request): Response
    {
        $token = (string) $request->route('token');
        $invitation = $this->findUsable($token);

        return Inertia::render('auth/invite', [
            'token' => $token,
            'email' => $invitation->email ?? '',
            'name' => $invitation->name ?? '',
            'status' => $request->session()->get('status'),
            // Same shape as NewPasswordController::create()'s $expired: one
            // answer for "no such token" and "spent or expired token",
            // because telling them apart would tell a guesser which
            // addresses this installation has invited.
            'expired' => $invitation === null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $invitation = $this->findUsable($validated['token']);

        if ($invitation === null) {
            throw ValidationException::withMessages([
                'token' => [__('This invitation is no longer valid. Ask whoever invited you to send a new one.')],
            ]);
        }

        // An invitation is live for days, and the address it names can be
        // taken in the meantime — staff got impatient and made the account
        // by hand, or the person registered through the public form. The
        // unique index on users.email spans trashed rows, so provision()
        // would raise a QueryException here rather than refusing: a 500 on
        // the screen of somebody who has just typed a password. Every other
        // caller with no form to validate asks this first, for this reason
        // — see ClientProvisioning::addressIsFree().
        if (! $this->provisioning->addressIsFree($invitation->email)) {
            throw ValidationException::withMessages([
                // Says what happened, because the person holding this link
                // already knows the address is theirs — it is the one the
                // invitation was sent to. There is nothing here to disclose
                // that the invitation itself did not.
                'token' => [__('An account already exists for this email address. Try signing in instead, or reset your password.')],
            ]);
        }

        $client = $this->provisioning->provision(
            name: $validated['name'],
            email: $invitation->email,
            password: $validated['password'],
            action: Action::ClientInvitationRedeemed,
            // Always, regardless of Setting::ClientsAutoApprove: an
            // invitation names a specific address a staff member already
            // decided to let in, which is the trust an approval queue
            // exists to establish for the address it never named.
            autoApprove: true,
            storageQuotaMb: $invitation->storage_quota_mb,
        );

        if ($invitation->group !== null) {
            $invitation->group->members()->syncWithoutDetaching([$client->id]);
        }

        $invitation->forceFill(['status' => Invitation::STATUS_REDEEMED])->save();

        return redirect()->route('login')->with(
            'status',
            $client->account_requested
                ? __('Your account has been created. You will be able to log in once it is approved.')
                : __('Your account has been created. You can log in now.'),
        );
    }

    /**
     * Resends a fresh link to the same address without anybody deciding
     * to — the invited person asked for it, not an administrator. A spent
     * or genuinely unknown token answers the same as an expired one: this
     * is the one door on the flow an anonymous visitor can knock on
     * repeatedly, so it must not become a way to learn which addresses
     * were ever invited.
     *
     * The new link goes to the address on the invitation, never to whoever
     * asked, so holding a leaked URL gets nobody a working one. What this
     * does spend is the operator's expiry window, which is why
     * SELF_RESEND_LIMIT caps how often it can be spent, and why staff can
     * end it outright by revoking.
     */
    public function resend(Request $request): RedirectResponse
    {
        $token = (string) $request->route('token');
        $invitation = $this->findUsable($token, includingExpired: true);

        // Spent, unknown, revoked, or renewed as often as it may be: all
        // four answer the same sentence below, and none of them sends
        // anything. Only the last of the four is a link whose holder did
        // nothing wrong, and telling them apart here would tell a guesser
        // which addresses this installation has invited.
        if ($invitation !== null && $invitation->resends < self::SELF_RESEND_LIMIT) {
            $fresh = Invitation::issue(
                email: $invitation->email,
                name: $invitation->name,
                group: $invitation->group,
                invitedBy: $invitation->invitedBy,
                expiresAt: now()->addHours((int) $this->settings->get(Setting::ClientInvitationExpiryHours)),
                storageQuotaMb: $invitation->storage_quota_mb,
                resends: $invitation->resends + 1,
            );

            Notification::route('mail', $fresh->email)->notify(
                new ClientInvitationNotification($fresh->name ?? $fresh->email, $fresh->token),
            );

            // Nobody is signed in, so this records no actor — which is the
            // point of logging it. Sending and redeeming were already in
            // the trail; renewing was the one step that moved an invitation
            // along with no staff member behind it and left no trace.
            // Bounded by the limit above, so an anonymous door cannot flood
            // the log.
            $this->activity->log(Action::ClientInvitationResent, context: ['email' => $fresh->email]);
        }

        return back()->with('status', __('If that invitation can still be resent, a new one is on its way.'));
    }

    /**
     * The invitation $token names, if it is still one store() would
     * accept — pending and not expired, unless $includingExpired asks for
     * the resend door's wider question instead.
     */
    private function findUsable(string $token, bool $includingExpired = false): ?Invitation
    {
        $invitation = Invitation::query()->pending()->where('token', $token)->first();

        if ($invitation === null) {
            return null;
        }

        if (! $includingExpired && $invitation->isExpired()) {
            return null;
        }

        return $invitation;
    }
}
