<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
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
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $filters = ['search' => $validated['search'] ?? null];

        $requests = MembershipRequest::query()
            ->pending()
            // A request whose client or group vanished is dead weight; excluding
            // it in SQL (not after fetching) keeps pagination counts honest.
            ->whereHas('user')
            ->whereHas('group')
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

    public function approve(MembershipRequest $membershipRequest): RedirectResponse
    {
        $group = $membershipRequest->group;
        $client = $membershipRequest->user;

        abort_unless($group !== null && $client !== null && $membershipRequest->status === MembershipRequest::STATUS_PENDING, 404);

        $group->members()->syncWithoutDetaching([$client->id]);
        $membershipRequest->delete();

        $this->activity->log(Action::GroupMembershipApproved, subject: $group, context: ['member' => $client->name]);

        $this->notifier->send('group.membership_approved', [$client], subject: $group, data: ['groupName' => $group->name]);

        return back()->with('success', __('Membership request approved.'));
    }

    public function deny(MembershipRequest $membershipRequest): RedirectResponse
    {
        $group = $membershipRequest->group;
        $client = $membershipRequest->user;

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
}
