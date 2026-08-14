<?php

declare(strict_types=1);

namespace App\Modules\Files\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Shares a folder (and its live subtree) with a client (User) or Group.
 *
 * @property int $id
 * @property int $folder_id
 * @property string $assignable_type
 * @property int $assignable_id
 */
class FolderAssignment extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
}
