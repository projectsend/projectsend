<?php

declare(strict_types=1);

namespace App\Modules\Groups\Notifications;

use App\Modules\Platform\Notifications\Concerns\RendersOverridableMail;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GroupMembershipDeniedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    public function __construct(
        private readonly string $groupName,
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
        if (($override = $this->overrideOrNull(EmailTemplateSlot::GroupMembershipDenied)) !== null) {
            return $this->mailFromOverride($override, [':group' => $this->groupName]);
        }

        return (new MailMessage)
            ->subject(__('Your group membership request was denied'))
            ->line(__('Your request to join the group ":group" has been denied.', ['group' => $this->groupName]));
    }
}
