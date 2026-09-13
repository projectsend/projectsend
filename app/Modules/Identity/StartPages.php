<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Platform\Onboarding\InstallationWelcome;
use App\Modules\Platform\Updates\UpdateWelcome;

/**
 * Where an account lands after signing in, and what it may choose from.
 *
 * The person's own choice wins, then their role's, then the dashboard.
 * Each is used only if the account can actually open that page *now*:
 * permissions change after a choice is saved, and a start page that
 * answers 403 is worse than no start page. So an unreachable choice is
 * skipped rather than obeyed, and the next one down is tried.
 *
 * A waiting greeting beats all of them. The getting-started list and the
 * what's-new page are reached through the dashboard (RedirectToGreeting
 * sits on that route alone), so an administrator who starts somewhere
 * else would otherwise never see either.
 */
class StartPages
{
    public function __construct(
        private readonly InstallationWelcome $installation,
        private readonly UpdateWelcome $update,
    ) {}

    /**
     * The path to send this account to. Relative, for redirect()->intended().
     */
    public function pathFor(User $user): string
    {
        $dashboard = route('dashboard', absolute: false);

        if ($this->installation->isWaitingFor($user) || $this->update->isWaitingFor($user)) {
            return $dashboard;
        }

        $page = $this->resolve($user);

        return $page === null ? $dashboard : route($page->routeName($user->type), absolute: false);
    }

    /**
     * The start page in force for this account, or null for the dashboard.
     */
    public function resolve(User $user): ?StartPage
    {
        foreach ([$user->start_page, $user->role?->start_page] as $value) {
            $page = is_string($value) ? StartPage::tryFrom($value) : null;

            if ($page !== null && $page->isReachableBy($user)) {
                return $page;
            }
        }

        return null;
    }

    /**
     * The role's default as it applies to this account: null when the role
     * names none, or names one this account cannot open.
     */
    public function roleDefault(User $user): ?StartPage
    {
        $value = $user->role?->start_page;
        $page = is_string($value) ? StartPage::tryFrom($value) : null;

        return $page !== null && $page->isReachableBy($user) ? $page : null;
    }

    /**
     * What a person may pick for themselves: the pages they can open.
     *
     * @return list<array{value: string, label: string}>
     */
    public function personalOptions(User $user): array
    {
        return array_values(array_map(
            fn (StartPage $page): array => ['value' => $page->value, 'label' => (string) __($page->label($user->type))],
            array_filter(StartPage::optionsFor($user->type), fn (StartPage $page): bool => $page->isReachableBy($user)),
        ));
    }

    /**
     * What a role may name as its default. Every page its kind of account
     * can have; RolesController checks the choice against the permissions
     * saved with it.
     *
     * @return list<array{value: string, label: string, permission: string|null}>
     */
    public function roleOptions(UserType $type): array
    {
        return array_map(
            fn (StartPage $page): array => [
                'value' => $page->value,
                'label' => (string) __($page->label($type)),
                'permission' => $page->requiredPermission($type)?->value,
            ],
            StartPage::optionsFor($type),
        );
    }

    /**
     * The kind of account a role is for. The Client system role holds
     * clients; every other role, built-in or custom, holds staff.
     */
    public static function typeOf(Role $role): UserType
    {
        return $role->name === Permissions\SystemRole::Client->value && $role->is_system
            ? UserType::Client
            : UserType::Staff;
    }
}
