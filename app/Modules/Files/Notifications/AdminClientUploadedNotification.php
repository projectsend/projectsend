<?php

declare(strict_types=1);

namespace App\Modules\Files\Notifications;

use App\Modules\Platform\Notifications\Concerns\RendersOverridableMail;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every configured admin recipient (Setting::AdminNotificationEmails)
 * when a client uploads a file via the portal. Sent on-demand, one per
 * address, so admin addresses aren't exposed to each other.
 */
class AdminClientUploadedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    public function __construct(
        private readonly string $clientName,
        private readonly string $fileName,
        private readonly int $fileId,
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
        if (($override = $this->overrideOrNull(EmailTemplateSlot::AdminClientUploaded)) !== null) {
            return $this->mailFromOverride($override, [':file' => $this->fileName, ':client' => $this->clientName])
                ->action(__('View file'), route('files.edit', $this->fileId));
        }

        return (new MailMessage)
            ->subject(__('A client uploaded a file'))
            ->line(__('The file ":file" was uploaded by :client.', ['file' => $this->fileName, 'client' => $this->clientName]))
            ->action(__('View file'), route('files.edit', $this->fileId));
    }
}
