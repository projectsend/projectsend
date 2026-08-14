<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's explicit choice for one dashboard widget's visibility and
 * position. An absent row for a given (user, widget_key) pair means "use
 * the documented default layout" — see
 * DashboardWidgetPreferences::layoutFor().
 *
 * @property int $id
 * @property int $user_id
 * @property string $widget_key
 * @property bool $enabled
 * @property int $column_index
 * @property int $position
 */
class DashboardWidgetPreference extends Model
{
    protected $table = 'dashboard_widget_preferences';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'bool',
            'column_index' => 'integer',
            'position' => 'integer',
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
