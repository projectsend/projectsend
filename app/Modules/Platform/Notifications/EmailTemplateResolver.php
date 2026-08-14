<?php

declare(strict_types=1);

namespace App\Modules\Platform\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the customized subject/body for a slot, if one has been
 * saved — cached like Settings::overrides(), guarded against a fresh
 * `php artisan migrate` querying a table that doesn't exist yet during
 * that same boot cycle.
 */
class EmailTemplateResolver
{
    private const CACHE_KEY = 'platform.email_templates';

    /**
     * @return array{subject: string, body: string}|null
     */
    public function resolve(EmailTemplateSlot $slot): ?array
    {
        return $this->all()[$slot->value] ?? null;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array{subject: string, body: string}>
     */
    private function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            if (! Schema::hasTable('email_templates')) {
                return [];
            }

            return EmailTemplate::query()->get()
                ->mapWithKeys(fn (EmailTemplate $template): array => [
                    $template->slot => ['subject' => $template->subject, 'body' => $template->body],
                ])
                ->all();
        });
    }
}
