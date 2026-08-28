<?php

declare(strict_types=1);

namespace App\Modules\Files\Models;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Access\SharingIdentity;
use App\Modules\Files\DownloadLimitScope;
use App\Modules\Files\FileDiskCleanup;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Groups\Models\Group;
use App\Support\Concerns\HasUniqueSlug;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $uploaded_by
 * @property int|null $folder_id
 * @property int|null $previous_file_id
 * @property int|null $version_root_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $original_name
 * @property string $path
 * @property string $disk
 * @property string $mime_type
 * @property int $size
 * @property string $checksum
 * @property bool $public
 * @property Carbon|null $expires_at
 * @property int|null $download_limit
 * @property DownloadLimitScope|null $download_limit_scope
 * @property-read int|null $downloads_count
 * @property-read int|null $own_downloads_count
 * @property-read User|null $uploader
 * @property-read File|null $previousVersion
 * @property-read File|null $nextVersion
 */
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    use HasUniqueSlug;
    use SoftDeletes;

    protected $guarded = [];

    /**
     * Laravel derives a factory's name by stripping the App\Models prefix,
     * which this module-namespaced model does not have — so name it here
     * rather than have the lookup miss.
     */
    protected static function newFactory(): FileFactory
    {
        return FileFactory::new();
    }

    protected function casts(): array
    {
        return [
            'public' => 'boolean',
            'commentable' => 'boolean',
            'expires_at' => 'datetime',
            'download_limit' => 'integer',
            // Nullable in PHP but not in the database: the column has a
            // default, which Eloquent does not apply to a model it has
            // not read back. Anything presenting the scope falls back to
            // Total rather than publishing a null for a field that is
            // only ever one of two strings.
            'download_limit_scope' => DownloadLimitScope::class,
        ];
    }

    protected static function slugFallback(): string
    {
        return 'file';
    }

    protected static function booted(): void
    {
        // Soft-deleting a file is the only user-facing "delete" there is —
        // nothing serves a trashed file's bytes (route-model binding
        // already 404s every route for it) and there's no restore
        // feature, so there's no reason to keep them. Fires on any future
        // forceDelete() too; harmless either way.
        static::deleted(function (File $file): void {
            // Before the bytes go: a trashed row still occupies its
            // predecessor's unique previous_file_id slot, so a chain that
            // is not repaired here can never be re-linked. Runs first
            // because it needs the row's own pointers intact.
            app(FileVersions::class)->detachOnDelete($file);

            // The bytes go once the transaction holding this row commits,
            // not alongside the row itself. A cascade — a folder subtree,
            // an account's content — deletes many rows in one transaction,
            // and anything that rolls it back afterwards puts every row
            // back while the bytes are already gone: a loss nothing can
            // undo. Deferred, the worst case is bytes left on disk with a
            // row that is only trashed, and a scan will not offer those:
            // OrphanFileScanner::knownPaths() counts a trashed row's path
            // as claimed, on purpose, so nothing double-adopts a file still
            // inside its erasure grace period. FileDiskCleanup's warning is
            // therefore the only record that it happened.
            //
            // Outside a transaction the callback runs immediately, so
            // deleting one file is unchanged. Nested transactions only fire
            // it at the outermost commit, which is the case this is for.
            $file->getConnection()->afterCommit(
                fn () => app(FileDiskCleanup::class)->delete($file)
            );
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return HasMany<FileAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(FileAssignment::class);
    }

    /**
     * The file this one revises, if any.
     *
     * Branch on this relation, never on the previous_file_id column: the
     * column stays populated after its target is trashed, the relation
     * correctly resolves to null (SoftDeletes' global scope applies here).
     * Every display path must go through it or through
     * Versions\FileVersionLinks, which does.
     *
     * @return BelongsTo<File, $this>
     */
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(File::class, 'previous_file_id');
    }

    /**
     * The file that revises this one. At most one, enforced by the unique
     * index on previous_file_id — that is what keeps a version history a
     * straight line instead of a tree.
     *
     * @return HasOne<File, $this>
     */
    public function nextVersion(): HasOne
    {
        return $this->hasOne(File::class, 'previous_file_id');
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function versionRoot(): BelongsTo
    {
        return $this->belongsTo(File::class, 'version_root_id');
    }

    /**
     * Whether this file is a revision of an earlier one — and therefore
     * owns no recipients of its own (see sharingOwnerId).
     *
     * Deliberately reads the column rather than the relation: a revision
     * whose root was trashed is not thereby promoted to a root, and
     * version_root_id is maintained eagerly on delete (detachOnDelete)
     * precisely so no query has to chase a trashed row to find out.
     */
    public function isRevision(): bool
    {
        return $this->version_root_id !== null;
    }

    /**
     * The file whose file_assignments rows decide who may see this one —
     * itself if it is a root, otherwise the oldest file in its chain.
     *
     * A revision inherits its recipients rather than holding copies, so
     * the two can never disagree about who has access. This is the single
     * definition of that rule; SharingIdentity is its SQL twin.
     */
    public function sharingOwnerId(): int
    {
        return $this->version_root_id ?? $this->id;
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    /**
     * @return MorphMany<ShareLink, $this>
     */
    public function shareLinks(): MorphMany
    {
        return $this->morphMany(ShareLink::class, 'shareable');
    }

    /**
     * Every logged download of this file — direct, via a public share
     * link, or via the public group listing — matching the "Downloads"
     * tab in the details panel (FileDetailsController::downloads()) —
     * for use with withCount().
     *
     * @return MorphMany<ActivityLog, $this>
     */
    public function downloads(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')
            ->whereIn('action', [Action::FileDownloaded, Action::ShareLinkDownloaded, Action::PublicFileDownloaded]);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->uploaded_by === $user->id;
    }

    /**
     * A file's own expiration date — independent of any share link's.
     * Null means never expires. Once past, the file is hidden from
     * clients and the public site (see scopeNotExpired) and staff keep
     * full access to view, download, and manage it — with one boundary
     * this used to leave out.
     *
     * A client-scoped staff member's library is their own uploads ∪ what
     * each assigned client may see (StaffLibraryScope::buildFiles), and
     * that second half is scopeVisibleToClient, which ends in
     * notExpired(). So an expired file they held only through a client
     * leaves their library too, while their own expired upload stays.
     * That is deliberate: c8078f65 weighed widening it and left the
     * boundary where it is, because scopeVisibleToClient is the single
     * source of truth for client file access, and relabelled the
     * expired-files widget instead. ExpiredFileStaffAccessTest pins both
     * halves so the sentence above cannot drift from the code again.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @param  Builder<File>  $query
     */
    public function scopeNotExpired(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Whether a cap has been set on how many times this may be
     * downloaded. Unlike expiry, reaching it does not hide the file:
     * it stays listed and stops being downloadable, so the recipient can
     * see it existed and ask for more. The counting and the "may this
     * person take it" question both live in DownloadAllowance, because
     * the answer depends on who is asking.
     */
    public function hasDownloadLimit(): bool
    {
        return $this->download_limit !== null && $this->download_limit > 0;
    }

    /**
     * The inverse of scopeNotExpired() above — files currently past their
     * own expiry, regardless of any retention grace period (see
     * PurgeExpiredFilesCommand, which applies the grace period on top of
     * this same expires_at column separately).
     *
     * @param  Builder<File>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /**
     * True when this file's own `public` flag is set, or it sits
     * anywhere in a public folder's live subtree — the single check
     * everywhere a file's real public status matters (public downloads,
     * the file editor's inherited/grayed-out checkbox state).
     */
    public function isEffectivelyPublic(): bool
    {
        return $this->public || ($this->folder?->isEffectivelyPublic() ?? false);
    }

    /**
     * A client can access a file that is assigned to them directly or
     * via a group, that sits in a folder shared with them (self or
     * ancestor), or that they uploaded themselves via the portal — the
     * single source of truth for client file access.
     *
     * The assignment half matches on SharingIdentity::column() rather than
     * on files.id, because a revision owns no assignment rows: it inherits
     * the recipients of its version chain's root. Folder placement, expiry
     * and the public flag stay per-file, so only this one branch changes.
     *
     * @param  Builder<File>  $query
     */
    public function scopeVisibleToClient(Builder $query, User $client): void
    {
        /** @var list<int> $groupIds */
        $groupIds = $client->memberOfGroups()->pluck('groups.id')->all();

        $query->where(function (Builder $outer) use ($client, $groupIds): void {
            // Directly assigned, or via a group — on this file or on the
            // original it revises.
            $outer->whereIn(
                SharingIdentity::column(),
                SharingIdentity::assignedToClient($client, $groupIds),
            );

            // Or inside a shared folder subtree.
            $visibleFolders = Folder::query()->select('id')->tap(
                fn (Builder $folders) => $folders->getModel()->scopeVisibleToClient($folders, $client)
            );
            $outer->orWhereIn('folder_id', $visibleFolders);

            // Or uploaded by the client themselves via the portal.
            $outer->orWhere('uploaded_by', $client->id);
        });

        $query->notExpired();
    }

    /**
     * Files publicly reachable through a specific (public) group: the
     * file's own `public` flag AND (directly assigned to the group, or
     * inside the subtree of a folder assigned to the group). A
     * group-keyed sibling of scopeVisibleToClient() above — kept
     * separate since that one is client-keyed and already used
     * elsewhere. See PublicGroupsController.
     *
     * Same substitution as scopeVisibleToClient for the assignment branch:
     * a revision inherits the group its original was shared with.
     *
     * @param  Builder<File>  $query
     */
    public function scopePubliclyVisibleForGroup(Builder $query, Group $group): void
    {
        $groupMorph = (new Group)->getMorphClass();

        $query->where('public', true)->where(function (Builder $outer) use ($group, $groupMorph): void {
            $outer->whereIn(
                SharingIdentity::column(),
                SharingIdentity::assignedToGroups([$group->id]),
            );

            $assignedFolderIds = FolderAssignment::query()
                ->where('assignable_type', $groupMorph)
                ->where('assignable_id', $group->id)
                ->pluck('folder_id');

            $subtreeFolderIds = Folder::query()
                ->whereIn('id', $assignedFolderIds)
                ->get()
                ->flatMap(fn (Folder $folder): array => $folder->subtreeFolderIds())
                ->unique()
                ->values();

            $outer->orWhereIn('folder_id', $subtreeFolderIds);
        });

        $query->notExpired();
    }

    /**
     * Files publicly reachable through a specific public folder: every
     * file in its live subtree, regardless of the file's own `public`
     * flag — the whole point of a public folder is that it makes
     * everything inside it public. A folder-keyed sibling of
     * scopePubliclyVisibleForGroup() above. See PublicFoldersController.
     *
     * @param  Builder<File>  $query
     */
    public function scopePubliclyVisibleForFolder(Builder $query, Folder $folder): void
    {
        $query->whereIn('folder_id', $folder->subtreeFolderIds())->notExpired();
    }

    /**
     * Public files not reachable through any public group or public
     * folder — the public listing's "front page" entries, so a file is
     * never shown twice (once standalone, once via its group's or
     * folder's own page). A file assigned only to a private group still
     * appears here: the file's own `public` flag is independent of any
     * group's or folder's.
     *
     * @param  Builder<File>  $query
     */
    public function scopeStandalonePublic(Builder $query): void
    {
        $groupMorph = (new Group)->getMorphClass();
        $publicGroupIds = Group::query()->where('public', true)->pluck('id');

        $publicGroupFolderIds = FolderAssignment::query()
            ->where('assignable_type', $groupMorph)
            ->whereIn('assignable_id', $publicGroupIds)
            ->pluck('folder_id');

        $publicGroupSubtreeFolderIds = Folder::query()
            ->whereIn('id', $publicGroupFolderIds)
            ->get()
            ->flatMap(fn (Folder $folder): array => $folder->subtreeFolderIds())
            ->unique()
            ->values();

        $publicFolderSubtreeIds = Folder::query()
            ->where('public', true)
            ->get()
            ->flatMap(fn (Folder $folder): array => $folder->subtreeFolderIds())
            ->unique()
            ->values();

        $query->where('public', true)
            // Reached through the chain root, like the two scopes above: a
            // revision of a file that belongs to a public group is reachable
            // via that group's own page, so it is not standalone either.
            ->whereNotIn(
                SharingIdentity::column(),
                SharingIdentity::assignedToGroups($publicGroupIds),
            )
            ->where(function (Builder $folder) use ($publicGroupSubtreeFolderIds): void {
                $folder->whereNull('folder_id')->orWhereNotIn('folder_id', $publicGroupSubtreeFolderIds);
            })
            ->where(function (Builder $folder) use ($publicFolderSubtreeIds): void {
                $folder->whereNull('folder_id')->orWhereNotIn('folder_id', $publicFolderSubtreeIds);
            })
            ->notExpired();
    }
}
