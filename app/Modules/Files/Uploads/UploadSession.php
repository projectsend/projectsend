<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use App\Models\User;
use App\Modules\Files\Models\Folder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A resumable chunked upload in progress. Parts live on disk under the
 * session's temp directory — the filesystem is the part ledger.
 *
 * @property string $id
 * @property int $user_id
 * @property int|null $folder_id
 * @property int|null $previous_file_id
 * @property string $original_name
 * @property int $size
 * @property string|null $mime_type
 * @property string|null $description
 * @property string $status
 * @property-read User $user
 */
class UploadSession extends Model
{
    use HasUuids;

    public const STATUS_OPEN = 'open';

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
