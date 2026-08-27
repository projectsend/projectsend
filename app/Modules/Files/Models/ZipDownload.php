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
 * @property int $requested_by
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
