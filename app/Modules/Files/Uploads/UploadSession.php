<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use App\Models\User;
use App\Modules\Files\Models\Folder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
 * @property int $staged_bytes
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

    /**
     * Claim room on the temporary volume for a part that is about to
     * arrive, returning false if the session has no room left.
     *
     * The claim is made before the bytes are read, and it is one
     * statement, because neither weaker version holds. Checking the part
     * directory and then writing leaves a gap that every other part
     * currently in flight fits through — and the protocol sends parts in
     * parallel, so the number of them is the caller's choice, not ours.
     * Charging the real size afterwards is the same gap by another name.
     *
     * $replacing is what the part number already holds, since re-sending a
     * part overwrites it rather than adding to it. That is an ordinary
     * resume, not an attack.
     *
     * The ceiling is the size the session declared. A client cannot stage
     * more than it said it was sending, which is the invariant the whole
     * fix rests on: store() has already measured that declaration against
     * the file-size limit and the storage quota, so bounding staged bytes
     * by it puts temporary bytes under the same limits as stored ones.
     */
    public function reserveStaged(int $bytes, int $replacing = 0): bool
    {
        $delta = $bytes - $replacing;

        $query = static::query()->whereKey($this->getKey());

        // Both bounds are rearranged so that the column is never part of a
        // subtraction. `staged_bytes + :delta BETWEEN 0 AND size` reads
        // naturally and is wrong: staged_bytes is BIGINT UNSIGNED, and on
        // MySQL a negative delta makes that expression underflow and raise
        // SQLSTATE 22003 — in the comparison, before any row is chosen, so
        // the bound meant to prevent it is the thing that trips over it.
        // SQLite has no unsigned integers, so the suite cannot see this at
        // all; UploadSessionStagedBytesMysqlTest is what covers it.
        if ($delta >= 0) {
            if ($delta > $this->size) {
                return false;
            }

            $query->where('staged_bytes', '<=', $this->size - $delta);
        } else {
            // Never give back more than is held.
            $query->where('staged_bytes', '>=', -$delta);
        }

        return $query->update(['staged_bytes' => DB::raw(sprintf('staged_bytes + (%d)', $delta))]) === 1;
    }

    /**
     * Replace a reservation with what the part actually weighs.
     *
     * Always called, whatever happened to the part: a body shorter than
     * its Content-Length, a client that hung up mid-transfer, a part
     * refused for being too long and deleted. Whatever is on disk now is
     * the truth, and the difference goes back to the session — otherwise
     * a client's own retries would slowly exhaust their room.
     */
    public function settleStaged(int $reserved, int $actual): void
    {
        if ($reserved === $actual) {
            return;
        }

        $this->reserveStaged($actual, $reserved);
    }
}
