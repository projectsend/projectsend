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
 * The two tasks that can be answered from the database are answered:
 * "create your first client" and "upload a file" tick themselves. Nothing
 * else is checkable without guessing — a theme that was never changed is
 * indistinguishable from one that was chosen deliberately — and a tick
 * that means "we assume so" is worse than no tick at all.
 */
class QuickStart
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly PermissionChecker $permissions,
    ) {}

    /**
     * @return list<array{key: string, title: string, description: string, href: string, done: bool}>
     */
    public function forUser(User $user): array
    {
        $items = [];

        if ($this->permissions->allows($user, Permission::CreateClients)) {
            $items[] = [
                'key' => 'client',
                'title' => __('Add your first client'),
                'description' => __('A client is somebody you send files to. They get their own account and see only what you share with them.'),
                'href' => route('clients.create', absolute: false),
                'done' => $this->hasAClient(),
            ];
        }

        if ($this->permissions->allows($user, Permission::Upload)) {
            $items[] = [
                'key' => 'upload',
                'title' => __('Upload a file'),
                'description' => __('Drop a file in and choose who it goes to. Uploads resume by themselves if the connection drops.'),
                'href' => route('files.create', absolute: false),
                'done' => $this->hasAFile(),
            ];
        }

        if ($this->permissions->allows($user, Permission::CreateGroups)) {
            $items[] = [
                'key' => 'group',
                'title' => __('Group the clients who get the same things'),
                'description' => __('Share with a group once instead of with six people individually, and anyone added later gets it too.'),
                'href' => route('groups.create', absolute: false),
                'done' => false,
            ];
        }

        if ($this->permissions->allows($user, Permission::EditSettings)) {
            $items[] = [
                'key' => 'theme',
                'title' => __('Choose how your file lists look'),
                'description' => __('Four layouts for the pages your clients and visitors see. Each one previews before you switch.'),
                'href' => route('system-settings.theming.edit', absolute: false),
                'done' => false,
            ];

            $items[] = [
                'key' => 'email-theme',
                'title' => __('Choose how your email looks'),
                'description' => __('Four themes for the messages ProjectSend sends, previewed on a real message rather than a mock-up.'),
                'href' => route('system-settings.theming.edit', ['tab' => 'email'], absolute: false),
                'done' => false,
            ];
        }

        // Community only: on a managed installation the mail server is
        // ours, and there is nothing here to point anywhere.
        if ($this->permissions->allows($user, Permission::EditSettings)
            && $this->capabilities->has(Capability::EmailTransportConfigure)) {
            $items[] = [
                'key' => 'email',
                'title' => __('Point ProjectSend at your mail server'),
                'description' => __('Notifications, password resets and share links all arrive by email, so this is worth doing before your first client does.'),
                'href' => route('system-settings.email.edit', absolute: false),
                'done' => false,
            ];
        }

        // Community only, and the example the brief named: a managed
        // installation has no staff accounts of its own to hand out.
        if ($this->permissions->allows($user, Permission::CreateUsers)
            && $this->capabilities->has(Capability::UsersManage)) {
            $items[] = [
                'key' => 'team',
                'title' => __('Add the rest of your team'),
                'description' => __('Staff accounts with roles, so people get exactly the part of this they need and nothing else.'),
                'href' => route('users.create', absolute: false),
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
                'description' => __('Expiring files, cleanups and queued email all depend on it. This screen tells you whether it has run.'),
                'href' => route('system-settings.scheduler.index', absolute: false),
                'done' => false,
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
}
