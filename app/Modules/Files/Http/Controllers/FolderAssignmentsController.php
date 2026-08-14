<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Http\Controllers\Concerns\ResolvesShareTargets;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Notifications\NotificationDigester;
use App\Modules\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Sharing a folder with a client or group grants live access to its
 * whole subtree. Mirrors FileAssignmentsController.
 */
class FolderAssignmentsController extends Controller
{
    use ResolvesShareTargets;

    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly StaffLibraryScope $scope,
        private readonly NotificationDigester $digester,
        private readonly Notifier $notifier,
    ) {}

    public function store(Request $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('update', $folder);

        [$assignable, $targetName] = $this->resolveRequestedTarget(
            $request,
            __('Folders can only be shared with clients or groups.'),
        );

        FolderAssignment::query()->firstOrCreate([
            'folder_id' => $folder->id,
            'assignable_type' => $this->assignableType($assignable),
            'assignable_id' => $assignable->getKey(),
        ]);

        $this->activity->log(Action::FolderShared, subject: $folder, context: ['target' => $targetName]);

        $recipients = $this->shareRecipients($assignable);
        $this->notifier->send('file_shared', $recipients, subject: $folder, data: ['itemName' => $folder->name]);

        // The master switch and each recipient's own preference are the
        // digester's job now — every caller was repeating them.
        $this->digester->queue('file_shared', $recipients, $folder->name, ['is_folder' => true]);

        return back();
    }

    public function destroy(Request $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('update', $folder);

        [$assignable, $targetName] = $this->resolveRequestedTarget(
            $request,
            __('Folders can only be shared with clients or groups.'),
        );

        $deleted = FolderAssignment::query()
            ->where('folder_id', $folder->id)
            ->where('assignable_type', $this->assignableType($assignable))
            ->where('assignable_id', $assignable->getKey())
            ->delete();

        if ($deleted > 0) {
            $this->activity->log(Action::FolderUnshared, subject: $folder, context: ['target' => $targetName]);
        }

        return back();
    }
}
