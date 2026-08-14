<?php

declare(strict_types=1);

namespace App\Modules\Platform\Theming;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\View;

/**
 * Renders a MailMessage to HTML through a specific theme, for previewing
 * only — never for an actual send. Shares `mail_theme`/`mail_theme_context`
 * the same way ThemedMailChannel does at real send time, so a premium
 * theme's extra context (e.g. Branded's logo) shows exactly as it would
 * in production. Used both for previewing a theme itself
 * (ThemingSettingsController) and for previewing a specific template's
 * effective content under the currently active theme
 * (EmailTemplatesController).
 */
class EmailPreviewRenderer
{
    public function __construct(
        private readonly EmailThemeRegistry $themes,
    ) {}

    public function render(MailMessage $message, string $themeKey): string
    {
        $context = $this->themes->get($themeKey)?->context;

        View::share('mail_theme', $themeKey);
        View::share('mail_theme_context', $context === null ? [] : $context());

        return (string) $message->theme($themeKey)->render();
    }
}
