<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers\Concerns;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Turning a {type, id} request into the client or group it names, and
 * refusing the ones the caller may not reach.
 *
 * Sharing a file and sharing a folder are the same conversation with a
 * different subject, and the two controllers held identical copies of this.
 *
 * Requires a readonly StaffLibraryScope $scope on the using class.
 */
trait ResolvesShareTargets
{
    /**
     * Validate the request, resolve the named target, and check the caller
     * may share with it — in that order, since the guard needs the resolved
     * model.
     *
     * @param  string  $rejection  the message shown when the id names
     *                             something that is not a client or a group;
     *                             worded per subject ("Files can only be
     *                             assigned to…" / "Folders can only be
     *                             shared with…")
     * @return array{0: User|Group, 1: string} the target and its display name
     */
    private function resolveRequestedTarget(Request $request, string $rejection): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['client', 'group'])],
            'id' => ['required', 'integer'],
        ]);

        [$assignable, $targetName] = $this->resolveTarget($validated['type'], (int) $validated['id'], $rejection);
        $this->guardAssignable($request->user(), $assignable);

        return [$assignable, $targetName];
    }

    /**
     * A revision has no recipients of its own — it inherits the ones set on
     * the original it revises (File::sharingOwnerId), so writing an
     * assignment row here would create a row every visibility query ignores
     * and leave the two screens disagreeing about who has access.
     *
     * Refused rather than silently redirected onto the root: assigning the
     * root is a bigger act than the caller asked for, since it reaches every
     * other revision in the chain too. The message names the file to go to.
     */
    private function guardFileOwnsItsSharing(File $file): void
    {
        if (! $file->isRevision()) {
            return;
        }

        $root = File::query()->find($file->sharingOwnerId());

        throw ValidationException::withMessages([
            'id' => $root === null
                ? __('This file is a new version of another file, and is shared with the same people as that one.')
                : __('This file is a new version of ":name". Manage sharing on ":name" instead — every version follows it.', ['name' => $root->name]),
        ]);
    }

    /**
     * Who actually hears about a share: a group stands for its members.
     *
     * @return iterable<User>
     */
    private function shareRecipients(User|Group $assignable): iterable
    {
        return $assignable instanceof Group ? $assignable->members : [$assignable];
    }

    /**
     * The value to write to, and match against, an assignment's
     * assignable_type.
     *
     * getMorphClass() rather than ::class so that reads and writes cannot
     * disagree: ShareTargets reads these rows back by morph class, and a
     * morph map would make the two spellings different strings.
     */
    private function assignableType(User|Group $assignable): string
    {
        return $assignable->getMorphClass();
    }

    /**
     * A client-scoped staff member may only share with their own clients (or
     * a group one of them belongs to). Applied when revoking too, so that
     * reaching a subject through one of your own clients does not let you
     * revoke somebody else's access to it.
     */
    private function guardAssignable(?User $user, User|Group $assignable): void
    {
        if ($user === null) {
            return;
        }

        $allowed = $assignable instanceof Group
            ? $this->scope->canAssignGroup($user, $assignable)
            : $this->scope->canAssignClient($user, $assignable);

        if (! $allowed) {
            throw ValidationException::withMessages(['id' => __('You can only share with the clients assigned to you.')]);
        }
    }

    /**
     * @return array{0: User|Group, 1: string}
     */
    private function resolveTarget(string $type, int $id, string $rejection): array
    {
        if ($type === 'client') {
            $client = User::query()->find($id);

            if ($client === null || ! $client->isClient()) {
                throw ValidationException::withMessages(['id' => $rejection]);
            }

            return [$client, $client->name];
        }

        $group = Group::query()->find($id);

        if ($group === null) {
            throw ValidationException::withMessages(['id' => $rejection]);
        }

        return [$group, $group->name];
    }
}
