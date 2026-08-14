<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Http\Controllers\Concerns\ResolvesShareTargets;
use App\Modules\Files\Http\Resources\Api\FileResource;
use App\Modules\Files\Models\File;
use App\Modules\Files\Sharing\FileSharing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Sharing a file with a client or a group: `{type: client|group, id}`.
 *
 * Both the target resolution (ResolvesShareTargets, which also stops a
 * client-scoped staff member sharing with someone else's client) and the
 * effects (FileSharing — the row, the activity entry, the in-app
 * notification, the digest email) are shared with the web controller, so
 * the two surfaces cannot drift.
 *
 * "May share" is "may edit", matching the web routes: there is no separate
 * sharing permission in this application, and inventing one for the API
 * would be a boundary that exists nowhere else.
 */
class FileAssignmentsController extends Controller
{
    use ResolvesShareTargets;

    public function __construct(
        private readonly StaffLibraryScope $scope,
        private readonly FileSharing $sharing,
    ) {}

    public function store(Request $request, File $file): FileResource
    {
        Gate::authorize('update', $file);
        $this->guardFileOwnsItsSharing($file);

        [$assignable, $targetName] = $this->resolveRequestedTarget(
            $request,
            __('Files can only be assigned to clients or groups.'),
        );

        // Idempotent, so a retried request is safe — which matters more
        // here than on the web, where a human does not retry automatically.
        $this->sharing->assign($file, $assignable, $targetName);

        return new FileResource($file->fresh()?->load(['assignments.assignable']) ?? $file);
    }

    public function destroy(Request $request, File $file): FileResource
    {
        Gate::authorize('update', $file);
        $this->guardFileOwnsItsSharing($file);

        [$assignable, $targetName] = $this->resolveRequestedTarget(
            $request,
            __('Files can only be assigned to clients or groups.'),
        );

        $this->sharing->unassign($file, $assignable, $targetName);

        return new FileResource($file->fresh()?->load(['assignments.assignable']) ?? $file);
    }
}
