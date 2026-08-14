<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string|null $actor_name
 * @property string|null $actor_type
 * @property ActivityOrigin $origin
 * @property int|null $api_token_id
 * @property string|null $api_token_name
 * @property string|null $ip_address
 * @property Action $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_name
 * @property array<string, mixed>|null $context
 * @property Carbon $created_at
 */
class ActivityLog extends Model
{
    protected $table = 'activity_log';

    // Log entries are immutable: created_at only, written by the logger.
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action' => Action::class,
            'origin' => ActivityOrigin::class,
            'context' => 'json',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
