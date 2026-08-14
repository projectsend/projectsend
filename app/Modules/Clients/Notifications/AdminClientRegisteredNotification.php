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
 * Sent to every configured admin recipient (Setting::AdminNotificationEmails)
 * when a client self-registers. Sent on-demand, one per address, so admin
 * addresses aren't exposed to each other in a shared To: header.
 */
class AdminClientRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    public function __construct(
        private readonly string $name,
        private readonly string $email,
        private readonly bool $pendingApproval,
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
        $override = $this->overrideOrNull(EmailTemplateSlot::AdminClientRegistered);

        $message = $override !== null
            ? $this->mailFromOverride($override, [':name' => $this->name, ':email' => $this->email])
            : (new MailMessage)
                ->subject(__('A new client has registered'))
                ->line(__('A new client account was created: :name (:email).', ['name' => $this->name, 'email' => $this->email]));

        // The pending-approval notice and its action stay code-controlled
        // regardless of a customized template, same as every other action.
        if ($this->pendingApproval) {
            $message->line(__('Their account is waiting for approval.'))
                ->action(__('Review pending requests'), route('account-requests.index'));
        }

        return $message;
    }
}
