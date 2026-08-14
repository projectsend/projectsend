<?php

declare(strict_types=1);

namespace App\Modules\Files\Folders;

use App\Modules\Files\Models\Folder;

/**
 * The trail from the library root down to a folder.
 *
 * The staff library and the client portal both render one, and both built it
 * the same way: collect the ancestor ids, fetch them in one query, then put
 * them back into ancestor order — because whereIn() returns rows in whatever
 * order it likes, and a breadcrumb in the wrong order is nonsense. The
 * reordering is the fiddly part, and the part worth having once.
 */
class BreadcrumbBuilder
{
    /**
     * The full trail to $current, root first.
     *
     * @return list<array{id: int, name: string}>
     */
    public function for(?Folder $current): array
    {
        if ($current === null) {
            return [];
        }

        return $this->fromIds([...$current->ancestorIds(), $current->id]);
    }

    /**
     * The trail to $current, trimmed to what this viewer can actually see:
     * it starts at the first visible ancestor, since a client shared with a
     * folder deep in the tree has no business seeing the names of the
     * folders above it.
     *
     * @param  list<int>  $visibleIds  folder ids within the viewer's subtree
     * @return list<array{id: int, name: string}>
     */
    public function visible(?Folder $current, array $visibleIds): array
    {
        if ($current === null) {
            return [];
        }

        $ids = [...$current->ancestorIds(), $current->id];

        foreach ($ids as $index => $id) {
            if (in_array($id, $visibleIds, true)) {
                return $this->fromIds(array_slice($ids, $index));
            }
        }

        // Nothing on the path is visible — show the folder alone rather than
        // leaking the names of ancestors this viewer cannot reach.
        return [['id' => $current->id, 'name' => $current->name]];
    }

    /**
     * @param  list<int>  $ids  in ancestor order, root first
     * @return list<array{id: int, name: string}>
     */
    private function fromIds(array $ids): array
    {
        $folders = Folder::query()->whereIn('id', $ids)->get()
            ->sortBy(fn (Folder $folder): int => (int) array_search($folder->id, $ids, true));

        $trail = [];

        foreach ($folders as $folder) {
            $trail[] = ['id' => $folder->id, 'name' => $folder->name];
        }

        return $trail;
    }
}
