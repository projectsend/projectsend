<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Http\Controllers\Concerns\ResolvesShareTargets;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Sharing\FolderSharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Sharing a folder with a client or group grants live access to its
 * whole subtree. Mirrors FileAssignmentsController; the effects live in
 * FolderSharing, shared with the API.
 */
class FolderAssignmentsController extends Controller
{
    use ResolvesShareTargets;

    public function __construct(
        private readonly StaffLibraryScope $scope,
        private readonly FolderSharing $sharing,
    ) {}

    public function store(Request $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('update', $folder);

        [$assignable, $targetName] = $this->resolveRequestedTarget(
            $request,
            __('Folders can only be shared with clients or groups.'),
        );

        $this->sharing->assign($folder, $assignable, $targetName);

        return back();
    }

    public function destroy(Request $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('update', $folder);

        [$assignable, $targetName] = $this->resolveRequestedTarget(
            $request,
            __('Folders can only be shared with clients or groups.'),
        );

        $this->sharing->unassign($folder, $assignable, $targetName);

        return back();
    }
}
