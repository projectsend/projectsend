<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources\Api;

use App\Models\User;
use App\Modules\Identity\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 *
 * A staff account over the API.
 *
 * Enumerated rather than serialised, for the same reason ClientResource
 * is, only more so: these rows carry the permissions that administer the
 * installation. Never present, regardless of what the model gains later:
 * `password`, `two_factor_secret`, `two_factor_recovery_codes`,
 * `remember_token`. The first three are credentials; the fourth is a
 * bearer credential in its own right, and publishing it would let an API
 * reader impersonate the account in a browser.
 *
 * `two_factor_enabled` is a boolean and stays one — whether an account is
 * protected is an administrative fact worth reporting, but nothing about
 * *how* belongs in a response.
 */
class StaffUserResource extends JsonResource
{
    /** @var array{files: int, folders: int}|null */
    private ?array $content = null;

    /** @var list<int>|null */
    private ?array $assignedClientIds = null;

    /**
     * The richer single-account shape.
     *
     * A named constructor rather than extra __construct parameters:
     * JsonResource::collection() maps the collection through
     * `new static($item, $key)`, so widening the constructor silently
     * breaks every listing with a TypeError on the second argument.
     *
     * @param  array{files: int, folders: int}  $content
     * @param  list<int>  $assignedClientIds
     */
    public static function detailed(User $user, array $content, array $assignedClientIds): self
    {
        $resource = new self($user);
        $resource->content = $content;
        $resource->assignedClientIds = $assignedClientIds;

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->role;

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'active' => $this->active,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            // Nested rather than a bare role_id, so a caller can render a
            // list without a second request. `is_administrator` is the flag
            // that actually matters when reading one of these.
            'role' => $role instanceof Role ? [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->is_system,
                'is_administrator' => $role->is_administrator,
                'client_scoped' => $role->client_scoped,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->assignedClientIds !== null) {
            // Only meaningful for a client-scoped role; empty otherwise,
            // which is what the sync guarantees rather than something a
            // caller has to infer.
            $data['assigned_client_ids'] = $this->assignedClientIds;
        }

        if ($this->content !== null) {
            // What a caller needs in order to answer DELETE's mandatory
            // content-disposition question before asking.
            $data['content'] = $this->content;
        }

        return $data;
    }
}
