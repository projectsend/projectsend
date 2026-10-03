<?php

declare(strict_types=1);

namespace App\Modules\Files\Folders;

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\Folder;

/**
 * The ancestors of a whole page of folders, in two queries however long the
 * page is, trimmed to what the viewer may see.
 *
 * BreadcrumbBuilder answers this for one folder on a screen. A list
 * endpoint needs it for every row, and a query per row is the cost a
 * listing must not have.
 *
 * Trimmed the way BreadcrumbBuilder::visible() trims the client portal's
 * trail: the list starts at the first ancestor the viewer can reach, since
 * a client-scoped staff member holding a client's folder deep in somebody
 * else's tree has no business reading the names of the folders above it.
 * An unscoped staff member reaches every folder, so for them nothing is
 * ever trimmed.
 */
class FolderTrails
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
    ) {}

    /**
     * @param  iterable<Folder>  $folders
     * @return array<int, list<array{id: int, name: string}>> folder id => its visible ancestors, root first, itself excluded
     */
    public function ancestors(iterable $folders, User $viewer): array
    {
        $chains = [];
        $allIds = [];

        foreach ($folders as $folder) {
            $ids = $folder->ancestorIds();
            $chains[$folder->id] = $ids;
            array_push($allIds, ...$ids);
        }

        $allIds = array_values(array_unique($allIds));

        if ($allIds === []) {
            return array_map(fn (): array => [], $chains);
        }

        $names = Folder::query()->whereIn('id', $allIds)->pluck('name', 'id')->all();

        $visible = $viewer->isClientScoped()
            ? array_flip($this->scope->folders($viewer)->whereIn('folders.id', $allIds)->pluck('folders.id')->all())
            : array_flip($allIds);

        $out = [];

        foreach ($chains as $folderId => $ids) {
            $trail = [];
            $reached = false;

            foreach ($ids as $id) {
                $reached = $reached || isset($visible[$id]);

                if ($reached && isset($names[$id])) {
                    $trail[] = ['id' => $id, 'name' => (string) $names[$id]];
                }
            }

            $out[$folderId] = $trail;
        }

        return $out;
    }
}
