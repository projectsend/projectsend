<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use App\Models\User;
use App\Modules\Files\Jobs\SendClientUploadMail;
use App\Modules\Files\Models\File;
use App\Modules\Notifications\PendingBatch;
use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * The "a client uploaded a file" email to the addresses in
 * Setting::AdminNotificationEmails, sent once per burst instead of once
 * per file (#1840). A client dropping a folder of 200 drawings into the
 * portal used to send each of those addresses 200 emails.
 *
 * Each upload is written down and a send is queued for a couple of minutes
 * later. The first send to run takes everything that client uploaded in
 * the meantime and mails it as one message; the sends after it find
 * nothing left. The same shape as NotificationDigester, which cannot be
 * used directly because these recipients are bare addresses, not accounts
 * with preferences.
 *
 * The rows live in pending_notifications under TYPE, keyed by the client
 * who uploaded rather than by a recipient — there is no account to key
 * them by — and SendNotificationDigest never handles this type, because
 * it is never queued for it.
 */
final class ClientUploadMail
{
    public const TYPE = 'admin_client_upload_mail';

    /**
     * Long enough for a folder upload to finish, short enough that a
     * single file is still news.
     */
    public const DEBOUNCE_SECONDS = PendingBatch::QUIET_SECONDS;

    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function queue(User $client, File $file): void
    {
        if ($this->settings->get(Setting::EmailNotificationsEnabled) !== true || $this->addresses() === []) {
            return;
        }

        PendingNotification::query()->create([
            'user_id' => $client->id,
            'type' => self::TYPE,
            'subject_name' => $file->name,
            'context' => ['file_id' => $file->id],
        ]);

        SendClientUploadMail::dispatch($client->id, PendingBatch::queueCannotWait())->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
    }

    /**
     * @return list<string>
     */
    public function addresses(): array
    {
        $addresses = $this->settings->get(Setting::AdminNotificationEmails);

        return is_array($addresses) ? array_values(array_filter($addresses, 'is_string')) : [];
    }
}
