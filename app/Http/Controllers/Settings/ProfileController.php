<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\ClientFieldContext;
use App\Modules\Clients\ClientPortalCustomFields;
use App\Modules\Files\DeletedAccountContent;
use App\Modules\Identity\Erasure\ErasureSchedule;
use App\Modules\Identity\Erasure\SelfDeletion;
use App\Modules\Identity\StaffAccounts;
use App\Modules\Identity\StartPage;
use App\Modules\Identity\StartPages;
use App\Modules\Platform\Localization\TimezoneRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ClientPortalCustomFields $customFields,
        private readonly TimezoneRegistry $timezones,
        private readonly StaffAccounts $accounts,
        private readonly StartPages $startPages,
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
            // Stored, not resolved: an empty choice means "follow my role",
            // and the form has to be able to say that rather than show the
            // role's page as if the person had picked it.
            'start_page' => $user->start_page,
            'start_page_options' => $this->startPages->personalOptions($user),
            'role_start_page' => (string) __(($this->startPages->roleDefault($user) ?? StartPage::Dashboard)->label($user->type)),
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
    public function deleteAccount(Request $request): Response
    {
        $user = $request->user();
        $selfDeletion = app(SelfDeletion::class);
        $applies = $user !== null && $selfDeletion->appliesTo($user);

        return Inertia::render('settings/delete-account', [
            'erasureGraceDays' => (int) app(Settings::class)->get(Setting::AccountErasureGraceDays),
            // What happens to their files, said before they confirm. Both
            // follow the account's own type (SelfDeletion::appliesTo), so a
            // staff member on a "clients only" installation is told
            // neither, because neither happens to them.
            'filesWithdrawn' => $applies,
            'filesDeletedImmediately' => $applies && $selfDeletion->deletesFilesImmediately(),
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

        // The rule every other door into this already asks: Staff update(),
        // guardDeletable(), and both role-conversion directions. This one
        // did not, and self-deletion is the one door where the account
        // being removed is certainly signed in — so the last active
        // administrator could take themselves out, leaving no live staff
        // row at all. EnsureSetupIsComplete then reopens first-run setup to
        // anybody who asks, which is the other half of this and is closed
        // below.
        $this->accounts->guardLastAdministrator(
            $user,
            removesAdmin: $this->accounts->isAdministratorRole($user->role_id),
        );

        Auth::logout();

        // Self-deletion: soft delete now, permanent GDPR erasure after
        // the disclosed grace period (Setting::AccountErasureGraceDays).
        //
        // One transaction with the files, for the reason
        // ClientsController::destroy gives: a deletion whose second half
        // failed must not leave the account gone and the files it
        // promised to delete still there.
        DB::transaction(function () use ($user): void {
            app(ErasureSchedule::class)->apply($user);
            $user->delete();

            app(ActivityLogger::class)->log(Action::UserDeleted, $user, context: ['name' => $user->name]);

            // Only what they own, by the rule an administrator's delete
            // uses: their uploads, and their folders only if nothing else
            // is left inside them. See SelfDeletion.
            $selfDeletion = app(SelfDeletion::class);

            if ($selfDeletion->appliesTo($user) && $selfDeletion->deletesFilesImmediately()) {
                $result = app(DeletedAccountContent::class)->cascadeDelete($user);
                app(ActivityLogger::class)->log(Action::AccountContentCascadeDeleted, context: ['name' => $user->name, ...$result]);
            }
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
