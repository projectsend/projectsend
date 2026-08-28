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
     * The account that wrote this comment, deleted or not.
     *
     * `author_id` is cascadeOnDelete and the cascade never fires, because
     * a user is soft-deleted: the row behind a deleted commenter is still
     * there and the column still points at it. Handing back null for one
     * left every caller to invent a meaning for the absence, and they
     * invented different ones — the author type became "guest" on two
     * screens and "client" in the API, while the name beside it stayed
     * correct, and the author filter and the name search stopped matching
     * the comment at all.
     *
     * Whether a comment is from a guest is decided by `author_id` alone.
     * isFromGuest() and authorName() already say so; this makes the
     * relation agree with them.
     *
     * Nothing that decides who may *read* a comment goes through here —
     * VisibleCommentScope and FileCommentPolicy both compare `author_id`
     * directly — so this widens no visibility.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
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
     * A deleted account is still read. author_id cascades on delete, but
     * a user is soft-deleted and the cascade never fires, so the row
     * behind a deleted commenter is still there — and reading it through
     * the plain relation returned null, which sent a named client's
     * comment out as "Anonymous". That is what a guest comment looks
     * like, and a guest comment is governed by different rules; the two
     * must not be able to look the same. Whether the author is a guest is
     * decided by author_id alone, which is also what isFromGuest() asks.
     */
    public function authorName(): string
    {
        if ($this->author_id === null) {
            return $this->guest_name ?? (string) __('Anonymous');
        }

        $author = $this->author;

        if ($author !== null) {
            return $author->name;
        }

        // Trashed: the row is still there, the relation simply will not
        // hand it over. Nothing comes back only once the grace-period
        // erasure has removed the row for real.
        $name = $this->author()->withTrashed()->value('name');

        return is_string($name) ? $name : (string) __('Anonymous');
    }
}
