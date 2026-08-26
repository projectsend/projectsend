<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GroupMembersController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly StaffLibraryScope $scope,
    ) {}

    public function store(Request $request, Group $group): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        /** @var User $client */
        $client = User::query()->findOrFail((int) $validated['user_id']);

        // Membership is clients-only — staff never belong to groups.
        if (! $client->isClient()) {
            throw ValidationException::withMessages([
                'user_id' => __('Only clients can be group members.'),
            ]);
        }

        $actor = $request->user();
        assert($actor instanceof User);

        // Membership is a library boundary, not just a list: joining a
        // group hands the new member everything shared with it, and if
        // that member is one of the actor's own clients,
        // File::scopeVisibleToClient hands the same content back to the
        // actor. `edit_groups` in front of the route is a permission,
        // not a boundary. See StaffLibraryScope::allowsGroupMembership.
        abort_unless($this->scope->allowsGroupMembership($actor, $group, $client), 403);

        $group->members()->syncWithoutDetaching([$client->id]);

        $this->activity->log(Action::GroupMemberAdded, subject: $group, context: ['member' => $client->name]);

        return back();
    }

    public function destroy(Request $request, Group $group, User $member): RedirectResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);

        // The same boundary as store(): taking somebody out of a group
        // is a decision about their access, and about a group.
        abort_unless($this->scope->allowsGroupMembership($actor, $group, $member), 403);

        $group->members()->detach($member->id);

        $this->activity->log(Action::GroupMemberRemoved, subject: $group, context: ['member' => $member->name]);

        return back();
    }
}
