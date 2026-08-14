<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's explicit choice for one notification type's email companion.
 * An absent row for a given (user, type) pair means "use the type's own
 * NotificationTypeDefinition::$defaultEmailEnabled" — see
 * NotificationPreferences::emailEnabledFor().
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property bool $email_enabled
 */
class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'bool',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
