<?php

declare(strict_types=1);

namespace App\Modules\Platform\Scheduling;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row per known scheduled command, upserted by RecordsScheduledTaskRuns
 * on every run — an absent row means "never run yet," same "absent row is
 * the default" convention used by Setting/EmailTemplate/DashboardWidgetPreference.
 *
 * @property int $id
 * @property string $command
 * @property TaskRunStatus $status
 * @property string|null $message
 * @property int|null $duration_ms
 * @property Carbon $ran_at
 */
class ScheduledTaskRun extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => TaskRunStatus::class,
            'ran_at' => 'datetime',
        ];
    }
}
