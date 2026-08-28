<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Clients\ClientProvisioning;
use App\Modules\Identity\AuthSource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A provider identity signing in for the first time, with no local
 * account yet.
 *
 * Always a client, never staff — there is no role parameter on this path,
 * so no misconfiguration can let an identity provider mint an account
 * with authority over the installation. v1's equivalent had a
 * `social_login_default_role` setting that offered staff roles.
 *
 * Approval is the provider's own `auto_approve`, not the
 * `ClientsAutoApprove` a self-registration asks, for the reason LDAP
 * already established: one is about strangers arriving at a public form,
 * the other about people a provider you configured has authenticated.
 * `ClientsAutoGroup` stays shared, since which group a new client joins
 * does not turn on how they arrived.
 */
class SocialProvisioner
{
    public function __construct(
        private readonly ClientProvisioning $clients,
    ) {}

    /**
     * @param  bool  $autoApprove  Decided by the caller, not read from the
     *                             settings row: an address the provider
     *                             never verified goes to the approval
     *                             queue whatever the setting says.
     */
    public function provision(SocialSettings $settings, SocialIdentity $identity, bool $autoApprove): ?User
    {
        if (! $settings->auto_provision || $identity->email === null) {
            return null;
        }

        // A deleted account still holds its address, and the insert below
        // would hit the unique index — a 500 in the middle of a sign-in.
        // Refusing here gives the caller the same "there is no account here
        // for that address" it gives every other unprovisionable identity,
        // which is also all a stranger should learn: whether an address was
        // once an account here is not the provider's to publish.
        if (! $this->clients->addressIsFree($identity->email)) {
            Log::warning('A provider identity was not provisioned: the address belongs to a deleted account.', [
                'provider' => $settings->provider->value,
                'email' => $identity->email,
            ]);

            return null;
        }

        return $this->clients->provision(
            name: $identity->name ?? $identity->email,
            email: $identity->email,
            // A password they will never use and never learn: this account
            // signs in through the provider. Generated rather than left
            // null so nothing downstream has to special-case an empty
            // hash, and it is why promoting one to staff requires setting
            // a real password — see AccountConversion::requiresNewPassword().
            password: Str::password(64),
            action: Action::SocialClientProvisioned,
            source: AuthSource::Social,
            autoApprove: $autoApprove,
            context: ['provider' => $settings->provider->label()],
        );
    }
}
