<?php

declare(strict_types=1);

namespace App\Modules\Files\Notifications;

use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use App\Modules\Platform\Notifications\Concerns\RendersOverridableMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One email for a burst of uploads by one client, to an address in
 * Setting::AdminNotificationEmails (#1840). Uploading a folder of 200
 * drawings used to send 200 copies of AdminClientUploadedNotification.
 *
 * Collected by ClientUploadMail and sent by SendClientUploadMail. A burst
 * of one still goes out as AdminClientUploadedNotification, so a single
 * upload reads exactly as it always has.
 *
 * Like FileShareDigestNotification, the customizable template is the intro
 * line only; every file name is appended after it as its own line.
 */
class AdminClientUploadDigestNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    /**
     * @param  list<PendingNotification>  $items
     */
    public function __construct(
        private readonly string $clientName,
        private readonly int $clientId,
        private readonly array $items,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->items);

        if (($override = $this->overrideOrNull(EmailTemplateSlot::AdminClientUploadDigest)) !== null) {
            $message = $this->mailFromOverride($override, [':client' => $this->clientName, ':count' => (string) $count]);
        } else {
            $message = (new MailMessage)
                ->subject(__(':client uploaded :count files', ['client' => $this->clientName, 'count' => $count]))
                ->line(__(':client uploaded these :count files:', ['client' => $this->clientName, 'count' => $count]));
        }

        foreach ($this->items as $item) {
            $message->line(__('File: :name', ['name' => $item->subject_name]));
        }

        // The library filtered to this client's uploads: every file in the
        // list, in one place, behind the usual sign-in.
        return $message->action(__('View the files'), route('files.index', ['uploader' => $this->clientId]));
    }
}
