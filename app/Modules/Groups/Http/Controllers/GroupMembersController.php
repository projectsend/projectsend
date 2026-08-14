<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GroupMembersController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
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

        $group->members()->syncWithoutDetaching([$client->id]);

        $this->activity->log(Action::GroupMemberAdded, subject: $group, context: ['member' => $client->name]);

        return back();
    }

    public function destroy(Group $group, User $member): RedirectResponse
    {
        $group->members()->detach($member->id);

        $this->activity->log(Action::GroupMemberRemoved, subject: $group, context: ['member' => $member->name]);

        return back();
    }
}
