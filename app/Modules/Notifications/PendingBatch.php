<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The debounce every batched email uses: shares and comments
 * (SendNotificationDigest) and a client's uploads (SendClientUploadMail).
 *
 * Each queued item writes a pending_notifications row and queues a send
 * QUIET_SECONDS later. A send that runs while the newest row is younger
 * than that stands down, because the send queued by that newer row will
 * take everything. So only the send queued by the last item of a burst
 * finds things quiet, and it sends the whole burst as one email.
 *
 * A fixed delay without this check grouped only the first two minutes of
 * a long burst, and sent one email per item after that.
 *
 * Nothing is held longer than MAX_HOLD_SECONDS, so a burst that never
 * stops still sends an email every quarter of an hour, not one at the end.
 */
final class PendingBatch
{
    /** How long things must be quiet before a batch goes out. */
    public const QUIET_SECONDS = 120;

    public const MAX_HOLD_SECONDS = 900;

    /**
     * Allowance for a queue that runs a delayed job a moment early, so the
     * send queued by the last item is never mistaken for one mid-burst.
     */
    private const SLACK_SECONDS = 5;

    /**
     * A queue that runs jobs on the spot cannot wait, and a send that stood
     * down there would never be followed by another. On such an
     * installation each item is sent at once, on its own, as it was before
     * any batching.
     */
    public static function queueCannotWait(): bool
    {
        return config('queue.connections.'.config('queue.default').'.driver') === 'sync';
    }

    /**
     * Takes and deletes the rows for one key and type, if it is time to send
     * them. Returns null when there is nothing to send, or when a later
     * send will take them.
     *
     * The rows are locked and deleted before anything is sent, so two sends
     * running together cannot both mail the same items. Mail is itself
     * queued, so it goes out after this commits.
     *
     * @return Collection<int, PendingNotification>|null
     */
    public static function takeWhenQuiet(int $userId, string $type, bool $immediate = false): ?Collection
    {
        return DB::transaction(function () use ($userId, $type, $immediate): ?Collection {
            $pending = PendingNotification::query()
                ->where('user_id', $userId)
                ->where('type', $type)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($pending->isEmpty()) {
                return null;
            }

            $quietSince = now()->subSeconds(self::QUIET_SECONDS - self::SLACK_SECONDS);
            $holdLimit = now()->subSeconds(self::MAX_HOLD_SECONDS);
            $stillBusy = $pending->contains(fn (PendingNotification $row): bool => $row->created_at !== null && $row->created_at->greaterThan($quietSince));
            $heldTooLong = $pending->contains(fn (PendingNotification $row): bool => $row->created_at !== null && $row->created_at->lessThan($holdLimit));

            if ($stillBusy && ! $heldTooLong && ! $immediate) {
                return null;
            }

            PendingNotification::query()->whereIn('id', $pending->pluck('id'))->delete();

            return $pending;
        });
    }
}
