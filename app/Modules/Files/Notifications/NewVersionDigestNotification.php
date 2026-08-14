<?php

declare(strict_types=1);

namespace App\Modules\Files\Notifications;

use App\Modules\Notifications\PendingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent instead of individual NewVersionAvailableNotification emails when
 * two or more files a recipient holds were revised inside one
 * SendNotificationDigest debounce window — see NotificationDigester.
 *
 * Staff re-issuing a whole set of drawings at once is the ordinary case
 * here, not the exception, so the digest matters more for this type than
 * for most.
 */
class NewVersionDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<PendingNotification>  $items
     */
    public function __construct(
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

        $message = (new MailMessage)
            ->subject(__(':count files you have were replaced by newer versions', ['count' => $count]))
            ->line(__('Newer versions of the following :count files are available:', ['count' => $count]));

        foreach ($this->items as $item) {
            $previous = (string) ($item->context['previousName'] ?? '');

            $message->line($previous === ''
                ? __('File: :name', ['name' => $item->subject_name])
                : __(':previous → :name', ['previous' => $previous, 'name' => $item->subject_name]));
        }

        return $message->action(__('View your files'), route('my-files.index'));
    }
}
