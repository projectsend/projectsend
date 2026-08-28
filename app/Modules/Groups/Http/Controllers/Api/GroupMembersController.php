<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Groups\Http\Resources\Api\GroupResource;
use App\Modules\Groups\Models\Group;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GroupMembersController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly StaffLibraryScope $scope,
    ) {}

    public function store(Request $request, Group $group): GroupResource
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $client = User::query()->findOrFail((int) $validated['user_id']);

        // Membership is clients-only — staff never belong to groups. Worth
        // enforcing here as well as on the web: a group is a sharing
        // target, and a staff member inside one would start receiving
        // shares as though they were a customer.
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

        // syncWithoutDetaching, so adding an existing member is a no-op and
        // a retried request is safe.
        $group->members()->syncWithoutDetaching([$client->id]);

        $this->activity->log(Action::GroupMemberAdded, subject: $group, context: ['member' => $client->name]);

        return $this->response($group, $actor);
    }

    public function destroy(Request $request, Group $group, User $member): GroupResource
    {
        $actor = $request->user();
        assert($actor instanceof User);

        // The same boundary as store(): taking somebody out of a group
        // is a decision about their access, and about a group.
        abort_unless($this->scope->allowsGroupMembership($actor, $group, $member), 403);

        $group->members()->detach($member->id);

        $this->activity->log(Action::GroupMemberRemoved, subject: $group, context: ['member' => $member->name]);

        return $this->response($group, $actor);
    }

    /**
     * The group as this actor may see it.
     *
     * GroupResource carries a name and an email per member, and its own
     * docblock puts the boundary here: "the controller loading this
     * relation is where that narrowing is applied". Api\GroupsController
     * ::show() applies it for the read of the same group; changing the
     * membership is not a reason to be told more than reading it, so both
     * halves narrow by the same query.
     *
     * The count is deliberately not narrowed. members_count is the size of
     * the group, which is a fact about the group rather than about who is
     * in it, and the web screen shows the same total.
     */
    private function response(Group $group, User $actor): GroupResource
    {
        return new GroupResource($group->loadCount('members')->load([
            'members' => fn (BelongsToMany $members) => $members
                ->whereIn('users.id', $this->scope->clients($actor)->select('id')),
        ]));
    }
}
