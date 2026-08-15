<?php

declare(strict_types=1);

namespace App\Modules\Platform\Onboarding;

use App\Models\User;
use App\Modules\Identity\StaffAccounts;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * "This installation is new — has its administrator been shown around
 * yet?" The install-time twin of UpdateWelcome, and deliberately shaped
 * like it: one marker, one person, one time, and an address that keeps
 * working afterwards.
 *
 * The marker is raised explicitly at the two places a first administrator
 * comes into existence — the setup screen and `projectsend:admin` — rather
 * than inferred from an empty database. Inferring it would mean deciding,
 * on every request forever, whether an installation is "new"; a flag
 * written once at the only moment the answer is unambiguous costs one
 * boolean and cannot drift. A third provisioning path added later has to
 * call raise() too, which is why it lives here rather than being copied
 * into both callers.
 */
class InstallationWelcome
{
    public function __construct(
        private readonly Settings $settings,
        private readonly StaffAccounts $staff,
    ) {}

    /**
     * Record that this installation was just installed.
     */
    public function raise(): void
    {
        $this->settings->set(Setting::GettingStartedPending, true);
    }

    public function pending(): bool
    {
        return $this->settings->get(Setting::GettingStartedPending) === true;
    }

    /**
     * Whether this user should be taken to the page right now.
     */
    public function isWaitingFor(User $user): bool
    {
        return $this->pending()
            && $this->mayRead($user)
            && $this->staff->mainAdministrator()?->is($user) === true;
    }

    /**
     * Whether this user may open the page at all.
     *
     * Any staff member, and no capability gate: the page is a list of
     * links to screens they can already reach, each one filtered to what
     * that person may actually do (see QuickStart). Both editions get it —
     * a managed installation still has a first client to add and a first
     * file to upload, which is the whole content.
     */
    public function mayRead(User $user): bool
    {
        return $user->isStaff();
    }

    /**
     * Stop redirecting. The page itself keeps working — somebody who
     * closed it on their way past should be able to find it again.
     */
    public function dismiss(): void
    {
        $this->settings->set(Setting::GettingStartedPending, false);
    }
}
