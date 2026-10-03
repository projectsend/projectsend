<?php

declare(strict_types=1);

namespace App\Modules\Files\Sharing;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\FolderAssignment;
use App\Modules\Groups\Models\Group;
use App\Modules\Notifications\NotificationDigester;
use App\Modules\Notifications\Notifier;

/**
 * What actually happens when a folder is shared with a client or a group —
 * the assignment row, the activity entry, the in-app notification and the
 * debounced digest email, in that order. The folder twin of FileSharing.
 *
 * Extracted for the same reason FileSharing was: the web controller and the
 * API controller must not be able to answer the question differently. The
 * AI connector in the hosted edition repeated these four steps too, because
 * there was nothing here to call.
 *
 * Unlike a file, a folder has no scan to wait for: the files inside it are
 * held back individually until they can be had, and sharing the folder
 * does not change that. So the telling is never deferred here.
 *
 * Authorization is the caller's job — both callers reach this after
 * Gate::authorize('update', $folder), and the target has already been
 * resolved and scope-checked by ResolvesShareTargets.
 */
class FolderSharing
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly NotificationDigester $digester,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Idempotent for the row: sharing the same folder with the same target
     * twice leaves one assignment, which matters for an API caller retrying
     * a request.
     */
    public function assign(Folder $folder, User|Group $assignable, string $targetName): void
    {
        FolderAssignment::query()->firstOrCreate([
            'folder_id' => $folder->id,
            'assignable_type' => $assignable->getMorphClass(),
            'assignable_id' => $assignable->getKey(),
        ]);

        $this->activity->log(Action::FolderShared, subject: $folder, context: ['target' => $targetName]);

        $recipients = $this->recipients($assignable);

        $this->notifier->send('file_shared', $recipients, subject: $folder, data: ['itemName' => $folder->name]);

        // The master switch and each recipient's own preference are the
        // digester's job now — every caller was repeating them.
        $this->digester->queue('file_shared', $recipients, $folder->name, ['is_folder' => true]);
    }

    /**
     * @return bool whether an assignment was actually removed
     */
    public function unassign(Folder $folder, User|Group $assignable, string $targetName): bool
    {
        $deleted = FolderAssignment::query()
            ->where('folder_id', $folder->id)
            ->where('assignable_type', $assignable->getMorphClass())
            ->where('assignable_id', $assignable->getKey())
            ->delete();

        if ($deleted > 0) {
            $this->activity->log(Action::FolderUnshared, subject: $folder, context: ['target' => $targetName]);
        }

        return $deleted > 0;
    }

    /**
     * Notifier performs no authorization of its own — see its SECURITY
     * CONTRACT docblock — so the recipient list is resolved here, from the
     * assignment itself.
     *
     * @return iterable<User>
     */
    private function recipients(User|Group $assignable): iterable
    {
        return $assignable instanceof Group ? $assignable->members : [$assignable];
    }
}
