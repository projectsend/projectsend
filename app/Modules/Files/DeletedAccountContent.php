<?php

declare(strict_types=1);

namespace App\Modules\Files;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use Illuminate\Support\Facades\DB;

/**
 * What to do with a deleted account's files/folders — offered as a choice
 * at delete time by ClientsController::destroy() / UsersController::destroy().
 * The automatic (unattended) erasure path has no equivalent yet — see
 * docs/pre-release-todo.md.
 */
class DeletedAccountContent
{
    /**
     * @return array{files: int, folders: int}
     */
    public function summarize(User $user): array
    {
        return [
            'files' => File::query()->where('uploaded_by', $user->id)->count(),
            'folders' => Folder::query()->where('created_by', $user->id)->count(),
        ];
    }

    /**
     * Bulk form of summarize(), for list pages that need every row's counts
     * without an N+1 query per row.
     *
     * @param  iterable<int>  $userIds
     * @return array<int, array{files: int, folders: int}>
     */
    public function summarizeMany(iterable $userIds): array
    {
        $ids = collect($userIds)->all();

        $files = File::query()->whereIn('uploaded_by', $ids)
            ->selectRaw('uploaded_by, count(*) as aggregate')
            ->groupBy('uploaded_by')
            ->pluck('aggregate', 'uploaded_by');

        $folders = Folder::query()->whereIn('created_by', $ids)
            ->selectRaw('created_by, count(*) as aggregate')
            ->groupBy('created_by')
            ->pluck('aggregate', 'created_by');

        return collect($ids)->mapWithKeys(fn (int $id): array => [
            $id => ['files' => (int) ($files[$id] ?? 0), 'folders' => (int) ($folders[$id] ?? 0)],
        ])->all();
    }

    /**
     * Removes only content the account actually owns. A folder it created
     * is deleted only if — after removing the account's own files — the
     * folder's subtree ends up completely empty (no files, no child
     * folders); otherwise it's kept but ownerless, matching the state a
     * hard delete already leaves behind via the created_by FK's
     * nullOnDelete(). Processed deepest-first so a fully-owned subtree can
     * collapse bottom-up in one pass.
     *
     * @return array{files: int, folders: int}
     */
    public function cascadeDelete(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $files = File::query()->where('uploaded_by', $user->id)->get();
            $files->each->delete();

            $folders = Folder::query()->where('created_by', $user->id)->get()
                ->sortByDesc(fn (Folder $folder): int => $folder->depth());

            $foldersDeleted = 0;
            foreach ($folders as $folder) {
                $empty = ! File::query()->where('folder_id', $folder->id)->exists()
                    && ! Folder::query()->where('parent_id', $folder->id)->exists();

                if ($empty) {
                    $folder->delete();
                    $foldersDeleted++;
                } else {
                    $folder->update(['created_by' => null]);
                }
            }

            return ['files' => $files->count(), 'folders' => $foldersDeleted];
        });
    }

    /**
     * @return array{files: int, folders: int}
     */
    public function reassignTo(User $from, User $to): array
    {
        return DB::transaction(fn (): array => [
            'files' => File::query()->where('uploaded_by', $from->id)->update(['uploaded_by' => $to->id]),
            'folders' => Folder::query()->where('created_by', $from->id)->update(['created_by' => $to->id]),
        ]);
    }
}
