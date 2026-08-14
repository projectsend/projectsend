<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use App\Modules\Platform\Notifications\Concerns\RendersOverridableMail;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the account whose second factor an administrator just removed.
 *
 * This is not a courtesy. Removing somebody's second factor is the one
 * support action that also happens to be the last step of an account
 * takeover, so the account holder is told every time — if they did not
 * ask for it, this email is how they find out.
 */
class TwoFactorResetNotification extends Notification implements ShouldQueue
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
        if (($override = $this->overrideOrNull(EmailTemplateSlot::TwoFactorReset)) !== null) {
            return $this->mailFromOverride($override, [])->action(__('Log in'), route('login'));
        }

        return (new MailMessage)
            ->subject(__('Two-factor authentication was removed from your account'))
            ->line(__('An administrator removed two-factor authentication from your account. You can set it up again from your security settings. Contact your administrator if this was not expected.'))
            ->action(__('Log in'), route('login'));
    }
}
