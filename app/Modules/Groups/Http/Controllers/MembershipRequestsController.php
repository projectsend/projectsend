<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Groups\Notifications\GroupMembershipDeniedNotification;
use App\Modules\Notifications\Notifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The staff queue for group membership requests: approve joins the
 * client to the group, deny discards the request. Both survive in the
 * activity log.
 */
class MembershipRequestsController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Settings $settings,
        private readonly Notifier $notifier,
        private readonly StaffLibraryScope $scope,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $filters = ['search' => $validated['search'] ?? null];

        $viewer = $request->user();
        assert($viewer !== null);

        $requests = MembershipRequest::query()
            ->pending()
            // A request whose client or group vanished is dead weight; excluding
            // it in SQL (not after fetching) keeps pagination counts honest.
            ->whereHas('user')
            ->whereHas('group')
            // Narrowed the way the buttons on each row now are — see
            // MembershipRequest::scopeApprovableBy, which the sidebar badge
            // reads too so the number and this screen agree.
            ->approvableBy($viewer)
            ->with(['group', 'user'])
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                ->whereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('group', fn (Builder $g) => $g->where('name', 'like', "%{$search}%"))))
            ->orderBy('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (MembershipRequest $membershipRequest): array => [
                'id' => $membershipRequest->id,
                'client_name' => $membershipRequest->user?->name,
                'client_email' => $membershipRequest->user?->email,
                'group_name' => $membershipRequest->group?->name,
                'created_at' => $membershipRequest->created_at?->toIso8601String(),
            ]);

        return Inertia::render('groups/membership-requests', [
            'requests' => $requests->items(),
            'pagination' => Pagination::meta($requests),
            'filters' => $filters,
        ]);
    }

    public function approve(Request $request, MembershipRequest $membershipRequest): RedirectResponse
    {
        $group = $membershipRequest->group;
        $client = $membershipRequest->user;

        abort_unless($group !== null && $client !== null && $membershipRequest->status === MembershipRequest::STATUS_PENDING, 404);

        $this->guardRequest($request, $group, $client);

        $group->members()->syncWithoutDetaching([$client->id]);
        $membershipRequest->delete();

        $this->activity->log(Action::GroupMembershipApproved, subject: $group, context: ['member' => $client->name]);

        $this->notifier->send('group.membership_approved', [$client], subject: $group, data: ['groupName' => $group->name]);

        return back()->with('success', __('Membership request approved.'));
    }

    public function deny(Request $request, MembershipRequest $membershipRequest): RedirectResponse
    {
        $group = $membershipRequest->group;
        $client = $membershipRequest->user;

        if ($group !== null && $client !== null) {
            $this->guardRequest($request, $group, $client);
        }

        // The denied row persists: the client sees the outcome, and it
        // enforces the re-request cooldown.
        $membershipRequest->forceFill([
            'status' => MembershipRequest::STATUS_DENIED,
            'denied_at' => now(),
        ])->save();

        if ($group !== null && $client !== null) {
            $this->activity->log(Action::GroupMembershipDenied, subject: $group, context: ['member' => $client->name]);

            if ($this->settings->get(Setting::EmailNotificationsEnabled) === true) {
                $client->notify(new GroupMembershipDeniedNotification($group->name));
            }
        }

        return back()->with('success', __('Membership request denied.'));
    }

    /**
     * Approving a request is GroupMembersController::store by another
     * door: it joins a client to a group, with the same consequence for
     * what that client -- and any staff member holding them -- can reach
     * afterwards. Denying one is a decision about somebody's client, and
     * emails them about it. Both belong inside the same boundary, and
     * `approve_groups_memberships_requests` in front of the route is a
     * permission, not one.
     *
     * 404 rather than 403, matching the guard immediately above it in
     * approve(): a request this staff member may not act on should not
     * be distinguishable from one that is not there.
     */
    private function guardRequest(Request $request, Group $group, User $client): void
    {
        $viewer = $request->user();
        assert($viewer !== null);

        abort_unless($this->scope->allowsGroupMembership($viewer, $group, $client), 404);
    }
}
