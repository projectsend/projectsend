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
    // Community-only — cut where the installation is managed for you.
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
    case AiConnector = 'ai.connector';

    /**
     * @return list<Edition>
     */
    public function editions(): array
    {
        return match ($this) {
            self::UsersManage,
            self::StorageConfigure,
            self::EmailTransportConfigure,
            self::SystemUpdates,
            self::SchedulerMonitoring,
            self::CustomAssets => [Edition::Community],

            self::Branding,
            self::StorageManaged,
            self::CaptchaManagedKeys,
            self::AiConnector => [Edition::Cloud],
        };
    }

    public function availableIn(Edition $edition): bool
    {
        return in_array($edition, $this->editions(), true);
    }
}
