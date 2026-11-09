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
 */
class SendClientUploadMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $clientId,
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
