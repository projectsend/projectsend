<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Theming\EmailPreviewRenderer;
use App\Modules\Platform\Theming\EmailThemeRegistry;
use App\Modules\Platform\Theming\PublicThemeRegistry;
use App\Modules\Platform\Theming\ThemeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Setting::Theme (shared by the guest public pages and the client
 * portal — see PublicGroupsController / MyFilesController) and
 * Setting::EmailTheme (the outgoing-notification header/footer skin —
 * see ThemedMailChannel) are both single, global, non-per-user settings,
 * matching v1. Only ever offers currently-available() themes: a premium
 * theme this edition lacks simply never appears as an option, and a
 * stored key that's no longer available resolves to "default" rather
 * than rejecting the save.
 */
class ThemingSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly PublicThemeRegistry $publicThemes,
        private readonly EmailThemeRegistry $emailThemes,
        private readonly CapabilityRegistry $capabilities,
        private readonly EmailPreviewRenderer $preview,
    ) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('system/settings/theming', [
            'theme' => $this->settings->get(Setting::Theme),
            'email_theme' => $this->settings->get(Setting::EmailTheme),
            'themes' => $this->options($this->publicThemes, previewDir: null),
            'email_themes' => $this->options($this->emailThemes, previewDir: 'email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'max:255'],
            'email_theme' => ['required', 'string', 'max:255'],
        ]);

        $this->settings->set(Setting::Theme, $this->publicThemes->resolve($validated['theme'], $this->capabilities));
        $this->settings->set(Setting::EmailTheme, $this->emailThemes->resolve($validated['email_theme'], $this->capabilities));

        // The long-running queue worker cached the old EmailTheme at boot
        // (or at its first read since); this signals it to restart so
        // queued notifications pick up the new theme without a manual
        // container restart — same reasoning as EmailSettingsController.
        Artisan::call('queue:restart');

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'theming']);

        return back()->with('success', __('Theme updated.'));
    }

    /**
     * Renders a real sample email through the requested theme — bypassing
     * Setting::EmailTheme entirely, so any available theme can be
     * previewed without touching what's actually live — via the same
     * MailMessage/Markdown pipeline every real notification uses (not a
     * static mockup), so a premium theme's extra context (e.g. Branded's
     * logo) shows exactly as it would in a real send. 404s for a theme
     * this edition/install doesn't have, same as an unavailable key
     * elsewhere in theming.
     */
    public function previewEmail(string $key): HttpResponse
    {
        abort_unless($this->emailThemes->resolve($key, $this->capabilities) === $key, 404);

        $definition = $this->emailThemes->get($key);
        $label = $definition === null ? $key : $definition->label;

        $message = (new MailMessage)
            ->subject(__('Sample email'))
            ->greeting(__('Hello!'))
            ->line(__('This is a preview of the :theme email theme.', ['theme' => $label]))
            ->action(__('Call to action'), url('/'))
            ->line(__('This is a sample email — no notification was actually sent.'));

        return new HttpResponse($this->preview->render($message, $key), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * $previewDir is null for the public/portal registry (files land
     * directly under images/theme-previews/) or a subdirectory name for
     * any other registry — email themes use 'email', keeping their PNGs
     * out of the public/portal namespace since the two registries share
     * some keys (e.g. 'default', 'branded').
     *
     * @return list<array{key: string, label: string, description: string, preview_url: string|null, preview_url_dark: string|null}>
     */
    private function options(ThemeRegistry $registry, ?string $previewDir): array
    {
        return array_map(
            fn ($theme): array => [
                'key' => $theme->key,
                'label' => $theme->label,
                'description' => $theme->description,
                'preview_url' => $this->previewUrl($theme->key, $previewDir),
                'preview_url_dark' => $this->previewUrl($theme->key, $previewDir, dark: true),
            ],
            $registry->available($this->capabilities),
        );
    }

    /**
     * A real screenshot under this theme — the public/portal set is a
     * "My files" capture against a demo client with actual content (see
     * the maintainers' screenshot tooling, and why an empty account can't
     * be used to generate these); the email set is a capture of
     * `previewEmail()`'s rendered sample message (see
     * that same tooling). Null
     * (falls back to a placeholder in the UI, or to the light capture for
     * `dark: true` — see `ThemeCard` in theming.tsx) until the screenshot
     * has actually been captured for this key, so a newly-registered
     * theme never shows a broken image.
     *
     * `dark: true` looks for a `-dark` suffixed file alongside the normal
     * one. Public/portal captures always have one (the app's own light/dark
     * toggle actually changes how those pages render); email captures
     * generally don't — the rendered mail HTML doesn't read the app's
     * appearance setting, so there's nothing distinct to capture — and
     * fall back to the light image via the same `??` in the frontend.
     */
    private function previewUrl(string $key, ?string $dir, bool $dark = false): ?string
    {
        $suffix = $dark ? '-dark' : '';
        $relative = $dir === null ? "images/theme-previews/{$key}{$suffix}.png" : "images/theme-previews/{$dir}/{$key}{$suffix}.png";

        return is_file(public_path($relative)) ? asset($relative) : null;
    }
}
