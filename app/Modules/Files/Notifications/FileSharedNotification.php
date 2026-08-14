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
 * Sent to a client (or every member of a group) the moment a file or
 * folder is assigned to them — v1's "new file" notification. Rendered
 * in the recipient's own locale via User::preferredLocale().
 */
class FileSharedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    /**
     * Built two ways: directly, and by SendNotificationDigest when a
     * recipient's burst turned out to be a single item — hence the named
     * `item` route in from(), which is how the digest constructs it.
     */
    public function __construct(
        private readonly string $itemName,
        private readonly bool $isFolder,
    ) {}

    public static function from(PendingNotification $item): self
    {
        return new self($item->subject_name, (bool) ($item->context['is_folder'] ?? false));
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
        $slot = $this->isFolder ? EmailTemplateSlot::FolderShared : EmailTemplateSlot::FileShared;

        if (($override = $this->overrideOrNull($slot)) !== null) {
            return $this->mailFromOverride($override, [':name' => $this->itemName])
                ->action(__('View your files'), route('my-files.index'));
        }

        $subject = $this->isFolder ? __('A folder has been shared with you') : __('A file has been shared with you');
        $line = $this->isFolder
            ? __('The folder ":name" has been shared with you.', ['name' => $this->itemName])
            : __('The file ":name" has been shared with you.', ['name' => $this->itemName]);

        return (new MailMessage)
            ->subject($subject)
            ->line($line)
            ->action(__('View your files'), route('my-files.index'));
    }
}
