<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Deliberately not named "Notification" — every existing mail-only
 * notification class in this app (App\Modules\{Files,Groups,Clients,
 * Identity,Platform}\Notifications\*) already extends
 * Illuminate\Notifications\Notification, and the two must never be
 * confused in a `use` statement.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $data
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
class InAppNotification extends Model
{
    protected $table = 'notifications';

    // Immutable except read_at: created_at only, written explicitly by
    // Notifier — same convention as ActivityLog.
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'json',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
