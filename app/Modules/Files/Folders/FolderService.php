<?php

declare(strict_types=1);

namespace App\Modules\Files\Folders;

use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Folder tree operations that must keep the materialized path and the
 * cascade-delete invariant consistent.
 */
class FolderService
{
    public function create(string $name, ?Folder $parent): Folder
    {
        $this->assertDepth($parent);

        return Folder::query()->create([
            'name' => $name,
            'parent_id' => $parent?->id,
            'created_by' => auth()->id(),
            'path' => $parent === null ? '/' : $parent->subtreePathPrefix(),
        ]);
    }

    /**
     * Reparent a folder, rejecting cycles and recomputing the subtree's
     * paths in one pass.
     */
    public function move(Folder $folder, ?Folder $newParent): void
    {
        if ($newParent !== null) {
            // Into itself or any of its own descendants → cycle.
            $isSelfOrDescendant = $newParent->id === $folder->id
                || str_starts_with($newParent->path, $folder->subtreePathPrefix());

            if ($isSelfOrDescendant) {
                throw ValidationException::withMessages(['parent_id' => __('A folder cannot be moved into itself.')]);
            }

            $this->assertDepth($newParent);
        }

        $oldPrefix = $folder->subtreePathPrefix();
        $newPath = $newParent === null ? '/' : $newParent->subtreePathPrefix();
        $newPrefix = $newPath.$folder->id.'/';

        DB::transaction(function () use ($folder, $newParent, $newPath, $oldPrefix, $newPrefix): void {
            // Descendants: swap the old prefix for the new one.
            Folder::query()
                ->where('path', 'like', $oldPrefix.'%')
                ->get()
                ->each(function (Folder $descendant) use ($oldPrefix, $newPrefix): void {
                    $descendant->forceFill([
                        'path' => $newPrefix.substr($descendant->path, strlen($oldPrefix)),
                    ])->save();
                });

            $folder->forceFill(['parent_id' => $newParent?->id, 'path' => $newPath])->save();
        });
    }

    /**
     * Cascade soft-delete: the folder, every descendant folder, and all
     * files within the subtree go together.
     */
    public function delete(Folder $folder): void
    {
        DB::transaction(function () use ($folder): void {
            $subtreeIds = Folder::query()
                ->where('id', $folder->id)
                ->orWhere('path', 'like', $folder->subtreePathPrefix().'%')
                ->pluck('id');

            File::query()->whereIn('folder_id', $subtreeIds)->get()->each->delete();
            Folder::query()->whereIn('id', $subtreeIds)->get()->each->delete();
        });
    }

    private function assertDepth(?Folder $parent): void
    {
        if ($parent !== null && $parent->depth() + 1 >= Folder::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => __('Folders cannot be nested more than :max levels deep.', ['max' => (string) Folder::MAX_DEPTH]),
            ]);
        }
    }
}
