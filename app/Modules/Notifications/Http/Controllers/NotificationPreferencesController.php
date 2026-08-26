<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\NotificationPreference;
use App\Modules\Notifications\NotificationPreferences;
use App\Modules\Notifications\NotificationTypeDefinition;
use App\Modules\Notifications\NotificationTypeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-user "also email me for this" toggles — every account (staff or
 * client) manages their own, hence living under settings/* alongside
 * profile/password/two-factor rather than system/settings/*.
 */
class NotificationPreferencesController extends Controller
{
    public function __construct(
        private readonly NotificationTypeRegistry $types,
        private readonly NotificationPreferences $preferences,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        return Inertia::render('settings/notifications', [
            'types' => array_map(fn (NotificationTypeDefinition $type): array => [
                'key' => $type->key,
                'label' => $type->label,
                'email_enabled' => $this->preferences->emailEnabledFor($user, $type),
            ], $this->emailable()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            // Against the registry, not merely "a string": a preference row
            // for a type nothing can send is a row that will never be read
            // again, and the screen only ever offers back what edit() gave
            // it.
            'preferences.*.type' => ['required', 'string', Rule::in($this->emailableKeys())],
            'preferences.*.email_enabled' => ['required', 'boolean'],
        ]);

        foreach ($validated['preferences'] as $preference) {
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'type' => $preference['type']],
                ['email_enabled' => $preference['email_enabled']],
            );
        }

        return back();
    }

    /**
     * Only types that can email at all have anything to opt in or out of —
     * a pure in-app type has no toggle to show. Either route counts:
     * Notifier sending a mail class directly, or the digest buffering and
     * sending one.
     *
     * Shared by both halves on purpose, so what the screen offers and what
     * it accepts back cannot drift apart.
     *
     * @return list<NotificationTypeDefinition>
     */
    private function emailable(): array
    {
        return array_values(array_filter(
            $this->types->all(),
            fn (NotificationTypeDefinition $type): bool => $type->mailNotification !== null || $type->digestMail !== null,
        ));
    }

    /**
     * @return list<string>
     */
    private function emailableKeys(): array
    {
        return array_map(fn (NotificationTypeDefinition $type): string => $type->key, $this->emailable());
    }
}
