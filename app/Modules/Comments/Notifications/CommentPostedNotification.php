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
 * A single comment, emailed once the digest window closed with only one
 * thing in it. Sent by SendNotificationDigest, never by Notifier — see
 * NotificationTypeDefinition's $digestMail.
 *
 * **The comment's body is deliberately not in the email.** A comment can
 * be visible to one client only, and email is forwarded, quoted and left
 * open on screens. This says something was said and where; the reader
 * opens the file and reads it under the same rules as everyone else.
 */
class CommentPostedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    public function __construct(
        private readonly string $fileName,
        private readonly string $authorName,
        private readonly ?int $fileId,
    ) {}

    public static function from(PendingNotification $item): self
    {
        return new self(
            $item->subject_name,
            (string) ($item->context['author_name'] ?? __('Someone')),
            isset($item->context['file_id']) ? (int) $item->context['file_id'] : null,
        );
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
        $replacements = [':name' => $this->fileName, ':author' => $this->authorName];

        $message = ($override = $this->overrideOrNull(EmailTemplateSlot::CommentPosted)) !== null
            ? $this->mailFromOverride($override, $replacements)
            : (new MailMessage)
                ->subject(__('New comment on ":name"', ['name' => $this->fileName]))
                ->line(__(':author commented on ":name".', ['author' => $this->authorName, 'name' => $this->fileName]));

        return $message->action(__('Read the comment'), $this->url());
    }

    /**
     * The deep link resolves per viewer (staff and clients have no page in
     * common), so it is safe to send the same URL to either.
     */
    private function url(): string
    {
        return $this->fileId === null ? route('my-files.index') : route('comments.go', $this->fileId);
    }
}
