<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Http\Controllers\Concerns\ResolvesShareTargets;
use App\Modules\Files\Models\File;
use App\Modules\Files\Sharing\FileSharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FileAssignmentsController extends Controller
{
    use ResolvesShareTargets;

    public function __construct(
        private readonly StaffLibraryScope $scope,
        private readonly FileSharing $sharing,
    ) {}

    public function store(Request $request, File $file): RedirectResponse
    {
        Gate::authorize('update', $file);
        $this->guardFileOwnsItsSharing($file);

        [$assignable, $targetName] = $this->resolveRequestedTarget(
            $request,
            __('Files can only be assigned to clients or groups.'),
        );

        $this->sharing->assign($file, $assignable, $targetName);

        return back();
    }

    public function destroy(Request $request, File $file): RedirectResponse
    {
        Gate::authorize('update', $file);
        $this->guardFileOwnsItsSharing($file);

        [$assignable, $targetName] = $this->resolveRequestedTarget(
            $request,
            __('Files can only be assigned to clients or groups.'),
        );

        $this->sharing->unassign($file, $assignable, $targetName);

        return back();
    }
}
