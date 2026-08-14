<?php

declare(strict_types=1);

namespace App\Modules\Groups\Notifications;

use App\Modules\Platform\Notifications\Concerns\RendersOverridableMail;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every configured admin recipient (Setting::AdminNotificationEmails)
 * when a client requests to join a group. Sent on-demand, one per
 * address, mirroring AdminClientRegisteredNotification/AdminClientUploadedNotification.
 */
class GroupMembershipRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    public function __construct(
        private readonly string $clientName,
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
        if (($override = $this->overrideOrNull(EmailTemplateSlot::GroupMembershipRequested)) !== null) {
            return $this->mailFromOverride($override, [':client' => $this->clientName, ':group' => $this->groupName])
                ->action(__('Review membership requests'), route('membership-requests.index'));
        }

        return (new MailMessage)
            ->subject(__('A client requested to join a group'))
            ->line(__('New membership request from :client for the group ":group".', ['client' => $this->clientName, 'group' => $this->groupName]))
            ->action(__('Review membership requests'), route('membership-requests.index'));
    }
}
