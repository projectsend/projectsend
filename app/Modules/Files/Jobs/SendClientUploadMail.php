<?php

declare(strict_types=1);

namespace App\Modules\Files\Jobs;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Notifications\AdminClientUploadDigestNotification;
use App\Modules\Files\Notifications\AdminClientUploadedNotification;
use App\Modules\Files\Uploads\ClientUploadMail;
use App\Modules\Notifications\PendingBatch;
use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Settings\StorageLocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Sends whatever one client has uploaded since the last send, as one email
 * per admin address. See ClientUploadMail.
 *
 * Every upload queues one of these, and each stands down while the client
 * is still uploading. The rule is PendingBatch's, shared with the share
 * and comment digests.
 */
class SendClientUploadMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_HOLD_SECONDS = PendingBatch::MAX_HOLD_SECONDS;

    public function __construct(
        private readonly int $clientId,
        /** Send what is waiting now, without standing down. For a queue that cannot delay. */
        private readonly bool $immediate = false,
    ) {}

    public function handle(Settings $settings, ClientUploadMail $mail): void
    {
        $items = PendingBatch::takeWhenQuiet($this->clientId, ClientUploadMail::TYPE, $this->immediate);

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

        // Where each file went, when the storage screen asked for the
        // email to say. Read here rather than when the upload landed, like
        // the switches above: the setting may have changed during the wait.
        $locationNames = $settings->get(Setting::UploadNotificationNamesLocation) === true
            ? $this->locationNames($items)
            : [];

        foreach ($mail->addresses() as $address) {
            if ($items->count() === 1) {
                /** @var PendingNotification $item */
                $item = $items->first();
                $fileId = (int) ($item->context['file_id'] ?? 0);
                $notification = new AdminClientUploadedNotification($client->name, $item->subject_name, $fileId, $locationNames[$fileId] ?? null);
            } else {
                $notification = new AdminClientUploadDigestNotification($client->name, $client->id, array_values($items->all()), $locationNames);
            }

            Notification::route('mail', $address)->notify($notification);
        }
    }

    /**
     * The storage location each file was written to, by file id, for the
     * files that still exist and sit on a location that is still known.
     *
     * @param  Collection<int, PendingNotification>  $items
     * @return array<int, string>
     */
    private function locationNames(Collection $items): array
    {
        $fileIds = $items
            ->map(fn (PendingNotification $item): int => (int) ($item->context['file_id'] ?? 0))
            ->filter()
            ->values()
            ->all();

        if ($fileIds === []) {
            return [];
        }

        /** @var Collection<int, string> $disks */
        $disks = File::withTrashed()->whereKey($fileIds)->pluck('disk', 'id');
        /** @var Collection<string, string> $names */
        $names = StorageLocation::query()->whereIn('disk', $disks->unique()->values()->all())->pluck('name', 'disk');

        return $disks
            ->map(fn (string $disk): ?string => $names->get($disk))
            ->filter()
            ->all();
    }
}
