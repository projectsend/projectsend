<?php

declare(strict_types=1);

namespace App\Modules\Platform\Updates;

use App\Models\User;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\StaffAccounts;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * "An update just landed — has the administrator been told what was in
 * it?" One answer, shared by the middleware that redirects and the
 * controller that renders.
 *
 * Deliberately one person and one time. The update happened to the
 * *installation*, and interrupting five staff members with the same
 * congratulation — each of whom then has to dismiss a page they did not
 * ask for — would turn a nice moment into a nuisance that gets asked
 * about in a support thread. So it goes to whoever administers this
 * installation, once, and stays reachable at its own address for anybody
 * who wants it afterwards.
 */
class UpdateWelcome
{
    public function __construct(
        private readonly Settings $settings,
        private readonly CapabilityRegistry $capabilities,
        private readonly PermissionChecker $permissions,
        private readonly StaffAccounts $staff,
        private readonly ReleaseNotes $notes,
    ) {}

    /**
     * Whether this user should be taken to the welcome page right now.
     */
    public function isWaitingFor(User $user): bool
    {
        return $this->pending() !== null
            && $this->isTheMainAdministrator($user)
            && $this->mayRead($user);
    }

    /**
     * Whether this user may open the page at all, update or no update.
     *
     * Carries the About screen's environment gate verbatim — view_system_info
     * plus SystemUpdates — because this answers the same question that one
     * does, in more detail. The capability is what keeps it off managed
     * installations, where nobody running this application performed an
     * update and "thank you for updating" would be addressed to the wrong
     * person entirely.
     */
    public function mayRead(User $user): bool
    {
        return $user->isStaff()
            && $this->capabilities->has(Capability::SystemUpdates)
            && $this->permissions->allows($user, Permission::ViewSystemInfo);
    }

    /**
     * The versions an unseen update moved between, or null if there is
     * nothing waiting.
     *
     * @return array{from: string, to: string}|null
     */
    public function pending(): ?array
    {
        $to = $this->string(Setting::UpdateWelcomeTo);

        if ($to === '') {
            return null;
        }

        return ['from' => $this->string(Setting::UpdateWelcomeFrom), 'to' => $to];
    }

    /**
     * Forget the pending update, so the redirect happens exactly once.
     *
     * Only the marker is cleared — the page itself keeps working, and
     * keeps describing the same release, because "I closed it by accident"
     * should not be an unrecoverable mistake.
     */
    public function dismiss(): void
    {
        $this->settings->set(Setting::UpdateWelcomeFrom, '');
        $this->settings->set(Setting::UpdateWelcomeTo, '');
    }

    /**
     * What to show: every release between the version that was running and
     * the one that is now.
     *
     * Falls back to the running version once the marker has been cleared,
     * so the page still has something to say when it is opened later from
     * a link rather than arrived at from an update.
     *
     * @return list<array{version: string, date: string, intro: list<string>, groups: list<array{heading: string, items: list<array{title: string, body: string}>}>}>
     */
    public function releases(): array
    {
        return $this->notes->between($this->previousVersion(), $this->version());
    }

    /**
     * The version this page is about — the one the update arrived at, or
     * the one running when it is opened cold.
     */
    public function version(): string
    {
        $pending = $this->pending();

        return $pending === null ? (string) config('projectsend.version') : $pending['to'];
    }

    /**
     * The version left behind, empty when unknown or when the page was
     * opened outside an update.
     */
    public function previousVersion(): string
    {
        return $this->pending()['from'] ?? '';
    }

    /**
     * Whether this page is being read because an update just happened, as
     * opposed to opened from a link afterwards. The wording differs: one
     * says "you have updated", the other only "here is what this release
     * brought".
     */
    public function isFresh(): bool
    {
        return $this->pending() !== null;
    }

    private function isTheMainAdministrator(User $user): bool
    {
        return $this->staff->mainAdministrator()?->is($user) === true;
    }

    private function string(Setting $setting): string
    {
        $value = $this->settings->get($setting);

        return is_string($value) ? $value : '';
    }
}
