<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Api\Support\PollingQuery;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Groups\Http\Resources\Api\GroupResource;
use App\Modules\Groups\Models\Group;
use App\Support\Rules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Groups — collections of clients used as a sharing target.
 *
 * Distinct from a Category (a label on a file) and a Folder (hierarchy);
 * the three are easy to conflate from outside, which is why the guide
 * names them separately.
 */
class GroupsController extends Controller
{
    public function __construct(
        private readonly PollingQuery $polling,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate($this->polling->rules() + [
            'search' => ['nullable', 'string', 'max:255'],
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
        ]);

        $query = Group::query()->withCount('members');

        if (($filters['search'] ?? null) !== null) {
            $search = $filters['search'];
            $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        if (($filters['visibility'] ?? null) !== null) {
            $query->where('public', $filters['visibility'] === 'public');
        }

        return GroupResource::collection($this->polling->paginate($request, $query, 'groups'));
    }

    public function show(Group $group): GroupResource
    {
        return new GroupResource($group->loadCount('members')->load('members'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => Rules::slug('groups'),
            'description' => ['nullable', 'string', 'max:2000'],
            'public' => ['required', 'boolean'],
        ]);

        $validated['slug'] = ($validated['slug'] ?? '') ?: Group::uniqueSlugFrom($validated['name']);

        $group = Group::query()->create($validated);

        $this->activity->log(Action::GroupCreated, subject: $group);

        if ($group->public) {
            $this->activity->log(Action::GroupMadePublic, subject: $group, context: ['slug' => $group->slug]);
        }

        return (new GroupResource($group->loadCount('members')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Group $group): GroupResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => Rules::slug('groups', $group->id),
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'public' => ['sometimes', 'boolean'],
        ]);

        // A slug must never change silently just because the name did.
        $validated['slug'] = ($validated['slug'] ?? '') ?: ($group->slug ?: Group::uniqueSlugFrom($validated['name'] ?? $group->name, $group->id));

        $wasPublic = $group->public;

        $group->update($validated);

        $this->activity->log(Action::GroupUpdated, subject: $group);

        if (! $wasPublic && $group->public) {
            $this->activity->log(Action::GroupMadePublic, subject: $group, context: ['slug' => $group->slug]);
        } elseif ($wasPublic && ! $group->public) {
            $this->activity->log(Action::GroupMadePrivate, subject: $group);
        }

        return new GroupResource($group->refresh()->loadCount('members'));
    }

    public function destroy(Group $group): JsonResponse
    {
        $name = $group->name;
        $group->delete();

        $this->activity->log(Action::GroupDeleted, context: ['name' => $name]);

        return response()->json(status: 204);
    }
}
