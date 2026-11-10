<?php

declare(strict_types=1);

namespace App\Modules\Files\Jobs;

use App\Models\User;
use App\Modules\Files\Notifications\AdminClientUploadDigestNotification;
use App\Modules\Files\Notifications\AdminClientUploadedNotification;
use App\Modules\Files\Uploads\ClientUploadMail;
use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Sends whatever one client has uploaded since the last send, as one email
 * per admin address. See ClientUploadMail.
 *
 * A real debounce: every upload queues one of these, and each one stands
 * down while the client is still uploading — while the newest file is
 * younger than the quiet period. Only the send queued by the last file of
 * a burst finds the client quiet, and mails the lot. A fixed delay without
 * this grouped only the first two minutes of a long folder upload and sent
 * one email per file after that.
 *
 * Never held longer than MAX_HOLD_SECONDS, so a client uploading steadily
 * for an hour still produces an email every quarter of an hour rather
 * than one at the end.
 */
class SendClientUploadMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_HOLD_SECONDS = 900;

    /**
     * Allowance for a queue that runs a delayed job a moment early, so the
     * send queued by the last file is never mistaken for one mid-burst.
     */
    private const SLACK_SECONDS = 5;

    public function __construct(
        private readonly int $clientId,
        /** Send what is waiting now, without standing down. For a queue that cannot delay. */
        private readonly bool $immediate = false,
    ) {}

    public function handle(Settings $settings, ClientUploadMail $mail): void
    {
        // Taken and deleted under a lock before anything is sent, so two
        // sends running together cannot both mail the same files. Sending
        // is itself queued, so it happens after this commits.
        /** @var Collection<int, PendingNotification>|null $items */
        $items = DB::transaction(function (): ?Collection {
            $pending = PendingNotification::query()
                ->where('user_id', $this->clientId)
                ->where('type', ClientUploadMail::TYPE)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($pending->isEmpty()) {
                return null;
            }

            $quietSince = now()->subSeconds(ClientUploadMail::DEBOUNCE_SECONDS - self::SLACK_SECONDS);
            $stillUploading = $pending->contains(fn (PendingNotification $row): bool => $row->created_at !== null && $row->created_at->greaterThan($quietSince));
            $heldTooLong = $pending->contains(fn (PendingNotification $row): bool => $row->created_at !== null && $row->created_at->lessThan(now()->subSeconds(self::MAX_HOLD_SECONDS)));

            // A later upload queued its own send; that one will take these.
            if ($stillUploading && ! $heldTooLong && ! $this->immediate) {
                return null;
            }

            PendingNotification::query()->whereIn('id', $pending->pluck('id'))->delete();

            return $pending;
        });

        if ($items === null) {
            return;
        }

        // Asked again at send time: email may have been switched off, or
        // the list emptied, during the wait.
        if ($settings->get(Setting::EmailNotificationsEnabled) !== true) {
            return;
        }

        $client = User::query()->find($this->clientId);

        if ($client === null) {
            return;
        }

        foreach ($mail->addresses() as $address) {
            if ($items->count() === 1) {
                /** @var PendingNotification $item */
                $item = $items->first();
                $notification = new AdminClientUploadedNotification($client->name, $item->subject_name, (int) ($item->context['file_id'] ?? 0));
            } else {
                $notification = new AdminClientUploadDigestNotification($client->name, $client->id, array_values($items->all()));
            }

            Notification::route('mail', $address)->notify($notification);
        }
    }
}
