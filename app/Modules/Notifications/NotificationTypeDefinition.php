<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use Closure;

/**
 * One registrable notification type: a key stored on each notification
 * row, a template sentence with :placeholder support (resolved from the
 * row's `data`, same mechanics as Audit's Action::template()), and an
 * optional mail companion.
 *
 * $mailNotification is an existing mail-only Notification class's FQCN
 * (e.g. FileSharedNotification::class) dispatched via $recipient->notify()
 * when the recipient's preference (see NotificationPreferences) allows
 * it — null means this type is in-app only, nothing to email.
 *
 * $url resolves the deep-link route for a notification of this type from
 * its `data` — e.g. fn (array $data) => route('files.edit', $data['file_id']).
 *
 * $digestMail / $digestMailMany are the other way to email: instead of
 * Notifier sending one message per event, NotificationDigester buffers
 * them per recipient and SendNotificationDigest sends $digestMail for a
 * lone item or $digestMailMany for several. Set these *instead of*
 * $mailNotification — setting both would send twice.
 *
 * Both constructors are called with named arguments (`item:` for one,
 * `items:` for many), so the mail class's parameter must be named to
 * match.
 *
 * A type with either of them counts as emailable, so it appears on the
 * per-user preference screen and $defaultEmailEnabled applies to it — the
 * digest path had no toggle at all before, which meant file-share emails
 * could not be turned off by the person receiving them.
 */
final class NotificationTypeDefinition
{
    /**
     * @param  (Closure(array<string, mixed> $data): string)|null  $url
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $template,
        public readonly ?string $mailNotification = null,
        public readonly bool $defaultEmailEnabled = false,
        public readonly ?string $digestMail = null,
        public readonly ?string $digestMailMany = null,
        public readonly ?Closure $url = null,
    ) {}
}
