<?php

declare(strict_types=1);

namespace App\Modules\Identity\Erasure;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\DeletedAccountContent;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Permanent, GDPR-grade account erasure: removes the account row
 * entirely and anonymizes the person's identifying snapshots in the
 * activity log. Non-personal facts (actions, timestamps, account type)
 * are kept for security statistics.
 */
class AccountEraser
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Settings $settings,
        private readonly DeletedAccountContent $content,
    ) {}

    public function erase(User $user): void
    {
        DB::transaction(function () use ($user): void {
            // Decide what happens to the files and folders this account
            // owned *before* the row goes: an admin deleting an account is
            // asked per account, but this runs unattended from cron, so it
            // follows the configured default rather than leaving the content
            // ownerless (the FKs' nullOnDelete would otherwise orphan it).
            $this->handleContent($user);

            // Anonymize snapshots: entries stay, names go. The actor_id
            // FK nulls itself on forceDelete; actor_type remains so the
            // log can still say "a deleted staff account".
            ActivityLog::query()
                ->where('actor_id', $user->id)
                ->update(['actor_name' => null]);

            ActivityLog::query()
                ->where('subject_type', $user->getMorphClass())
                ->where('subject_id', $user->id)
                ->update(['subject_name' => null]);

            // Entries that carry the name in context, not in the FK-backed
            // snapshot columns: the self-deletion (UserDeleted) and denial
            // records, plus the content-handling records this very erasure
            // just wrote and any left by an earlier admin deletion of the
            // same person. Their :target — a still-active inheritor — is a
            // different account and is deliberately left alone.
            ActivityLog::query()
                ->whereIn('action', [
                    Action::UserDeleted->value,
                    Action::ClientDenied->value,
                    Action::AccountContentCascadeDeleted->value,
                    Action::AccountContentReassigned->value,
                ])
                ->get()
                ->filter(fn (ActivityLog $entry): bool => ($entry->context['name'] ?? null) === $user->name)
                ->each(function (ActivityLog $entry): void {
                    $context = $entry->context;
                    $context['name'] = null;
                    $entry->forceFill(['context' => $context])->save();
                });

            $type = $user->type->value;

            $user->forceDelete();

            $this->activity->logSystem(Action::AccountErased, ['type' => $type]);
        });
    }

    /**
     * Reassign the account's content to the configured fallback, or cascade
     * it away. Reassign needs a fallback that still exists, is active, and is
     * not the account being erased; when it does not (never chosen, since
     * deactivated, deleted), the safe choice is to cascade rather than orphan
     * — so content is never left ownerless whatever the configuration.
     */
    private function handleContent(User $user): void
    {
        if ($this->settings->get(Setting::AccountErasureContentAction) === 'reassign') {
            $fallback = $this->fallbackFor($user);

            if ($fallback !== null) {
                $result = $this->content->reassignTo($user, $fallback);
                $this->activity->logSystem(Action::AccountContentReassigned, ['name' => $user->name, 'target' => $fallback->name, ...$result]);

                return;
            }
        }

        $result = $this->content->cascadeDelete($user);
        $this->activity->logSystem(Action::AccountContentCascadeDeleted, ['name' => $user->name, ...$result]);
    }

    private function fallbackFor(User $user): ?User
    {
        $id = (int) $this->settings->get(Setting::AccountErasureReassignTo);

        if ($id === 0) {
            return null;
        }

        return User::query()
            ->where('active', true)
            ->whereKeyNot($user->id)
            ->find($id);
    }
}
