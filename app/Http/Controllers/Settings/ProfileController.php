<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\ClientFieldContext;
use App\Modules\Clients\ClientPortalCustomFields;
use App\Modules\Identity\Erasure\ErasureSchedule;
use App\Modules\Platform\Localization\TimezoneRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ClientPortalCustomFields $customFields,
        private readonly TimezoneRegistry $timezones,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            // Resolved, so the picker shows the zone dates are actually
            // being rendered in — which for most people is the one their
            // browser was detected as, not something they ever chose.
            'timezone' => $this->timezones->resolve($user),
            'timezones' => $this->timezones->options(),
            'custom_fields' => $user->isClient() ? $this->customFields->rows(ClientFieldContext::AccountEdit, $user) : [],
            'custom_field_values' => $user->isClient() ? $this->customFields->values(ClientFieldContext::AccountEdit, $user) : [],
        ]);
    }

    /**
     * The account-deletion screen.
     *
     * Its own page rather than a block under the profile form: this is
     * the one irreversible action a person can take on themselves, and it
     * should be somewhere you navigate to on purpose instead of somewhere
     * you scroll past on the way to saving your email address. The delete
     * itself still goes to destroy() below.
     */
    public function deleteAccount(): Response
    {
        return Inertia::render('settings/delete-account', [
            'erasureGraceDays' => (int) app(Settings::class)->get(Setting::AccountErasureGraceDays),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $validated = $request->validated();
        $customFieldValues = $validated['custom_field_values'] ?? [];
        unset($validated['custom_field_values']);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->isClient()) {
            $this->customFields->save($user, ClientFieldContext::AccountEdit, $customFieldValues);
        }

        app(ActivityLogger::class)->log(Action::ProfileUpdated, $user);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        assert($user !== null);

        Auth::logout();

        // Self-deletion: soft delete now, permanent GDPR erasure after
        // the disclosed grace period (Setting::AccountErasureGraceDays).
        app(ErasureSchedule::class)->apply($user);
        $user->delete();

        app(ActivityLogger::class)->log(Action::UserDeleted, $user, context: ['name' => $user->name]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
