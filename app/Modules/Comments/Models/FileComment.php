<?php

declare(strict_types=1);

namespace App\Modules\Comments\Models;

use App\Models\User;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Files\Models\File;
use Database\Factories\FileCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One comment on a file. See the create_file_comments_table migration for
 * why client_context_id and approved_at are shaped the way they are.
 *
 * There is deliberately no query scope on this model for "the comments a
 * viewer may see" — that lives in Access\VisibleCommentScope, alone, so
 * there is exactly one place to audit. A scope here would be a second,
 * easier-to-reach answer to the same question, and the easier one always
 * wins by accident.
 *
 * @property int $id
 * @property int $file_id
 * @property int|null $author_id
 * @property int|null $client_context_id
 * @property string|null $guest_name
 * @property string|null $ip_address
 * @property CommentVisibility $visibility
 * @property string $body
 * @property Carbon|null $approved_at
 * @property Carbon|null $edited_at
 * @property Carbon|null $created_at
 * @property-read User|null $author
 * @property-read User|null $clientContext
 * @property-read File $file
 */
class FileComment extends Model
{
    /** @use HasFactory<FileCommentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * Laravel derives a factory's name by stripping the App\Models prefix,
     * which this module-namespaced model does not have — so name it here
     * rather than have the lookup miss.
     */
    protected static function newFactory(): FileCommentFactory
    {
        return FileCommentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'visibility' => CommentVisibility::class,
            'approved_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The client whose conversation this comment belongs to — not its
     * author.
     *
     * Null means the comment is not one client's: a Clients comment with no
     * context is staff addressing everyone on the file, and OnlyMe /
     * StaffOnly / Everyone have no client in them to begin with. A
     * staff-only note is its own audience now, not a null context.
     *
     * @return BelongsTo<User, $this>
     */
    public function clientContext(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_context_id');
    }

    public function isPending(): bool
    {
        return $this->approved_at === null;
    }

    public function isFromGuest(): bool
    {
        return $this->author_id === null;
    }

    /**
     * The name to show. Snapshotted for guests at write time; read live
     * for accounts so a rename is reflected everywhere at once.
     *
     * author_id cascades on delete, so a row that has one always has the
     * account behind it — there is no deleted-author case to snapshot
     * against, unlike the activity log's actor_name.
     */
    public function authorName(): string
    {
        $author = $this->author;

        if ($author !== null) {
            return $author->name;
        }

        return $this->guest_name ?? (string) __('Anonymous');
    }
}
