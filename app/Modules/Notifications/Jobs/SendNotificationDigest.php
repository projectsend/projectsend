<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Models\User;
use App\Modules\Notifications\NotificationTypeRegistry;
use App\Modules\Notifications\PendingBatch;
use App\Modules\Notifications\PendingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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

    public function handle(NotificationTypeRegistry $types): void
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

        // Asked again at send time: the account may have been deactivated
        // during the debounce window.
        if ($user === null || ! $user->maySignIn()) {
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
}
