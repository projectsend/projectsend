<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Models\User;
use App\Modules\Notifications\Jobs\SendNotificationDigest;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * Collapses a burst of the same kind of notification to the same person
 * into one email.
 *
 * Sharing five files with a client one at a time used to mean five emails;
 * so would answering five comments. This debounces both, without needing a
 * "is a send already scheduled" flag — see SendNotificationDigest for why.
 *
 * Generalised from the file-share pipeline it grew out of. What made it
 * share-specific was the payload, not the mechanism: it is keyed by
 * notification type now, and asks the registry which mail class to use for
 * one item and which for several.
 *
 * **Like Notifier, this performs no authorization.** `$recipients` must
 * already be the exact, permission-checked list — the same contract, for
 * the same reason. It *does* apply the two email gates (the installation's
 * master switch and the recipient's own preference), because those are
 * questions about email rather than about who may see a thing, and every
 * caller was otherwise repeating them.
 */
class NotificationDigester
{
    /**
     * How long a recipient's pending items wait for more to accumulate.
     * Deliberately short — this is about collapsing a burst a few seconds
     * apart, not batching over an afternoon.
     */
    private const DEBOUNCE_SECONDS = 120;

    public function __construct(
        private readonly NotificationTypeRegistry $types,
        private readonly NotificationPreferences $preferences,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  iterable<User>  $recipients  Already permission-checked by the caller.
     * @param  array<string, mixed>  $context  Whatever the mail class needs.
     */
    public function queue(string $typeKey, iterable $recipients, string $subjectName, array $context = []): void
    {
        $type = $this->types->get($typeKey);

        if ($type?->digestMail === null) {
            return;
        }

        if ($this->settings->get(Setting::EmailNotificationsEnabled) !== true) {
            return;
        }

        foreach ($recipients as $recipient) {
            if (! $this->preferences->emailEnabledFor($recipient, $type)) {
                continue;
            }

            PendingNotification::query()->create([
                'user_id' => $recipient->getKey(),
                'type' => $type->key,
                'subject_name' => $subjectName,
                'context' => $context === [] ? null : $context,
            ]);

            SendNotificationDigest::dispatch($recipient->getKey(), $type->key)
                ->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
        }
    }
}
