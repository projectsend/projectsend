<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\UserType;
use App\Support\Pagination;
use App\Support\PublicUrl;
use App\Support\Rules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GroupsController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly PublicUrl $publicUrl,
        private readonly StaffLibraryScope $scope,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
        ]);

        $filters = [
            'search' => $validated['search'] ?? null,
            'visibility' => $validated['visibility'] ?? null,
        ];

        $groups = Group::query()
            ->withCount('members')
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($filters['visibility'], fn (Builder $query, string $visibility) => $query->where('public', $visibility === 'public'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'public' => $group->public,
                'members_count' => $group->members_count,
                'public_url' => $group->public
                    ? $this->publicUrl->for($group)
                    : null,
            ]);

        return Inertia::render('groups/index', [
            'groups' => $groups->items(),
            'pagination' => Pagination::meta($groups),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('groups/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // The slug only matters (and is only shown) once a group is
            // public — otherwise fall back to one derived from the name.
            'slug' => Rules::slug('groups'),
            'description' => ['nullable', 'string', 'max:2000'],
            'public' => ['required', 'boolean'],
        ]);

        $validated['slug'] = $validated['slug'] ?? '' ?: Group::uniqueSlugFrom($validated['name']);

        $group = Group::query()->create($validated);

        $this->activity->log(Action::GroupCreated, subject: $group);

        if ($group->public) {
            $this->activity->log(Action::GroupMadePublic, subject: $group, context: ['slug' => $group->slug]);
        }

        return redirect()->route('groups.edit', $group)->with('success', __('Group created.'));
    }

    public function edit(Group $group): Response
    {
        return Inertia::render('groups/edit', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
                'public' => $group->public,
            ],
            'members' => $group->members()->orderBy('name')->get()
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ])->all(),
            'available_clients' => User::query()
                ->where('type', UserType::Client)
                ->whereNotIn('id', $group->members()->pluck('users.id'))
                ->orderBy('name')
                ->get()
                ->map(fn (User $client): array => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                ])->all(),
        ]);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);

        // A group whose reach extends past this staff member's library is
        // not theirs to change. #1701 drew this line for membership; the
        // object itself needs it for the same reason and more sharply —
        // an assignment to a group is how its members reach a file, so
        // deleting one revokes that access for every member, including
        // clients outside this person's roster. Measured before this
        // guard: a scoped role deleted a stranger's group and the
        // stranger's client stopped seeing the file it carried.
        abort_unless($this->scope->allowsGroupChange($viewer, $group), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // The slug only matters (and is only shown) once a group is
            // public — otherwise fall back to one derived from the name.
            'slug' => Rules::slug('groups', $group->id),
            'description' => ['nullable', 'string', 'max:2000'],
            'public' => ['required', 'boolean'],
        ]);

        // Omitting the field on an update leaves the current slug alone —
        // it must not silently change just because the name did.
        $validated['slug'] = ($validated['slug'] ?? '') ?: ($group->slug ?: Group::uniqueSlugFrom($validated['name'], $group->id));

        $wasPublic = $group->public;

        $group->update($validated);

        $this->activity->log(Action::GroupUpdated, subject: $group);

        if (! $wasPublic && $group->public) {
            $this->activity->log(Action::GroupMadePublic, subject: $group, context: ['slug' => $group->slug]);
        } elseif ($wasPublic && ! $group->public) {
            $this->activity->log(Action::GroupMadePrivate, subject: $group);
        }

        return back()->with('success', __('Group updated.'));
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        $viewer = $request->user();
        assert($viewer !== null);

        // A group whose reach extends past this staff member's library is
        // not theirs to change. #1701 drew this line for membership; the
        // object itself needs it for the same reason and more sharply —
        // an assignment to a group is how its members reach a file, so
        // deleting one revokes that access for every member, including
        // clients outside this person's roster. Measured before this
        // guard: a scoped role deleted a stranger's group and the
        // stranger's client stopped seeing the file it carried.
        abort_unless($this->scope->allowsGroupChange($viewer, $group), 404);

        $name = $group->name;
        $group->delete();

        $this->activity->log(Action::GroupDeleted, context: ['name' => $name]);

        return redirect()->route('groups.index')->with('success', __('Group deleted.'));
    }
}
