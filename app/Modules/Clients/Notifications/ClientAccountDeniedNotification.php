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
 * $client->notify() — the account row is force-deleted in the same
 * request, and a queued job re-fetching a deleted model would fail.
 */
class ClientAccountDeniedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    public function __construct(
        private readonly string $name,
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
        if (($override = $this->overrideOrNull(EmailTemplateSlot::ClientAccountDenied)) !== null) {
            return $this->mailFromOverride($override, [':name' => $this->name]);
        }

        return (new MailMessage)
            ->subject(__('Your account request was denied'))
            ->greeting(__('Hello :name,', ['name' => $this->name]))
            ->line(__('Your account request has been denied.'));
    }
}
