<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Resources\Api;

use App\Models\User;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Clients\Models\ClientCustomField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 *
 * A client is a row in `users`, which is the most sensitive table in the
 * application — so this enumerates its fields rather than serialising the
 * model, and the enumeration is the point.
 *
 * Never present, regardless of what the model gains later: `password`,
 * `two_factor_secret`, `two_factor_recovery_codes`, `remember_token`.
 * The first three are credentials; the fourth is a bearer credential in
 * its own right, and publishing it would let an API reader impersonate the
 * client in a browser.
 *
 * Custom field *values* are included, because they are ordinary client
 * data that any staff member with `edit_clients` already reads on the edit
 * screen — the API must not be a second, quieter privacy boundary. They
 * are attached only on `show`, so a bulk listing does not hand out every
 * client's field data in one call.
 */
class ClientResource extends JsonResource
{
    /** @var array<int, string>|null field id => value */
    private ?array $customFieldValues = null;

    private ?ClientStorageUsage $storage = null;

    /** @var array{files: int, folders: int}|null */
    private ?array $content = null;

    /**
     * The richer single-client shape.
     *
     * A named constructor rather than extra __construct parameters:
     * JsonResource::collection() maps the collection through
     * `new static($item, $key)`, so widening the constructor silently
     * breaks every listing with a TypeError on the second argument.
     *
     * @param  array<int, string>  $customFieldValues  field id => value
     * @param  array{files: int, folders: int}  $content
     */
    public static function detailed(
        User $client,
        array $customFieldValues,
        ClientStorageUsage $storage,
        array $content,
    ): self {
        $resource = new self($client);
        $resource->customFieldValues = $customFieldValues;
        $resource->storage = $storage;
        $resource->content = $content;

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'active' => $this->active,
            'account_requested' => $this->account_requested,
            // Whether, not what: the state of the second factor is what a
            // caller needs to see before removing it. The secret and the
            // recovery codes stay where they are.
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->storage !== null) {
            $quotaMb = $this->storage->quotaMb($this->resource);

            $data['storage'] = [
                // The client's own column, where 0 means "inherit the site
                // default" rather than "unlimited" — both are reported so a
                // caller need not know that rule to display it correctly.
                'quota_mb' => $this->storage_quota_mb,
                'effective_quota_mb' => $quotaMb,
                'unlimited' => $quotaMb === 0,
                'used_mb' => (int) ceil($this->storage->usedBytes($this->resource) / 1024 / 1024),
            ];
        }

        if ($this->customFieldValues !== null) {
            $data['custom_fields'] = ClientCustomField::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (ClientCustomField $field): array => [
                    'id' => $field->id,
                    'name' => $field->name,
                    'label' => $field->label,
                    'type' => $field->type->value,
                    'value' => $this->customFieldValues[$field->id] ?? null,
                ])
                ->all();
        }

        if ($this->content !== null) {
            // What a caller needs in order to answer DELETE's mandatory
            // content-disposition question before asking.
            $data['content'] = $this->content;
        }

        return $data;
    }
}
