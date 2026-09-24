<?php

declare(strict_types=1);

namespace App\Modules\Identity\Erasure;

use App\Models\User;
use App\Modules\Identity\Erasure\Events\ResolvingSelfDeletion;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;

/**
 * What deleting your own account does to your files.
 *
 * Two rules, and they share one question — whose deletion counts, set by
 * Setting::AccountSelfDeleteScope:
 *
 * 1. **The files stop being served at once.** From the moment the account
 *    is soft-deleted, nobody but staff gets them: not the clients and
 *    groups they were shared with, not a share link, not the public
 *    listing. Somebody who asked to leave should not stay published for
 *    the length of a grace period. Staff keep seeing them, because the
 *    grace period exists so that a mistake can still be undone. Nothing
 *    is deleted by this rule, and share links are kept, so an account
 *    that is restored is served again exactly as before.
 *
 * 2. **Optionally, the files are deleted at once** rather than when the
 *    account is erased (Setting::AccountSelfDeleteFiles, which a platform
 *    can overrule through ResolvingSelfDeletion).
 *
 * In practice rule 1 only ever meets a *self*-deleted account. An
 * administrator deleting an account that owns anything must choose there
 * and then to delete or reassign it (AccountContentDeletion), so no file
 * is left pointing at an account an administrator removed.
 */
class SelfDeletion
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * Whether the two rules apply to this account's own deletion.
     */
    public function appliesTo(User $user): bool
    {
        return $this->clientsOnly() ? $user->isClient() : true;
    }

    public function deletesFilesImmediately(): bool
    {
        return $this->resolve()->filesImmediately;
    }

    /**
     * Whether the platform made the choice, so the settings screen shows
     * it rather than a switch that would change nothing.
     */
    public function isManaged(): bool
    {
        return $this->resolve()->managed;
    }

    /**
     * The ids of every deleted account whose files are withdrawn — the
     * subquery File::scopeNotWithdrawn() and File::isWithdrawn() ask.
     *
     * @return Builder<User>
     */
    public function withdrawnAccounts(): Builder
    {
        return User::onlyTrashed()
            ->select('id')
            ->when($this->clientsOnly(), fn (Builder $query) => $query->where('type', UserType::Client));
    }

    private function clientsOnly(): bool
    {
        return $this->settings->get(Setting::AccountSelfDeleteScope) === 'clients';
    }

    private function resolve(): ResolvingSelfDeletion
    {
        $event = new ResolvingSelfDeletion(
            $this->settings->get(Setting::AccountSelfDeleteFiles) === 'immediately',
        );

        Event::dispatch($event);

        return $event;
    }
}
