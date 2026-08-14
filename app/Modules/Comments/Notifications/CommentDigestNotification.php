<?php

declare(strict_types=1);

namespace App\Modules\Comments\Notifications;

use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Notifications\Concerns\RendersOverridableMail;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent instead of individual CommentPostedNotification emails when two or
 * more comments reached the same recipient inside one debounce window —
 * which is the case this pipeline exists for, since answering several
 * comments in a row is exactly how a staff member works through them.
 *
 * Lists which files were commented on and by whom, never what was said,
 * for the same reason the single version does not.
 */
class CommentDigestNotification extends Notification implements ShouldQueue
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

        $message = ($override = $this->overrideOrNull(EmailTemplateSlot::CommentDigest)) !== null
            ? $this->mailFromOverride($override, [':count' => (string) $count])
            : (new MailMessage)
                ->subject(__(':count new comments', ['count' => $count]))
                ->line(__('There are :count new comments for you:', ['count' => $count]));

        foreach ($this->items as $item) {
            $message->line(__(':author commented on ":name"', [
                'author' => (string) ($item->context['author_name'] ?? __('Someone')),
                'name' => $item->subject_name,
            ]));
        }

        return $message->action(__('View your files'), route('my-files.index'));
    }
}
