<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Onboarding\InstallationWelcome;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-run setup: create the initial staff administrator. Only reachable
 * while no staff user exists; afterwards the routes bounce home.
 */
class SetupController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly InstallationWelcome $welcome,
    ) {}

    public function show(): Response|RedirectResponse
    {
        if ($this->setupIsComplete()) {
            return redirect()->route('home');
        }

        return Inertia::render('setup');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->setupIsComplete()) {
            return redirect()->route('home');
        }

        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->settings->set(Setting::SiteName, $validated['site_name']);

        $admin = User::create([
            'type' => UserType::Staff,
            'active' => true,
            'role_id' => Role::query()->where('name', SystemRole::SystemAdministrator->value)->value('id'),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'email_verified_at' => now(),
        ]);

        // v1 logged installation as action 0; setup is a recorded action.
        $this->activity->log(Action::SetupCompleted, $admin);
        $this->activity->log(Action::UserCreated, $admin, $admin);

        if ($this->settings->get(Setting::AdminNotificationEmails) === []) {
            $this->settings->set(Setting::AdminNotificationEmails, [$admin->email]);
        }

        // They will be shown around the first time they sign in — which is
        // the next thing that happens, since setup deliberately does not
        // log anybody in.
        $this->welcome->raise();

        // Deliberately no auto-login: the new administrator proves their
        // credentials at the login form, which also confirms they work.
        return redirect()->route('setup.success')->with('setup_completed', true);
    }

    public function success(Request $request): Response|RedirectResponse
    {
        if (! $this->setupIsComplete()) {
            return redirect()->route('setup');
        }

        if (! $request->session()->get('setup_completed')) {
            return redirect()->route('login');
        }

        return Inertia::render('setup-success');
    }

    private function setupIsComplete(): bool
    {
        return User::query()->where('type', UserType::Staff)->exists();
    }
}
