<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Folders\FolderTrails;
use App\Modules\Files\Http\Controllers\Concerns\ResolvesShareTargets;
use App\Modules\Files\Http\Resources\Api\FolderResource;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Sharing\FolderSharing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Sharing a folder with a client or a group: `{type: client|group, id}`.
 *
 * A client a folder is shared with sees everything inside it, including
 * folders and files added later.
 *
 * Both the target resolution (ResolvesShareTargets) and the effects
 * (FolderSharing — the row, the activity entry, the in-app notification,
 * the digest email) are shared with the web controller, so the two surfaces
 * cannot drift. "May share" is "may edit", as on the web.
 */
class FolderAssignmentsController extends Controller
{
    use ResolvesShareTargets;

    public function __construct(
        private readonly StaffLibraryScope $scope,
        private readonly FolderSharing $sharing,
        private readonly FolderTrails $trails,
    ) {}

    /**
     * Share a folder.
     *
     * Sharing it again with the same client or group leaves one share.
     */
    public function store(Request $request, Folder $folder): FolderResource
    {
        Gate::authorize('update', $folder);

        [$assignable, $targetName] = $this->resolveRequestedTarget(
            $request,
            __('Folders can only be shared with clients or groups.'),
        );

        $this->sharing->assign($folder, $assignable, $targetName);

        return $this->resource($request, $folder);
    }

    /**
     * Stop sharing a folder.
     */
    public function destroy(Request $request, Folder $folder): FolderResource
    {
        Gate::authorize('update', $folder);

        [$assignable, $targetName] = $this->resolveRequestedTarget(
            $request,
            __('Folders can only be shared with clients or groups.'),
        );

        $this->sharing->unassign($folder, $assignable, $targetName);

        return $this->resource($request, $folder);
    }

    private function resource(Request $request, Folder $folder): FolderResource
    {
        $folder = $folder->fresh() ?? $folder;
        $folder->load('assignments.assignable');

        $user = $request->user();

        if ($user !== null) {
            $folder->setRelation('trail', collect($this->trails->ancestors([$folder], $user)[$folder->id] ?? []));
        }

        return new FolderResource($folder);
    }
}
