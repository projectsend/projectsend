<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use App\Modules\Platform\Notifications\Concerns\RendersOverridableMail;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Translated, queued replacement for Laravel's built-in ResetPassword
 * notification (which renders 100% in English with no customization
 * hook). Wired up via User::sendPasswordResetNotification(). Not gated
 * by the "send email notifications" setting — this is a security flow,
 * not a notification preference. The wording IS still customizable via
 * the email template editor, matching v1; only the reset link/action
 * stays fixed.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    public function __construct(
        public readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(CanResetPassword $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        if (($override = $this->overrideOrNull(EmailTemplateSlot::PasswordReset)) !== null) {
            return $this->mailFromOverride($override, [':count' => (string) $expireMinutes])
                ->action(__('Reset Password'), $url);
        }

        return (new MailMessage)
            ->subject(__('Reset Password Notification'))
            ->line(__('You are receiving this email because we received a password reset request for your account.'))
            ->action(__('Reset Password'), $url)
            ->line(__('This password reset link will expire in :count minutes.', ['count' => $expireMinutes]))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }
}
