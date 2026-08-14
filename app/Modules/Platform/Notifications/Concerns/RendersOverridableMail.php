<?php

declare(strict_types=1);

namespace App\Modules\Platform\Notifications\Concerns;

use App\Modules\Platform\Notifications\EmailTemplateResolver;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Shared by every templated Notification class. An override is raw,
 * global text (not translated, not per-locale, matching v1) — the
 * default, non-customized path is untouched and keeps calling __() as
 * before. The action button (if any) is always appended by the caller
 * after this returns; it's never part of the customizable body.
 */
trait RendersOverridableMail
{
    /**
     * @return array{subject: string, body: string}|null
     */
    protected function overrideOrNull(EmailTemplateSlot $slot): ?array
    {
        return app(EmailTemplateResolver::class)->resolve($slot);
    }

    /**
     * @param  array{subject: string, body: string}  $override
     * @param  array<string, string>  $replacements
     */
    protected function mailFromOverride(array $override, array $replacements): MailMessage
    {
        $message = (new MailMessage)->subject(strtr($override['subject'], $replacements));

        foreach (explode("\n\n", trim($override['body'])) as $paragraph) {
            if ($paragraph !== '') {
                $message->line(strtr($paragraph, $replacements));
            }
        }

        return $message;
    }
}
