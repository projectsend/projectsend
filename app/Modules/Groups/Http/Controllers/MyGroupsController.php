<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Groups\Notifications\GroupMembershipRequestedNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The client-facing groups page: see your memberships and — when the
 * installation allows it (clients_can_select_group) — request to join
 * more, just like at registration. Clients only.
 */
class MyGroupsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): Response
    {
        $client = $request->user();
        abort_unless($client !== null && $client->isClient(), 404);

        // Only PUBLIC groups are ever shown to clients — private groups
        // are the staff's internal organization and must not leak here,
        // not even the client's own membership in one.
        $memberGroupIds = $client->memberOfGroups()->pluck('groups.id');
        $requests = MembershipRequest::query()->where('user_id', $client->id)->get();
        $pendingGroupIds = $requests->where('status', MembershipRequest::STATUS_PENDING)->pluck('group_id');
        $deniedGroupIds = $requests
            ->filter(fn (MembershipRequest $request): bool => $this->inDenyCooldown($request))
            ->pluck('group_id');

        return Inertia::render('portal/my-groups', [
            'memberships' => $client->memberOfGroups()->where('public', true)->orderBy('name')->get()
                ->map(fn (Group $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                ])->values()->all(),
            'available' => $this->requestableGroups()
                ->reject(fn (Group $group): bool => $memberGroupIds->contains($group->id))
                ->map(fn (Group $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'requested' => $pendingGroupIds->contains($group->id),
                    'denied' => $deniedGroupIds->contains($group->id),
                ])->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = $request->user();
        abort_unless($client !== null && $client->isClient(), 404);

        $validated = $request->validate([
            'group_id' => ['required', 'integer', Rule::in($this->requestableGroups()->pluck('id')->all())],
        ]);

        $groupId = (int) $validated['group_id'];

        $alreadyMember = $client->memberOfGroups()->where('groups.id', $groupId)->exists();

        if ($alreadyMember) {
            return back();
        }

        $existing = MembershipRequest::query()
            ->where('user_id', $client->id)
            ->where('group_id', $groupId)
            ->first();

        if ($existing !== null && $existing->status === MembershipRequest::STATUS_PENDING) {
            return back();
        }

        if ($existing !== null && $this->inDenyCooldown($existing)) {
            throw ValidationException::withMessages([
                'group_id' => __('Your previous request to this group was declined. You can request again later.'),
            ]);
        }

        // Past-cooldown denial: the same row goes back to pending.
        if ($existing !== null) {
            $existing->forceFill([
                'status' => MembershipRequest::STATUS_PENDING,
                'denied_at' => null,
                'created_at' => now(),
            ])->save();
            $membershipRequest = $existing;
        } else {
            $membershipRequest = MembershipRequest::query()->create([
                'group_id' => $groupId,
                'user_id' => $client->id,
            ]);
        }

        $group = $membershipRequest->group;

        $this->activity->log(Action::GroupMembershipRequested, $client, $group);

        if ($group !== null && $this->settings->get(Setting::EmailNotificationsEnabled) === true) {
            $adminEmails = $this->settings->get(Setting::AdminNotificationEmails);
            foreach (is_array($adminEmails) ? $adminEmails : [] as $email) {
                Notification::route('mail', $email)->notify(
                    new GroupMembershipRequestedNotification($client->name, $group->name)
                );
            }
        }

        return back();
    }

    /**
     * A denied request blocks re-requesting until the configured
     * cooldown has passed (0 = no cooldown).
     */
    private function inDenyCooldown(MembershipRequest $request): bool
    {
        if ($request->status !== MembershipRequest::STATUS_DENIED || $request->denied_at === null) {
            return false;
        }

        $days = (int) $this->settings->get(Setting::ClientsMembershipDenyCooldownDays);

        return $days > 0 && $request->denied_at->addDays($days)->isFuture();
    }

    /**
     * Leave a public group. Restricted to public memberships for the
     * same reason the lists are: a rejection must not reveal whether a
     * private group exists.
     */
    public function leave(Request $request, Group $group): RedirectResponse
    {
        $client = $request->user();
        abort_unless($client !== null && $client->isClient(), 404);

        $isPublicMembership = $group->public
            && $client->memberOfGroups()->where('groups.id', $group->id)->exists();

        abort_unless($isPublicMembership, 404);

        $group->members()->detach($client->id);

        $this->activity->log(Action::GroupMembershipLeft, $client, $group);

        return back();
    }

    /**
     * Requestable from the portal: public groups only, whenever
     * requesting is enabled at all.
     *
     * @return Collection<int, Group>
     */
    private function requestableGroups(): Collection
    {
        return match ($this->settings->get(Setting::ClientsCanSelectGroup)) {
            'public' => Group::query()->where('public', true)->orderBy('name')->get(),
            default => Group::query()->whereRaw('1 = 0')->get(),
        };
    }
}
