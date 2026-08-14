<?php

declare(strict_types=1);

namespace App\Modules\Clients\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One client's answer for one custom field. Cascades away with either
 * side (the field definition or the client).
 *
 * @property int $id
 * @property int $client_custom_field_id
 * @property int $user_id
 * @property string|null $value
 */
class ClientCustomFieldValue extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<ClientCustomField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ClientCustomField::class, 'client_custom_field_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
