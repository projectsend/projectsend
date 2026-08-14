<?php

declare(strict_types=1);

namespace App\Modules\Clients\Models;

use App\Modules\Clients\ClientCustomFieldType;
use App\Modules\Clients\ClientFieldEditability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A field definition shown on the staff-facing client create/edit
 * screens, and optionally also on the client-facing registration and/or
 * account pages (see `client_editability`/`client_contexts`). Configuration
 * data — hard-deleted; deleting one cascades its per-client values.
 *
 * @property int $id
 * @property string $name
 * @property string $label
 * @property ClientCustomFieldType $type
 * @property list<string>|null $options
 * @property bool $required
 * @property int $sort_order
 * @property ClientFieldEditability $client_editability
 * @property list<string>|null $client_contexts
 */
class ClientCustomField extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ClientCustomFieldType::class,
            'options' => 'array',
            'required' => 'boolean',
            'client_editability' => ClientFieldEditability::class,
            'client_contexts' => 'array',
        ];
    }

    /**
     * @return HasMany<ClientCustomFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ClientCustomFieldValue::class);
    }
}
