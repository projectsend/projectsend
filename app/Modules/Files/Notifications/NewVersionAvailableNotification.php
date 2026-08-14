<?php

declare(strict_types=1);

namespace App\Modules\Files\Notifications;

use App\Modules\Notifications\PendingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to someone who already has a file when a newer version of it is
 * published — the whole point of versioning, since a badge only helps
 * somebody who comes back and looks.
 *
 * No EmailTemplateSlot override: the customizable templates are a fixed,
 * admin-facing list on the settings screen, and adding to it is its own
 * decision rather than a side effect of this feature.
 */
class NewVersionAvailableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $fileName,
        private readonly string $previousName,
    ) {}

    /**
     * How SendNotificationDigest rebuilds this when a recipient's burst
     * turned out to be a single item.
     */
    public static function from(PendingNotification $item): self
    {
        return new self($item->subject_name, (string) ($item->context['previousName'] ?? $item->subject_name));
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('A newer version of a file you have is available'))
            ->line(__('":previous" has been replaced by a newer version: ":name".', [
                'previous' => $this->previousName,
                'name' => $this->fileName,
            ]))
            ->action(__('View your files'), route('my-files.index'));
    }
}
