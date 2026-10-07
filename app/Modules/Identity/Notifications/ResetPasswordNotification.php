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
        public readonly bool $firstPassword = false,
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

        // The same link, for an account that signs in through a provider and
        // has never had a password: this is now the only way it gets one, so
        // an email that speaks of a reset nobody asked for is one people
        // ignore. Not taken from the customisable reset template for the
        // same reason, since that text is written about resetting.
        if ($this->firstPassword) {
            return (new MailMessage)
                ->subject(__('Set your password'))
                ->line(__('Use the button below to choose a password for your account. Until now you have signed in through a connected account, such as Google or Microsoft.'))
                ->action(__('Set a password'), $url)
                ->line(__('This link will expire in :count minutes.', ['count' => $expireMinutes]))
                ->line(__('If you did not ask for this, no further action is required: you can keep signing in the way you do now.'));
        }

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
