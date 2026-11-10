<?php

declare(strict_types=1);

namespace App\Modules\Files\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracks one queued zip-archive build (BuildZipDownloadJob). Disposable —
 * see PurgeZipDownloadsCommand, which removes old rows and their files.
 *
 * @property int $id
 * @property int|null $requested_by  null for a visitor on a public page; see public_scope_type
 * @property string|null $public_scope_type  'index', 'group' or 'folder' for a visitor's zip, else null
 * @property int|null $public_scope_id  the group's or folder's id for a visitor's zip
 * @property string $status
 * @property string|null $path
 * @property int $file_count
 * @property int|null $total_size
 * @property string|null $error
 * @property list<int> $file_ids
 * @property list<int> $folder_ids
 * @property list<int>|null $contained_file_ids
 * @property list<array{id: int, name: string}>|null $skipped_files
 * @property Carbon|null $delivered_at
 * @property Carbon|null $started_at
 */
class ZipDownload extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const SCOPE_INDEX = 'index';

    public const SCOPE_GROUP = 'group';

    public const SCOPE_FOLDER = 'folder';

    /** Whether a visitor on a public page asked for this, rather than a signed-in user. */
    public function isPublic(): bool
    {
        return $this->public_scope_type !== null;
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'file_ids' => 'array',
            'folder_ids' => 'array',
            'contained_file_ids' => 'array',
            'skipped_files' => 'array',
            'delivered_at' => 'datetime',
            'started_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
