<?php

declare(strict_types=1);

namespace App\Modules\Files\Models;

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Folders\FolderReach;
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

    /** Whether this folder stands in as some client's root. */
    public function isHome(): bool
    {
        return $this->home_for_user_id !== null;
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
     * Whether $user may put content into $folder (null = loose at the
     * root, always allowed).
     *
     * **Read the name as "may place into", not "may upload into".** Every
     * way a file arrives in a folder has to come through here, and the
     * name cost us one advisory already: the publication rule below was
     * written for GHSA-237r-jx85-j3hr and wired into the upload paths
     * alone, because those are what the name suggested. Moving a file in,
     * bulk-moving a selection in, reparenting one through the edit form,
     * and dragging a whole folder into a public parent all put content
     * somewhere too, and none of them asked (GHSA-rxf8-wh8v-jm9j). They
     * ask now. Anything new that writes a `folder_id` or a `parent_id`
     * belongs on this list.
     *
     * Staff are held to the library boundary they are held to everywhere
     * else: an unscoped staff member may use any folder, a client-scoped
     * one only the folders StaffLibraryScope already shows them. Callers
     * that have already resolved the destination through
     * StaffLibraryScope::folders() have answered that half — the two are
     * the same query — and call this for the publication half.
     *
     * For a client this is unchanged, and is still the whole of the
     * check: they own the folder, or it is a public folder that opts into
     * client uploads and their role permits uploading into public folders
     * at all.
     */
    public static function uploadableBy(User $user, ?self $folder): bool
    {
        if ($folder === null) {
            return true;
        }

        if ($user->isStaff()) {
            if (! app(StaffLibraryScope::class)->allowsFolder($user, $folder)) {
                return false;
            }

            // Reaching the folder is not the same as being allowed to share
            // with everybody it reaches. A folder shared with one of this
            // staff member's clients and with somebody else's is in their
            // library, and putting a file there shared it with both. A
            // client-scoped staff member shares only with their own clients,
            // so they may place only where every recipient is theirs.
            if (! app(FolderReach::class)->withinRosterOf($user, $folder)) {
                return false;
            }

            // Being allowed to reach the folder is not the same as being
            // allowed to publish, and putting a file in a public folder
            // publishes it: isEffectivelyPublic() is "my own flag, or my
            // folder's". So the destination reaches the property that
            // `upload_public` guards, without ever touching the switch
            // (GHSA-237r-jx85-j3hr).
            //
            // The keys already say this. The client branch below has always
            // asked for `upload_to_public_folders` here, and
            // MyFilesController's picker calls that the established meaning
            // of the two — it was simply never asked on a staff role, which
            // left that permission doing nothing at all for staff.
            //
            // Effectively public, not `public`: the flag is inherited down
            // a subtree, so a private folder inside a public one publishes
            // just the same and a check on the folder's own flag would walk
            // straight past it.
            return ! $folder->isEffectivelyPublic()
                || $user->can('upload_public')
                || $user->can('upload_to_public_folders');
        }

        if (! $folder->isEffectivelyPublic()) {
            // Their own folder, and one only they can see. A client's folder
            // inside a folder staff shared with a group, or with several
            // clients, showed everything they put in it to all of them.
            return $folder->isOwnedBy($user) && ! app(FolderReach::class)->reachesOthersThan($user, $folder);
        }

        // Owning the folder is not enough once it is public, and a client's
        // own folder is public whenever it sits inside a public one: the
        // flag is inherited. Owning it alone let a client create a folder
        // inside a public folder that staff kept closed to client uploads,
        // upload into it, and publish (GHSA-65c7-3fpf-q89h). So a client
        // placing into anything public needs the permission, and every
        // public folder on the way up must opt into client uploads.
        if (! $user->can('upload_to_public_folders')) {
            return false;
        }

        if (! $folder->isOwnedBy($user) && ! $folder->public) {
            return false;
        }

        return ! self::query()
            ->whereIn('id', [$folder->id, ...$folder->ancestorIds()])
            ->where('public', true)
            ->where('allow_client_uploads', false)
            ->exists();
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
     * groups (plus every descendant of such a folder, live subtree), and
     * the folders they created in a tree of their own.
     *
     * "A tree of their own" is one whose top-level folder they created:
     * their home folder, or a folder they made at the top of the library
     * where there are no homes. A folder they created inside a folder
     * staff shared with them is visible through that share and only while
     * it lasts. Owning the folder used to be enough wherever it sat, so
     * unsharing the parent, or moving the folder, left the client with it
     * and with every file staff later filed there, for whoever it was
     * meant for. Visibility follows the share.
     *
     * @param  Builder<Folder>  $query
     */
    public function scopeVisibleToClient(Builder $query, User $client): void
    {
        $sharedIds = self::sharedFolderIds($client);
        $ownRootIds = self::ownRootIds($client);

        $query->where(function (Builder $inner) use ($sharedIds, $ownRootIds, $client): void {
            if ($ownRootIds !== []) {
                $inner->where(function (Builder $own) use ($ownRootIds, $client): void {
                    $own->where('created_by', $client->id)->where(function (Builder $tree) use ($ownRootIds): void {
                        $tree->whereIn('id', $ownRootIds);

                        foreach ($ownRootIds as $rootId) {
                            $tree->orWhere('path', 'like', '/'.$rootId.'/%');
                        }
                    });
                });
            } else {
                // Nothing of their own: start from an empty match so the
                // shares below are or'ed onto something false.
                $inner->whereRaw('1 = 0');
            }

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
     * Top-level folders the client created: the roots of the trees that
     * are theirs by creation rather than by a share.
     *
     * @return list<int>
     */
    public static function ownRootIds(User $client): array
    {
        return array_values(self::query()
            ->whereNull('parent_id')
            ->where('created_by', $client->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all());
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
