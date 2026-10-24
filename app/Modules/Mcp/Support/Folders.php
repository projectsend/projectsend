<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Files\Sharing\FolderSharing;
use Illuminate\Database\Eloquent\Builder;

/**
 * The staff library's folders, under the app's own rules — the folder
 * twin of Files.
 *
 * Which folders a staff member sees is StaffLibraryScope: the
 * whole tree for most, their own and their clients' for a client-scoped
 * one. What they may do to a folder is FolderPolicy's own/others pair.
 * Whether they may put content into one is `Folder::uploadableBy()`,
 * which every way of placing a file or a folder somewhere has to ask: a
 * public folder publishes whatever lands in it, and the move half of
 * that was an advisory of its own (GHSA-rxf8-wh8v-jm9j). The tools ask
 * the same three questions the web does, in the same words.
 *
 * Staff only: on a client account every folder tool answers the gate
 * first (decision 17), so nothing here is ever asked for a client.
 */
final class Folders
{
    /**
     * The folders this staff member's library page would show.
     *
     * @return Builder<Folder>
     */
    public static function visibleTo(Account $account): Builder
    {
        return app(StaffLibraryScope::class)->folders($account->user);
    }

    public static function find(Account $account, int $folderId): ?Folder
    {
        return self::visibleTo($account)->whereKey($folderId)->first();
    }

    /** The answer every folder lookup gives when the id is not there. */
    public static function notFound(): ToolError
    {
        return ToolError::notFound('folder', ['tool' => 'list_folders', 'arguments' => []]);
    }

    /**
     * FolderPolicy::update, for staff: their own folder with `edit_files`,
     * anybody else's with `edit_others_files`. Reaching it at all was the
     * scope's question, answered by find().
     */
    public static function requireUpdate(Folder $folder, Account $account): void
    {
        $own = (int) $folder->getAttribute('created_by') === $account->id();
        $permission = $own ? 'edit_files' : 'edit_others_files';

        if (! $account->can($permission)) {
            throw new ToolFailure(ToolError::permissionMissing($permission, Web::staffFolder((int) $folder->getAttribute('id'))));
        }
    }

    /**
     * May this person put content into the folder: Folder's
     * `uploadableBy()`, which for staff is "the folder is not public, or
     * you may publish". Named for uploads, asked for every placement.
     */
    public static function requirePlaceable(Folder $folder, Account $account): void
    {
        if (Folder::uploadableBy($account->user, $folder)) {
            return;
        }

        throw new ToolFailure(ToolError::permissionMissing('upload_public', Web::staffFolder((int) $folder->getAttribute('id'))));
    }

    /**
     * Each folder's place in the tree, as a person would read it:
     * "Clients / Acme / 2026". One query for every ancestor on the page,
     * read off the materialized path Folder keeps ("/3/7/").
     *
     * @param  iterable<int, Folder>  $folders
     * @return array<int, string> folder id => its path, itself included
     */
    public static function paths(iterable $folders): array
    {
        $chains = [];
        $ancestorIds = [];

        foreach ($folders as $folder) {
            $ids = self::ancestorIds($folder);
            $chains[(int) $folder->getAttribute('id')] = [$ids, (string) $folder->getAttribute('name')];
            array_push($ancestorIds, ...$ids);
        }

        $names = $ancestorIds === []
            ? []
            : Folder::query()->whereIn('id', array_unique($ancestorIds))->pluck('name', 'id')->all();

        $out = [];

        foreach ($chains as $id => [$ids, $name]) {
            $parts = [];

            foreach ($ids as $ancestorId) {
                if (isset($names[$ancestorId])) {
                    $parts[] = (string) $names[$ancestorId];
                }
            }

            $parts[] = $name;
            $out[$id] = implode(' / ', $parts);
        }

        return $out;
    }

    /**
     * Ancestor ids, root first, from the materialized path.
     *
     * @return list<int>
     */
    public static function ancestorIds(Folder $folder): array
    {
        $path = trim((string) ($folder->getAttribute('path') ?? '/'), '/');

        return $path === '' ? [] : array_map('intval', explode('/', $path));
    }

    /**
     * Share the folder with a client account through FolderSharing, as
     * the web does: the assignment row, the activity entry, the in-app
     * notification and the digest email. True when it was newly shared;
     * a second call for the same pair changes nothing and notifies nobody.
     */
    public static function shareWith(Folder $folder, User $client): bool
    {
        $already = FolderAssignment::query()
            ->where('folder_id', $folder->id)
            ->where('assignable_type', $client->getMorphClass())
            ->where('assignable_id', $client->id)
            ->exists();

        if ($already) {
            return false;
        }

        app(FolderSharing::class)->assign($folder, $client, $client->name);

        return true;
    }

    /**
     * Files in the folder and every folder under it: what a client the
     * folder is shared with now sees.
     */
    public static function filesInSubtree(Folder $folder): int
    {
        $prefix = (string) ($folder->getAttribute('path') ?? '/').(int) $folder->getAttribute('id').'/';

        $folderIds = Folder::query()
            ->where('path', 'like', $prefix.'%')
            ->pluck('id')
            ->push((int) $folder->getAttribute('id'))
            ->all();

        return File::query()->whereIn('folder_id', $folderIds)->count();
    }
}
