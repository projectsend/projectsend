<?php

declare(strict_types=1);

namespace App\Modules\Platform\Onboarding;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Scheduling\ScheduledTaskRun;

/**
 * The short list of things worth doing on a brand-new installation, in
 * the order they make sense, filtered to what this person can actually
 * do here.
 *
 * Two filters, and both matter. **Permission** keeps the list honest for
 * anybody who is not an administrator — a link to a screen that answers
 * 403 is worse than no link. **Capability** keeps it honest per edition:
 * on a managed installation there are no staff accounts to create, no
 * mail server to point at and no scheduler to check, because somebody
 * else does all three. A getting-started list that opens with three tasks
 * you are not allowed to perform teaches the reader to ignore it.
 *
 * Each step also says whether it is **essential**, because they are not
 * equally urgent and a list that pretends otherwise is a list nobody
 * reads twice. Essential means the installation does not really work
 * without it — including the one whose absence is silent: nothing tells
 * you that password resets are going nowhere until somebody needs one.
 * It is a fact about the task rather than about the layout, which is why
 * it is decided here and not in the page.
 *
 * The tasks that can be answered from the database are answered: "add
 * your first client", "upload a file" and "check the scheduler" tick
 * themselves. Nothing else is checkable without guessing — a theme that
 * was never changed is indistinguishable from one that was chosen
 * deliberately — and a tick that means "we assume so" is worse than no
 * tick at all.
 *
 * Descriptions are one short line each. This is a list to be scanned on
 * the first day, by somebody who wants to get on with it; the screen at
 * the other end of the link explains itself.
 */
class QuickStart
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly PermissionChecker $permissions,
    ) {}

    /**
     * @return list<array{key: string, title: string, description: string, href: string, essential: bool, done: bool}>
     */
    public function forUser(User $user): array
    {
        $items = [];

        if ($this->permissions->allows($user, Permission::CreateClients)) {
            $items[] = [
                'key' => 'client',
                'title' => __('Add your first client'),
                'description' => __('The people you send files to.'),
                'href' => route('clients.create', absolute: false),
                'essential' => true,
                'done' => $this->hasAClient(),
            ];
        }

        if ($this->permissions->allows($user, Permission::Upload)) {
            $items[] = [
                'key' => 'upload',
                'title' => __('Upload a file'),
                'description' => __('Drop one in and choose who gets it.'),
                'href' => route('files.create', absolute: false),
                'essential' => true,
                'done' => $this->hasAFile(),
            ];
        }

        if ($this->permissions->allows($user, Permission::CreateGroups)) {
            $items[] = [
                'key' => 'group',
                'title' => __('Group your clients'),
                'description' => __('Share once instead of six times.'),
                'href' => route('groups.create', absolute: false),
                'essential' => false,
                'done' => false,
            ];
        }

        if ($this->permissions->allows($user, Permission::EditSettings)) {
            $items[] = [
                'key' => 'theme',
                'title' => __('Choose how your file lists look'),
                'description' => __('Four layouts for the client-facing pages.'),
                'href' => route('system-settings.theming.edit', absolute: false),
                'essential' => false,
                'done' => false,
            ];

            $items[] = [
                'key' => 'email-theme',
                'title' => __('Choose how your email looks'),
                'description' => __('Previewed on a real message.'),
                'href' => route('system-settings.theming.edit', ['tab' => 'email'], absolute: false),
                'essential' => false,
                'done' => false,
            ];
        }

        // Community only: on a managed installation the mail server is
        // ours, and there is nothing here to point anywhere.
        if ($this->permissions->allows($user, Permission::EditSettings)
            && $this->capabilities->has(Capability::EmailTransportConfigure)) {
            $items[] = [
                'key' => 'email',
                'title' => __('Set up your mail server'),
                'description' => __('Resets and share links go out through it.'),
                'href' => route('system-settings.email.edit', absolute: false),
                'essential' => true,
                'done' => false,
            ];
        }

        // Both editions since 2.2.0. A managed installation was once the
        // example of a site with no staff accounts of its own to hand out;
        // it is sold seats and fills them itself now, so this step belongs
        // on its list too. The capability stays in the condition as the
        // seam an edition difference would travel through.
        if ($this->permissions->allows($user, Permission::CreateUsers)
            && $this->capabilities->has(Capability::UsersManage)) {
            $items[] = [
                'key' => 'team',
                'title' => __('Add the rest of your team'),
                'description' => __('Staff accounts, each scoped by its role.'),
                'href' => route('users.create', absolute: false),
                'essential' => false,
                'done' => false,
            ];
        }

        // Community only: scheduled work is somebody else's problem on a
        // managed installation, and its screen does not exist there.
        if ($this->permissions->allows($user, Permission::ViewSystemInfo)
            && $this->capabilities->has(Capability::SchedulerMonitoring)) {
            $items[] = [
                'key' => 'scheduler',
                'title' => __('Check the scheduler is running'),
                'description' => __('Expiring files and queued email need it.'),
                'href' => route('system-settings.scheduler.index', absolute: false),
                'essential' => true,
                'done' => $this->schedulerHasRun(),
            ];
        }

        return $items;
    }

    private function hasAClient(): bool
    {
        return User::query()->where('type', UserType::Client)->exists();
    }

    private function hasAFile(): bool
    {
        return File::query()->exists();
    }

    /**
     * The scheduler step is the one essential task nobody could ever
     * complete: it was hardcoded unticked even though the screen it links
     * to already answers the question from these rows.
     *
     * A row exists once the scheduler has run here at all — which is
     * precisely what the step asks. Its status does not come into it: a
     * task that ran and failed still proves cron reaches this
     * installation, and the Scheduler screen is where that failure is
     * read.
     */
    private function schedulerHasRun(): bool
    {
        return ScheduledTaskRun::query()->exists();
    }
}
