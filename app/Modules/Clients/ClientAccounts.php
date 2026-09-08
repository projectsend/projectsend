<?php

declare(strict_types=1);

namespace App\Modules\Clients;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\Notifications\ClientWelcomeNotification;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Seats\SeatAllowance;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * Creating a client account — the rules and the side effects, shared by
 * every surface that makes one.
 *
 * The same argument StaffAccounts makes for staff. What a client account
 * *is* — its type, its role, an active flag, a quota where zero means
 * "inherit the site default" rather than "none" — is a set of invariants,
 * and an invariant enforced in one controller and re-implemented in
 * another is one that will eventually hold in only one of them. There are
 * three surfaces onto this now: the staff screens, `/api/v1/clients`, and
 * the platform control plane in the private package, which reaches this
 * by name because it cannot import a host class.
 *
 * What stays with the caller is what genuinely differs: the shape of the
 * request, its validation rules, its response, and anything about *who is
 * asking* — a client-scoped staff member gaining the client on their own
 * roster is a fact about the creator, not about the account created.
 *
 * **Not to be confused with ClientProvisioning**, which sits beside it and
 * handles the other half: an account that comes into existence without
 * anybody deciding to create it — the public registration form, and a
 * first successful LDAP sign-in. The policies genuinely differ rather than
 * merely duplicating. An account made here is approved and verified by
 * construction, because somebody who already knows who this is asked for
 * it; one made there may wait for approval, joins a configured group, and
 * tells the administrators it arrived.
 */
class ClientAccounts
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly SeatAllowance $seats,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  int  $storageQuotaMb  0 means no per-account quota and
     *                               inherits the site default at
     *                               enforcement time — see
     *                               ClientStorageUsage::quotaMb(). It does
     *                               not mean unlimited.
     * @param  bool  $welcome  whether this installation should email the
     *                         new account. A caller that sends its own
     *                         welcome passes false rather than having the
     *                         customer receive two.
     */
    public function create(
        string $name,
        string $email,
        string $password,
        int $storageQuotaMb = 0,
        bool $welcome = true,
        string $emailField = 'email',
    ): User {
        // Before anything is written, and deliberately not left to the
        // caller. The platform sets this cap and the platform is also what
        // calls the control plane — so enforcing it here is what stops a
        // leaked control token minting accounts without limit. A guard
        // that only ran on the surfaces that remembered it would not be a
        // guard.
        $this->seats->guardClient($emailField);

        $client = User::create([
            'type' => UserType::Client,
            'active' => true,
            'account_requested' => false,
            'role_id' => Role::query()->where('name', SystemRole::Client->value)->value('id'),
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'storage_quota_mb' => $storageQuotaMb,
        ]);

        // forceFill, and not part of the create() array above: like
        // StaffAccounts, email_verified_at is deliberately absent from
        // User::$fillable — where an account stands is a security decision
        // rather than an attribute — so mass assignment drops it in
        // silence. Every client-creation path used to pass it in that
        // array and lose it. The intent is real: an account created by
        // somebody who already knows who this is has no address to
        // confirm and nobody to confirm it to. (Inert today, since
        // MustVerifyEmail is not enabled on the model, but the column is
        // what a later switch would read.)
        $client->forceFill(['email_verified_at' => now()])->save();

        $this->activity->log(Action::UserCreated, subject: $client);

        if ($welcome && $this->settings->get(Setting::EmailNotificationsEnabled) === true) {
            $client->notify(new ClientWelcomeNotification);
        }

        return $client;
    }
}
