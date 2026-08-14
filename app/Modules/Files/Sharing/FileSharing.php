<?php

declare(strict_types=1);

namespace App\Modules\Files\Sharing;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Groups\Models\Group;
use App\Modules\Notifications\NotificationDigester;
use App\Modules\Notifications\Notifier;

/**
 * What actually happens when a file is shared with a client or a group —
 * the assignment row, the activity entry, the in-app notification and the
 * debounced digest email, in that order.
 *
 * Extracted so the web controller and the API controller cannot answer the
 * question differently. Sharing is not one insert: it is an insert plus
 * three side effects, and the next side effect added here should not
 * depend on someone remembering there are two callers. Same reasoning as
 * StoreUploadedFile, which is the single seam for "a payload becomes a
 * File".
 *
 * Authorization is the caller's job — both callers reach this after
 * Gate::authorize('update', $file), and the target has already been
 * resolved and scope-checked by ResolvesShareTargets.
 */
class FileSharing
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly NotificationDigester $digester,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Idempotent: assigning a file to the same target twice is a no-op for
     * the row, which matters for an API caller retrying a request.
     */
    public function assign(File $file, User|Group $assignable, string $targetName): void
    {
        FileAssignment::query()->firstOrCreate([
            'file_id' => $file->id,
            'assignable_type' => $assignable->getMorphClass(),
            'assignable_id' => $assignable->getKey(),
        ]);

        $this->activity->log(Action::FileAssigned, subject: $file, context: ['target' => $targetName]);

        $recipients = $this->recipients($assignable);

        $this->notifier->send('file_shared', $recipients, subject: $file, data: ['itemName' => $file->name]);

        // The master switch and each recipient's own preference are the
        // digester's job now — every caller was repeating them.
        $this->digester->queue('file_shared', $recipients, $file->name, ['is_folder' => false]);
    }

    /**
     * @return bool whether an assignment was actually removed
     */
    public function unassign(File $file, User|Group $assignable, string $targetName): bool
    {
        $deleted = FileAssignment::query()
            ->where('file_id', $file->id)
            ->where('assignable_type', $assignable->getMorphClass())
            ->where('assignable_id', $assignable->getKey())
            ->delete();

        if ($deleted > 0) {
            $this->activity->log(Action::FileUnassigned, subject: $file, context: ['target' => $targetName]);
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
