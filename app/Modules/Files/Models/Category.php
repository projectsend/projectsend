<?php

declare(strict_types=1);

namespace App\Modules\Files\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A flat, cross-cutting label for files (folders provide hierarchy). A
 * file can carry several categories. Configuration data — hard-deleted;
 * deleting one only detaches it from files.
 *
 * @property int $id
 * @property string $name
 * @property string $color
 * @property-read int $files_count
 */
class Category extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsToMany<File, $this>
     */
    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class)->withTimestamps();
    }
}
