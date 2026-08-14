<?php

declare(strict_types=1);

namespace App\Modules\Files\Models;

use App\Models\User;
use App\Modules\Groups\Models\Group;
use App\Support\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A library folder. Staff see one shared tree; a folder becomes visible
 * to a client either when it — or an ancestor — is explicitly shared
 * (granting live access to the whole subtree), or when the client created
 * it themselves.
 *
 * @property int $id
 * @property string $name
 * @property int|null $parent_id
 * @property int|null $created_by
 * @property string $path
 * @property string $slug
 * @property bool $public
 * @property bool $allow_client_uploads
 */
class Folder extends Model
{
    use HasUniqueSlug;
    use SoftDeletes;

    public const MAX_DEPTH = 10;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'public' => 'boolean',
            'allow_client_uploads' => 'boolean',
        ];
    }

    protected static function slugFallback(): string
    {
        return 'folder';
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    /**
     * @return HasMany<File, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * @return HasMany<FolderAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(FolderAssignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Ancestor ids parsed from the materialized path (nearest first is
     * not guaranteed; order is root→self).
     *
     * @return list<int>
     */
    public function ancestorIds(): array
    {
        return array_values(array_filter(array_map('intval', explode('/', trim($this->path, '/')))));
    }

    public function depth(): int
    {
        return count($this->ancestorIds());
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->created_by === $user->id;
    }

    /**
     * Self or any ancestor is public — the inheritance every file in this
     * folder's subtree relies on (File::isEffectivelyPublic()), and what
     * the file editor shows the user when a file's own public checkbox is
     * grayed out.
     */
    public function isEffectivelyPublic(): bool
    {
        return $this->publicSourceName() !== null;
    }

    /**
     * Self's name if public, else the name of the nearest public ancestor
     * (not necessarily the topmost one), else null. What the file editor
     * names in the message under a grayed-out, inherited-public checkbox.
     */
    public function publicSourceName(): ?string
    {
        if ($this->public) {
            return $this->name;
        }

        $ancestorIds = $this->ancestorIds();

        if ($ancestorIds === []) {
            return null;
        }

        $publicAncestorNames = self::query()->whereIn('id', $ancestorIds)->where('public', true)->pluck('name', 'id');

        foreach (array_reverse($ancestorIds) as $id) {
            if (isset($publicAncestorNames[$id])) {
                return $publicAncestorNames[$id];
            }
        }

        return null;
    }

    /**
     * Whether $user may upload a new file directly into $folder (null =
     * loose at the root, always allowed). Staff already validate folder_id
     * through FilesController's own flow — this is the client-facing
     * check, used by ChunkedUploadsController: the client owns the
     * folder, or it's a public folder that opts into client uploads and
     * the client's role permits uploading into public folders at all.
     */
    public static function uploadableBy(User $user, ?self $folder): bool
    {
        if ($folder === null) {
            return true;
        }

        if ($user->isStaff()) {
            return true;
        }

        return $folder->isOwnedBy($user)
            || ($folder->public && $folder->allow_client_uploads && $user->can('upload_to_public_folders'));
    }

    /**
     * The path prefix matching this folder's whole subtree (self + all
     * descendants share this prefix in their path).
     */
    public function subtreePathPrefix(): string
    {
        return $this->path.$this->id.'/';
    }

    /**
     * This folder's id plus every descendant's — the live subtree, used
     * anywhere "every file inside this folder, recursively" is needed
     * (e.g. zipping a folder).
     *
     * @return list<int>
     */
    public function subtreeFolderIds(): array
    {
        $descendantIds = self::query()
            ->where('path', 'like', $this->subtreePathPrefix().'%')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_values([$this->id, ...$descendantIds]);
    }

    /**
     * Folders visible to a client: any folder shared with them or their
     * groups (plus every descendant of such a folder, live subtree), or
     * any folder they created themselves, anywhere in that visible tree
     * (see MyFoldersController::store's parent_id validation, which only
     * ever lets a client nest a new folder inside this same set).
     *
     * @param  Builder<Folder>  $query
     */
    public function scopeVisibleToClient(Builder $query, User $client): void
    {
        $sharedIds = self::sharedFolderIds($client);

        $query->where(function (Builder $inner) use ($sharedIds, $client): void {
            $inner->where('created_by', $client->id);

            if ($sharedIds !== []) {
                $inner->orWhereIn('id', $sharedIds);

                foreach (self::query()->whereIn('id', $sharedIds)->get() as $shared) {
                    $inner->orWhere('path', 'like', $shared->subtreePathPrefix().'%');
                }
            }
        });
    }

    /**
     * Folders publicly reachable on the public listing site: any folder
     * marked public, plus every descendant in its live subtree (mirrors
     * scopeVisibleToClient's shared-subtree shape). No Gate/auth involved
     * — same reasoning as File::scopeStandalonePublic.
     *
     * @param  Builder<Folder>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $publicIds = self::query()->where('public', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($publicIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $inner) use ($publicIds): void {
            $inner->whereIn('id', $publicIds);

            foreach (self::query()->whereIn('id', $publicIds)->get() as $public) {
                $inner->orWhere('path', 'like', $public->subtreePathPrefix().'%');
            }
        });
    }

    /**
     * Ids of folders shared directly with the client or via a group.
     *
     * @return list<int>
     */
    public static function sharedFolderIds(User $client): array
    {
        $groupIds = $client->memberOfGroups()->pluck('groups.id')->all();

        $ids = FolderAssignment::query()
            ->where(function (Builder $query) use ($client, $groupIds): void {
                $query->where(function (Builder $direct) use ($client): void {
                    $direct->where('assignable_type', (new User)->getMorphClass())
                        ->where('assignable_id', $client->id);
                })->orWhere(function (Builder $viaGroup) use ($groupIds): void {
                    $viaGroup->where('assignable_type', (new Group)->getMorphClass())
                        ->whereIn('assignable_id', $groupIds);
                });
            })
            ->pluck('folder_id')
            ->all();

        return array_values(array_map('intval', $ids));
    }
}
