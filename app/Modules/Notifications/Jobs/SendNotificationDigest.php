<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Notifications\NotificationPreferences;
use App\Modules\Notifications\NotificationTypeRegistry;
use App\Modules\Notifications\PendingBatch;
use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Gate;

/**
 * One of these is dispatched, delayed, for every single queued item, so a
 * burst leaves several scheduled. Each stands down while the burst is
 * still going (PendingBatch's rule); the one queued by the last item finds
 * things quiet and sends everything as one email. Any left after it find
 * nothing and do nothing. No "is a digest already scheduled" flag is
 * needed for that reason.
 *
 * A lone item still sends the type's ordinary single-item mail, so one
 * share or one comment looks exactly as it would have without any
 * batching in the picture.
 *
 * What was true when an item was queued is asked again when it is sent:
 * the wait can be a quarter of an hour, and in that time a file can be
 * unshared or deleted, a staff member's clients can change, or the
 * recipient can switch this email off. An item naming a file or folder
 * the recipient can no longer view is left out, by the same `view` check
 * that chose the recipients in the first place.
 */
class SendNotificationDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Send what is waiting now, without standing down. For a queue that
     * cannot delay. A declared property with a default rather than a
     * promoted one, so a job queued before this existed still reads false.
     */
    private bool $immediate = false;

    public function __construct(
        private readonly int $userId,
        private readonly string $typeKey,
        bool $immediate = false,
    ) {
        $this->immediate = $immediate;
    }

    public function handle(NotificationTypeRegistry $types, NotificationPreferences $preferences, Settings $settings): void
    {
        $type = $types->get($this->typeKey);

        if ($type?->digestMail === null) {
            return;
        }

        $items = PendingBatch::takeWhenQuiet($this->userId, $this->typeKey, $this->immediate);

        if ($items === null) {
            return;
        }

        $user = User::query()->find($this->userId);

        // Asked again at send time: the account may have been deactivated,
        // or the email switched off, during the wait.
        if ($user === null
            || ! $user->maySignIn()
            || $settings->get(Setting::EmailNotificationsEnabled) !== true
            || ! $preferences->emailEnabledFor($user, $type)) {
            return;
        }

        $items = $items->filter(fn (PendingNotification $item): bool => $this->stillVisible($user, $item))->values();

        if ($items->isEmpty()) {
            return;
        }

        if ($items->count() === 1 || $type->digestMailMany === null) {
            /** @var PendingNotification $item */
            $item = $items->first();

            // Each single-item mail class exposes from(), because the row
            // carries its payload in a generic `context` and only that
            // class knows how to read its own.
            $user->notify($type->digestMail::from($item));

            return;
        }

        $user->notify(new $type->digestMailMany(array_values($items->all())));
    }

    /**
     * Whether the recipient may still view what the item names. An item
     * queued before items carried an id cannot be asked, and is sent.
     */
    private function stillVisible(User $user, PendingNotification $item): bool
    {
        $context = $item->context ?? [];

        if (isset($context['file_id'])) {
            $file = File::query()->find((int) $context['file_id']);

            return $file !== null && Gate::forUser($user)->allows('view', $file);
        }

        if (isset($context['folder_id'])) {
            $folder = Folder::query()->find((int) $context['folder_id']);

            return $folder !== null && Gate::forUser($user)->allows('view', $folder);
        }

        return true;
    }
}
