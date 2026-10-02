<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Resources\Api;

use App\Modules\Files\Access\ClientIdentityScope;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Folder
 *
 * Every field is listed explicitly, never $folder->toArray(), for the same
 * reason as FileResource: the next migration must not publish itself.
 *
 * `ancestors` and `path` come from FolderTrails, loaded by the controller
 * for a whole page at once, and are trimmed to the folders the caller may
 * see. The assignment list is narrowed per entry by ClientIdentityScope,
 * exactly as FileResource narrows a file's.
 */
class FolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $identity = app(ClientIdentityScope::class);
        $groupMorph = (new Group)->getMorphClass();

        $ancestors = $this->ancestors();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            // The folders above this one, root first, as far up as the
            // caller may see. Empty for a folder at the top of the library.
            'ancestors' => $ancestors,
            // The same trail as one string, this folder included:
            // "Clients / Acme / 2026". For display; match on ids, since a
            // folder name may itself contain " / ".
            'path' => implode(' / ', [...array_column($ancestors, 'name'), $this->name]),
            // Read-only here. Making a folder public publishes everything
            // inside it, and is done on the web.
            'public' => (bool) $this->public,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'assignments' => $this->whenLoaded('assignments', fn (): array => $this->assignments
                ->filter(fn (FolderAssignment $assignment): bool => $assignment->assignable_type === $groupMorph
                    ? $identity->permitsGroupId($viewer, (int) $assignment->assignable_id)
                    : $identity->permitsClientId($viewer, (int) $assignment->assignable_id))
                ->map(fn (FolderAssignment $assignment): array => [
                    'type' => $assignment->assignable_type === $groupMorph ? 'group' : 'client',
                    'id' => $assignment->assignable_id,
                    'name' => $assignment->assignable?->getAttribute('name'),
                ])
                ->values()
                ->all()),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function ancestors(): array
    {
        if (! $this->resource->relationLoaded('trail')) {
            return [];
        }

        /** @var list<array{id: int, name: string}> $trail */
        $trail = $this->resource->getRelation('trail')->all();

        return $trail;
    }
}
