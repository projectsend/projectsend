<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Models\User;
use App\Modules\Notifications\NotificationTypeRegistry;
use App\Modules\Notifications\PendingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * One of these is dispatched, delayed, for every single queued item — so
 * several to the same recipient inside the debounce window leave several
 * jobs scheduled, all but one of which find nothing to do. That is
 * deliberate and cheap: whichever runs first consumes everything
 * accumulated for that recipient and type so far and sends one email;
 * every later job finds an empty list and no-ops. No "is a digest already
 * scheduled" flag is needed for that reason.
 *
 * A lone item still sends the type's ordinary single-item mail, so one
 * share or one comment looks exactly as it would have without any
 * batching in the picture.
 */
class SendNotificationDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $userId,
        private readonly string $typeKey,
    ) {}

    public function handle(NotificationTypeRegistry $types): void
    {
        $type = $types->get($this->typeKey);

        if ($type?->digestMail === null) {
            return;
        }

        // Locked and deleted inside a transaction, before anything is
        // sent — sending is itself queued, so it must happen after this
        // commits rather than from inside it.
        $items = DB::transaction(function (): ?Collection {
            $pending = PendingNotification::query()
                ->where('user_id', $this->userId)
                ->where('type', $this->typeKey)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($pending->isEmpty()) {
                return null;
            }

            PendingNotification::query()->whereIn('id', $pending->pluck('id'))->delete();

            return $pending;
        });

        if ($items === null || $items->isEmpty()) {
            return;
        }

        $user = User::query()->find($this->userId);

        if ($user === null) {
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
