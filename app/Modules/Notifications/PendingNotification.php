<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One notification awaiting its digest email — see NotificationDigester
 * and Jobs\SendNotificationDigest.
 *
 * Transient by design: a row exists only between being queued and its
 * debounce window elapsing, and the digest deletes it. Its mere existence
 * is the whole state; there is no "sent" flag because a sent row is gone.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $subject_name
 * @property array<string, mixed>|null $context
 */
class PendingNotification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'subject_name',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
