<?php

declare(strict_types=1);

namespace App\Modules\Files\Notifications;

use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Notifications\Concerns\RendersOverridableMail;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent instead of individual FileSharedNotification emails when two or
 * more files/folders were shared with the same recipient inside one
 * SendNotificationDigest debounce window — see NotificationDigester. Each
 * item's name is always appended as its own line after the (possibly
 * customized) intro; the item list itself is never part of the
 * customizable body, same reasoning as the action button.
 */
class FileShareDigestNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

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

        if (($override = $this->overrideOrNull(EmailTemplateSlot::FileShareDigest)) !== null) {
            $message = $this->mailFromOverride($override, [':count' => (string) $count]);
        } else {
            $message = (new MailMessage)
                ->subject(__(':count items have been shared with you', ['count' => $count]))
                ->line(__('The following :count items have been shared with you:', ['count' => $count]));
        }

        foreach ($this->items as $item) {
            $message->line(($item->context['is_folder'] ?? false)
                ? __('Folder: :name', ['name' => $item->subject_name])
                : __('File: :name', ['name' => $item->subject_name]));
        }

        return $message->action(__('View your files'), route('my-files.index'));
    }
}
