<?php

declare(strict_types=1);

namespace App\Modules\Platform\Capabilities;

/**
 * Every capability that differs between editions, in one place.
 *
 * The registry is bidirectional: each capability declares the editions it
 * belongs to. Community-only capabilities exist because managed
 * installations handle those concerns outside the application; cloud-only
 * capabilities exist because some features cannot ship inside a self-hosted
 * package (commercial trust providers, vendor credentials, legal posture).
 *
 * Adding a capability means adding a case here and its editions below —
 * enforcement points (gates, Inertia shared props, API middleware) derive
 * from this enum and must not need changes.
 */
enum Capability: string
{
    // Both editions. It was Community-only while a managed installation's
    // staff accounts were expected to be created from outside — but a
    // platform does not know whether Alice should be an Account Manager,
    // any more than it knows where her files go when she leaves, and the
    // seat count it does own is enforced by PROJECTSEND_PLATFORM_MAX_STAFF_USERS
    // rather than by closing the screen. Capacity is the platform's; who
    // fills it is the tenant's. Same division managed storage already uses:
    // the bucket is provisioned, what goes in it is not.
    case UsersManage = 'users.manage';
    case StorageConfigure = 'storage.configure';
    case EmailTransportConfigure = 'email.transport.configure';
    case SystemUpdates = 'system.updates';

    // Community-only — scheduled-task run history and failed-queue-job
    // visibility. Cut on managed installations, where infrastructure
    // monitoring happens outside this application; a transient failure
    // that self-heals on the next
    // run showing up in a customer-facing UI would do more harm than good.
    case SchedulerMonitoring = 'scheduler.monitoring';

    // Community-only — code lives in the separate projectsend/community-modules
    // package (github.com/projectsend/community-modules, public and GPLv2);
    // arbitrary HTML/CSS/JS injection is too risky to offer on the hosted
    // platform. Separate for operational reasons, not secret ones — unlike
    // cloud-modules below.
    case CustomAssets = 'custom_assets.manage';

    // Cloud-only — code lives in the private projectsend/cloud-modules
    // package (github.com/projectsend/cloud-modules), never in this repo.
    case Branding = 'branding.customize';

    // Cloud-only — the storage backend is ours, supplied by the
    // environment when the instance is provisioned and not the customer's
    // to see or change. The counterpart of StorageConfigure above rather
    // than a contradiction of it: one edition configures its own bucket,
    // the other is given one. Behaviour lives in the private
    // projectsend/cloud-modules package; without it this capability is
    // simply inert and files stay on local disk.
    case StorageManaged = 'storage.managed';

    // Cloud-only — managed installations supply CAPTCHA keys centrally, so
    // protection is on before anybody finds the settings screen. The
    // feature itself is in both editions and behind no capability: this
    // covers only the option of using *our* credentials, which cannot ship
    // inside a self-hosted package.
    case CaptchaManagedKeys = 'captcha.managed_keys';

    // Cloud-only — letting an AI assistant act on this installation on
    // somebody's behalf. Code lives in the private
    // projectsend/cloud-modules package; without it this capability is
    // inert, which is the point: the edition boundary here is which
    // package is installed, not a flag an installation can set. Present
    // in this enum even so, because a package cannot extend a closed one
    // — core has to publish the key before anything can gate on it.
    // Cloud-only — marks an installation that a platform provisioned and
    // looks after, for the screens that have to know the difference.
    //
    // It does not close the tenant's own /users screens. It used to say
    // so, and that stopped being true when UsersManage opened on both
    // editions: a platform sells the seats, the tenant decides who sits
    // in them. Capacity is the platform's, and it arrives as
    // PROJECTSEND_PLATFORM_MAX_STAFF_USERS rather than as a shut door.
    //
    // The seat *number* deliberately does not live here. There are no
    // billing or plan tiers in this application to key off — the same
    // reason config/api.php gives for not inventing an installation-level
    // rate limit — so the limit arrives from the environment and this
    // capability only says who is in charge.
    //
    // Declared before the module that implements it exists, and that is
    // the point: a capability added after a release is invisible to every
    // image built from one, which is exactly how StorageManaged came to
    // sit unusable for a fleet that had everything else in place.
    case PlatformManaged = 'platform.managed';

    case AiConnector = 'ai.connector';

    /**
     * @return list<Edition>
     */
    public function editions(): array
    {
        return match ($this) {
            self::StorageConfigure,
            self::EmailTransportConfigure,
            self::SystemUpdates,
            self::SchedulerMonitoring,
            self::CustomAssets => [Edition::Community],

            self::UsersManage => [Edition::Community, Edition::Cloud],

            self::Branding,
            self::StorageManaged,
            self::CaptchaManagedKeys,
            self::PlatformManaged,
            self::AiConnector => [Edition::Cloud],
        };
    }

    public function availableIn(Edition $edition): bool
    {
        return in_array($edition, $this->editions(), true);
    }
}
