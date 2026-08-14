<?php

declare(strict_types=1);

namespace App\Modules\Clients\Notifications;

use App\Modules\Platform\Notifications\Concerns\RendersOverridableMail;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when staff create a client account directly (ClientsController::store).
 * No set-password link — staff already chose the password in that flow,
 * unlike self-registration approval which reuses the same login-link shape.
 */
class ClientWelcomeNotification extends Notification implements ShouldQueue
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
        if (($override = $this->overrideOrNull(EmailTemplateSlot::ClientWelcome)) !== null) {
            return $this->mailFromOverride($override, [])->action(__('Log in'), route('login'));
        }

        return (new MailMessage)
            ->subject(__('Welcome'))
            ->line(__('An account has been created for you. You can log in now.'))
            ->action(__('Log in'), route('login'));
    }
}
