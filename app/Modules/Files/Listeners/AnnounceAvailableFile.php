<?php

declare(strict_types=1);

namespace App\Modules\Files\Listeners;

use App\Models\User;
use App\Modules\Files\Events\FileBecameAvailable;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Groups\Models\Group;
use App\Modules\Notifications\NotificationDigester;
use App\Modules\Notifications\Notifier;
use Illuminate\Support\Collection;

/**
 * Tells the people a file was shared with, once it can actually be had.
 *
 * Sharing a file that is still being scanned writes the assignment and
 * says nothing (FileSharing::assign). This is the other half: when the
 * scan finishes, or the file is let through, or an administrator releases
 * it from quarantine, whoever it was shared with hears about it then.
 *
 * Recipients are derived from the assignments as they stand *now* rather
 * than remembered from the moment of sharing. A share taken back while
 * the file was being checked should not produce an email afterwards, and
 * one added in the meantime should — and deriving costs one query,
 * against a table that already has to be read to answer the same question
 * anywhere else.
 */
class AnnounceAvailableFile
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly NotificationDigester $digester,
        private readonly FileVersions $versions,
    ) {}

    public function handle(FileBecameAvailable $event): void
    {
        $file = $event->file;

        $recipients = $this->recipients($file->id);

        if ($recipients->isNotEmpty()) {
            $this->notifier->send('file_shared', $recipients, subject: $file, data: ['itemName' => $file->name]);
            $this->digester->queue('file_shared', $recipients, $file->name, ['is_folder' => false]);
        }

        $previous = $file->previousVersion;

        if ($previous === null) {
            return;
        }

        // The same intersection rule the linking itself follows: only
        // somebody who can see both files is told, and it is asked again
        // here because while the new file was being checked the visibility
        // scope hid it and the audience came out empty.
        $audience = $this->versions->sharedAudience($file, $previous);

        if ($audience->isNotEmpty()) {
            $this->notifier->send('file_new_version', $audience, subject: $file, data: [
                'itemName' => $file->name,
                'previousName' => $previous->name,
            ]);

            $this->digester->queue('file_new_version', $audience, $file->name, [
                'previousName' => $previous->name,
            ]);
        }
    }

    /**
     * Everybody the file is assigned to, directly or through a group.
     *
     * @return Collection<int, User>
     */
    private function recipients(int $fileId): Collection
    {
        return FileAssignment::query()
            ->where('file_id', $fileId)
            ->get()
            ->flatMap(function (FileAssignment $assignment): array {
                $target = $assignment->assignable;

                if ($target instanceof Group) {
                    return $target->members->all();
                }

                return $target instanceof User ? [$target] : [];
            })
            ->unique(fn (User $user): int => $user->id)
            ->values();
    }
}
