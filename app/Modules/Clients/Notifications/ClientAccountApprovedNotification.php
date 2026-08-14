<?php

declare(strict_types=1);

namespace App\Modules\Clients\Notifications;

use App\Modules\Platform\Notifications\Concerns\RendersOverridableMail;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientAccountApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (($override = $this->overrideOrNull(EmailTemplateSlot::ClientAccountApproved)) !== null) {
            return $this->mailFromOverride($override, [])->action(__('Log in'), route('login'));
        }

        return (new MailMessage)
            ->subject(__('Your account has been approved'))
            ->line(__('Your account request has been approved. You can now log in.'))
            ->action(__('Log in'), route('login'));
    }
}
