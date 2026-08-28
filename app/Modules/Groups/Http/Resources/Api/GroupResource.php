<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Resources\Api;

use App\Models\User;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Group
 *
 * Members carry a name and an email, which is what the group edit screen
 * shows the same viewer. That is a claim about the screen, so it holds
 * only for as long as the screen does: both narrow the list to the
 * clients the viewer may act on, and the controller loading this relation
 * is where that narrowing is applied. They are attached only when
 * explicitly loaded, so a listing of groups does not become a bulk export
 * of every client's address.
 */
class GroupResource extends JsonResource
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
            'public' => $this->public,
            'members_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'members' => $this->whenLoaded('members', fn (): array => $this->members
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ])
                ->all()),
        ];
    }
}
