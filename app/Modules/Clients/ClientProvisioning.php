<?php

declare(strict_types=1);

namespace App\Modules\Clients;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\Notifications\AdminClientRegisteredNotification;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Seats\SeatAllowance;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Notification;

/**
 * A client account coming into existence without a staff member creating
 * it by hand.
 *
 * Two entry points now reach this — the public registration form and a
 * first successful LDAP sign-in — and they must agree on the parts that
 * are policy rather than presentation: whether the account is active or
 * waits for approval, which group it joins, and who gets told. Keeping one
 * definition is the same reasoning FileSharing and StoreUploadedFile
 * already follow.
 *
 * What stays with each caller is what genuinely differs: the registration
 * form's custom fields and group requests, and LDAP's directory stamp.
 */
class ClientProvisioning
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly SeatAllowance $seats,
    ) {}

    /**
     * Whether a newly provisioned client can sign in straight away.
     */
    public function autoApproves(): bool
    {
        return $this->settings->get(Setting::ClientsAutoApprove) === true;
    }

    /**
     * @param  bool|null  $autoApprove  Null asks Setting::ClientsAutoApprove,
     *                                  which is the right question for the
     *                                  public registration form. A caller
     *                                  that has already established who
     *                                  somebody is — LDAP, an identity
     *                                  provider — passes its own answer
     *                                  instead.
     * @param  array<string, mixed>  $context  Placeholders for the action's
     *                                         log template, e.g. which
     *                                         provider an account came from.
     */
    public function provision(
        string $name,
        string $email,
        string $password,
        Action $action,
        AuthSource $source = AuthSource::Local,
        ?string $ldapDn = null,
        ?bool $autoApprove = null,
        array $context = [],
    ): User {
        $autoApprove ??= $this->autoApproves();

        // Only when the account arrives already approved. A request that
        // still needs a decision is not yet a client this installation has
        // taken on, and counting one would let a stranger exhaust a paid
        // limit from the registration form — see SeatAllowance. The guard
        // for those sits on approval instead.
        if ($autoApprove) {
            $this->seats->guardClient();
        }

        $client = User::create([
            'type' => UserType::Client,
            'active' => $autoApprove,
            'account_requested' => ! $autoApprove,
            'role_id' => Role::query()->where('name', SystemRole::Client->value)->value('id'),
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        // Not mass-assignable: where an account's credentials live is a
        // security decision, not an attribute a form may set.
        if ($source !== AuthSource::Local || $ldapDn !== null) {
            $client->forceFill([
                'auth_source' => $source,
                'ldap_dn' => $ldapDn,
                'ldap_synced_at' => $ldapDn === null ? null : now(),
            ])->save();
        }

        $this->activity->log($action, $client, $client, $context);

        $this->joinAutoGroup($client);
        $this->notifyAdministrators($client, pending: ! $autoApprove);

        return $client;
    }

    /**
     * The group every self-provisioned client joins, if one is configured.
     * Direct membership, no approval — an administrator chose this in
     * settings.
     */
    private function joinAutoGroup(User $client): void
    {
        $autoGroupId = (int) $this->settings->get(Setting::ClientsAutoGroup);

        if ($autoGroupId <= 0) {
            return;
        }

        $group = Group::query()->find($autoGroupId);

        $group?->members()->syncWithoutDetaching([$client->id]);
    }

    private function notifyAdministrators(User $client, bool $pending): void
    {
        if ($this->settings->get(Setting::EmailNotificationsEnabled) !== true) {
            return;
        }

        $addresses = $this->settings->get(Setting::AdminNotificationEmails);

        foreach (is_array($addresses) ? $addresses : [] as $address) {
            Notification::route('mail', $address)->notify(
                new AdminClientRegisteredNotification($client->name, $client->email, $pending)
            );
        }
    }
}
