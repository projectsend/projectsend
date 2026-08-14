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
 * A generic security-style notice — no diff of what changed (avoids
 * exposing e.g. a password-change signal in cleartext) — sent whenever
 * staff edit a client's name, email, active status, or password.
 */
class ClientAccountEditedNotification extends Notification implements ShouldQueue
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
        if (($override = $this->overrideOrNull(EmailTemplateSlot::ClientAccountEdited)) !== null) {
            return $this->mailFromOverride($override, []);
        }

        return (new MailMessage)
            ->subject(__('Your account was updated'))
            ->line(__('Your account details were recently changed by an administrator. Contact your administrator if this was not expected.'));
    }
}
