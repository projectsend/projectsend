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
 * Sent on-demand (Notification::route('mail', ...)), never via
 * $client->notify() — there is no account yet to notify, only an address
 * somebody typed into the invite form.
 */
class ClientInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    public function __construct(
        private readonly string $name,
        private readonly string $token,
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
        $url = route('invitations.show', $this->token);

        if (($override = $this->overrideOrNull(EmailTemplateSlot::ClientInvited)) !== null) {
            return $this->mailFromOverride($override, [':name' => $this->name])->action(__('Register'), $url);
        }

        return (new MailMessage)
            ->subject(__("You've been invited to register"))
            ->greeting(__('Hello :name,', ['name' => $this->name]))
            ->line(__("You've been invited to register a client account. The link below will let you set your own password."))
            ->action(__('Register'), $url);
    }
}
