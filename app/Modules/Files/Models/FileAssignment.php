<?php

declare(strict_types=1);

namespace App\Modules\Files\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Links a file to a client (User) or a Group.
 *
 * @property int $id
 * @property int $file_id
 * @property string $assignable_type
 * @property int $assignable_id
 */
class FileAssignment extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
}
