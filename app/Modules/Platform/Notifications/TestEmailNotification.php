<?php

declare(strict_types=1);

namespace App\Modules\Platform\Notifications;

use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent only by the "Send test email" button on the Email settings page,
 * so an admin can verify SMTP works before turning notifications on.
 */
class TestEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteName = app(Settings::class)->get(Setting::SiteName);
        $siteName = is_string($siteName) ? $siteName : 'ProjectSend';

        return (new MailMessage)
            ->subject(__('Test email from :site', ['site' => $siteName]))
            ->line(__('This is a test email from :site.', ['site' => $siteName]))
            ->line(__('If you received this, your email configuration is working.'));
    }
}
