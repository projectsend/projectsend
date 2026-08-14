<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\DeletedAccountContent;
use App\Modules\Identity\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Deciding what happens to an account's files and folders when the account
 * is deleted — cascade them away, or hand them to somebody still active.
 *
 * Staff and clients are deleted from two different screens, and both asked
 * the same three questions in identical code: who could inherit this, what
 * did the admin choose, and carry it out. Keeping one copy matters more than
 * the line count here, because the rules are about destroying data: a
 * "reassign to an active account other than this one" check that was
 * tightened on one screen and not the other would be a real hole.
 *
 * The work itself belongs to DeletedAccountContent; this is the request-side
 * half that wraps it.
 */
class AccountContentDeletion
{
    public function __construct(
        private readonly DeletedAccountContent $content,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Every other active account, for the reassignment-target picker.
     * $excludeId is omitted on index pages, where one candidate list is
     * shared across every row and each row's own id is filtered out
     * client-side instead.
     *
     * @return array<int, array{id: int, name: string, role: string}>
     */
    public function candidates(?int $excludeId = null): array
    {
        return User::query()
            ->when($excludeId, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->where('active', true)
            ->with('role')
            ->orderBy('name')
            ->get()
            ->map(function (User $user): array {
                $role = $user->role;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->isClient() ? __('Client') : ($role instanceof Role ? $role->name : __('Staff')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * When the account being deleted owns any files/folders, require the
     * admin to choose what happens to them. Returns an empty array when
     * there is nothing to decide, so accounts with no content delete
     * exactly as before.
     *
     * @return array{content_action?: string, reassign_to_id?: int}
     */
    public function validate(Request $request, User $target): array
    {
        $summary = $this->content->summarize($target);

        if ($summary['files'] === 0 && $summary['folders'] === 0) {
            return [];
        }

        return $request->validate([
            'content_action' => ['required', Rule::in(['cascade_delete', 'reassign'])],
            'reassign_to_id' => [
                'required_if:content_action,reassign',
                'integer',
                Rule::exists('users', 'id')->where('active', true),
                Rule::notIn([$target->id]),
            ],
        ]);
    }

    /**
     * @param  array{content_action?: string, reassign_to_id?: int}  $validated
     */
    public function apply(array $validated, User $target, string $name): void
    {
        $action = $validated['content_action'] ?? null;

        if ($action === 'cascade_delete') {
            $result = $this->content->cascadeDelete($target);
            $this->activity->log(Action::AccountContentCascadeDeleted, context: ['name' => $name, ...$result]);

            return;
        }

        if ($action === 'reassign' && isset($validated['reassign_to_id'])) {
            $to = User::findOrFail($validated['reassign_to_id']);
            $result = $this->content->reassignTo($target, $to);
            $this->activity->log(Action::AccountContentReassigned, context: ['name' => $name, 'target' => $to->name, ...$result]);
        }
    }
}
