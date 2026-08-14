<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Resources\Api;

use App\Modules\Files\DownloadLimitScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin File
 *
 * Every field is listed explicitly. Never $file->toArray() here: that
 * would publish whatever the next migration adds, and two of this model's
 * columns must not leave the server at all —
 *
 *  - `path` and `disk` describe where the bytes physically live. A caller
 *    has no use for them (downloads go through the download endpoint,
 *    which authorizes and then hands off), and publishing them leaks the
 *    storage layout, which is exactly the map you would want before
 *    attempting to reach the bytes another way.
 *  - `checksum` is included deliberately, since verifying an integration's
 *    own download is a real use case, and it reveals nothing about
 *    location.
 */
class FileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'checksum' => $this->checksum,
            'public' => $this->public,
            // Only consulted while the installation's comment setting is
            // "only files marked as commentable"; published anyway, since a
            // caller that sets it wants to read it back.
            'commentable' => $this->commentable,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'expired' => $this->isExpired(),

            // Null when the file may be downloaded any number of times.
            // `download_limit_scope` says what the number counts —
            // "total" across everyone, or "per_user" for each person
            // separately. It is always one of those two, and is
            // meaningless while the limit is null.
            'download_limit' => $this->download_limit,
            'download_limit_scope' => ($this->download_limit_scope ?? DownloadLimitScope::Total)->value,
            // The file's total downloads. Under a per_user limit this is
            // still the total, since "how much has this caller used" is a
            // different number for every caller.
            'downloads_used' => $this->downloads()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // True when this file is a new version of an earlier one. A
            // revision is always shared with the same people as the file it
            // replaces, so it has no recipients of its own: assigning it
            // returns 422, and `sharing_root_id` names the file to assign
            // instead.
            'is_revision' => $this->isRevision(),

            // The oldest file in this version chain — the one whose
            // recipients govern the whole chain. Null when this file is not
            // a revision.
            'sharing_root_id' => $this->version_root_id,

            // The file this one replaces. Null when there is none, or when
            // it is one you cannot see.
            'previous_version' => $this->whenLoaded('previousVersion', fn (): ?array => $this->previousVersion === null ? null : [
                'id' => $this->previousVersion->id,
                'name' => $this->previousVersion->name,
            ]),

            // The file that replaced this one, on the same terms.
            'next_version' => $this->whenLoaded('nextVersion', fn (): ?array => $this->nextVersion === null ? null : [
                'id' => $this->nextVersion->id,
                'name' => $this->nextVersion->name,
            ]),

            'folder' => $this->whenLoaded('folder', fn (): ?array => $this->folder === null ? null : [
                'id' => $this->folder->id,
                'name' => $this->folder->name,
            ]),

            // Name only. The uploader is a user record; their email address
            // is not part of what "this file exists" needs to say.
            'uploaded_by' => $this->whenLoaded('uploader', fn (): ?array => $this->uploader === null ? null : [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),

            'categories' => $this->whenLoaded('categories', fn (): array => $this->categories
                ->map(fn ($category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->all()),

            'assignments' => $this->whenLoaded('assignments', fn (): array => $this->assignments
                ->map(fn (FileAssignment $assignment): array => [
                    'type' => $assignment->assignable_type === Group::class ? 'group' : 'client',
                    'id' => $assignment->assignable_id,
                    // getAttribute() rather than ->name: the relation is a
                    // MorphTo over User|Group, so the property is only
                    // knowable at runtime. Both targets carry a name.
                    'name' => $assignment->assignable?->getAttribute('name'),
                ])
                ->all()),

            'links' => [
                'download' => route('api.files.download', $this->resource),
            ],
        ];
    }
}
