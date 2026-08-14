<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Models\User;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Dispatches notifications: always writes an in-app row, and optionally
 * sends the type's mail companion when the master switch and the
 * recipient's own preference both allow it. Mirrors ActivityLogger's
 * shape (a small service, no state beyond its dependencies).
 *
 * SECURITY CONTRACT — read before calling send() from new code: this
 * class performs NO authorization of its own. $recipients must already
 * be the exact, permission-checked list the caller would otherwise pass
 * to $user->notify() directly — the same list FileAssignmentsController
 * resolves before calling FileShareDigester::queue(), for example. Never
 * pass a broad query (User::all(), an unfiltered role lookup, etc.) —
 * centralizing "who can see this subject" here would require this
 * shared module to know every feature's distinct access model, and any
 * attempt to re-derive it here risks silently drifting out of sync with
 * the real check that already lives next to each feature's own policy.
 * Every call site must resolve recipients itself first.
 */
class Notifier
{
    public function __construct(
        private readonly NotificationTypeRegistry $types,
        private readonly NotificationPreferences $preferences,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  iterable<User>  $recipients  Already permission-checked by the caller.
     * @param  array<string, mixed>  $data  Template placeholders; also passed as named
     *                                      constructor arguments to $type->mailNotification when one is dispatched —
     *                                      keys must match that class's constructor parameter names exactly.
     */
    public function send(string $typeKey, iterable $recipients, ?Model $subject = null, array $data = []): void
    {
        $type = $this->types->get($typeKey) ?? throw new InvalidArgumentException("Unknown notification type: {$typeKey}");

        foreach ($recipients as $recipient) {
            InAppNotification::query()->create([
                'user_id' => $recipient->getKey(),
                'type' => $type->key,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'data' => $data,
                'created_at' => now(),
            ]);

            if ($type->mailNotification !== null && $this->shouldEmail($recipient, $type)) {
                $recipient->notify(app($type->mailNotification, $data));
            }
        }
    }

    private function shouldEmail(User $recipient, NotificationTypeDefinition $type): bool
    {
        if ($this->settings->get(Setting::EmailNotificationsEnabled) !== true) {
            return false;
        }

        return $this->preferences->emailEnabledFor($recipient, $type);
    }
}
