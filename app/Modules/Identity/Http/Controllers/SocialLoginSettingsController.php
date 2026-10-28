<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\Reauthentication;
use App\Modules\Identity\Social\OidcDiscovery;
use App\Modules\Identity\Social\SocialProvider;
use App\Modules\Identity\Social\SocialSettings;
use App\Modules\Identity\StaffSignInPolicy;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuring identity providers.
 *
 * Available in **both** editions and behind no capability, for the reason
 * LDAP settled: this is an administrator's setting, not an edition
 * difference.
 *
 * The client secret follows the pattern MailProviderSettings established
 * and LdapSettings repeated: it is never sent to the browser (only
 * `has_client_secret`), and a blank submission means "keep the stored
 * one". v1 rendered all eight of these into the form's HTML `value=`.
 */
class SocialLoginSettingsController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly OidcDiscovery $discovery,
        private readonly StaffSignInPolicy $staffSignIn,
        private readonly Reauthentication $reauthentication,
        private readonly Settings $settings,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        $providers = [];

        foreach (SocialSettings::allProviders() as $key => $settings) {
            $provider = $settings->provider;

            $providers[] = [
                'provider' => $key,
                'label' => $provider->label(),
                'enabled' => $settings->enabled,
                'client_id' => $settings->client_id,
                // The secret itself never leaves the server.
                'has_client_secret' => is_string($settings->client_secret) && $settings->client_secret !== '',
                'issuer_url' => $settings->issuer_url,
                'tenant_id' => $settings->tenant_id,
                'require_verified_email' => $settings->require_verified_email,
                'allowed_domains' => $settings->allowed_domains,
                'auto_provision' => $settings->auto_provision,
                'auto_approve' => $settings->auto_approve,
                'skip_two_factor' => $settings->skip_two_factor,
                'needs_issuer_url' => $provider->needsIssuerUrl(),
                'needs_tenant_id' => $provider->needsTenantId(),
                // Without this an administrator reads the verified-email
                // checkbox as doing something it cannot do here.
                'can_report_verified_email' => $provider->canReportVerifiedEmail(),
                // The single most common configuration failure is pasting
                // the wrong one of these into the provider's console.
                'redirect_uri' => $provider->redirectUri(),
            ];
        }

        $configured = $this->staffSignIn->configuredProvider();

        return Inertia::render('system/settings/social-login', [
            'providers' => $providers,
            'staff_sign_in' => [
                'provider' => $configured->value ?? '',
                // Chosen but not in force, because the provider is off or
                // half-configured — passwords work meanwhile, and the
                // screen says so rather than letting it look enforced.
                'enforced' => $this->staffSignIn->provider() !== null,
                'options' => collect(SocialProvider::cases())
                    ->filter(fn (SocialProvider $provider): bool => SocialSettings::for($provider)->usable())
                    ->map(fn (SocialProvider $provider): array => [
                        'provider' => $provider->value,
                        'label' => $provider->label(),
                        // The administrator choosing this has to be able to
                        // sign in with it, or the next sign-in is their last.
                        'connected' => $this->reauthentication->isConnected($user, $provider),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Which provider staff must sign in with, or none (#1828).
     *
     * Only a provider that is switched on and configured, and only one the
     * administrator making the change is connected to themselves: choosing
     * a provider you cannot sign in with locks you out at your next
     * sign-in, and every other staff member with you if nobody is.
     */
    public function updateStaffSignIn(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $validated = $request->validate([
            'provider' => ['present', 'nullable', 'string', Rule::in(['', ...array_map(fn (SocialProvider $p): string => $p->value, SocialProvider::cases())])],
        ]);

        $provider = SocialProvider::tryFrom((string) ($validated['provider'] ?? ''));

        if ($provider !== null && ! SocialSettings::for($provider)->usable()) {
            throw ValidationException::withMessages([
                'provider' => __('Switch :provider on and finish setting it up first.', ['provider' => $provider->label()]),
            ]);
        }

        if ($provider !== null && ! $this->reauthentication->isConnected($user, $provider)) {
            throw ValidationException::withMessages([
                'provider' => __('Connect :provider to your own account first, under Settings → Connected accounts, so that you can still sign in.', ['provider' => $provider->label()]),
            ]);
        }

        $this->settings->set(Setting::StaffSignInProvider, $provider->value ?? '');

        // A "remember me" cookie from a password sign-in would otherwise
        // keep signing its holder back in for as long as it lives, without
        // the provider ever being asked — so switching somebody off there
        // would not keep them out here. None can be made from now on:
        // passwords are refused and provider sign-ins never remember.
        if ($provider !== null) {
            User::query()->where('type', UserType::Staff)->update(['remember_token' => null]);
        }

        $this->activity->log(Action::SettingsUpdated, context: [
            'section' => 'staff-sign-in',
            'provider' => $provider?->label(),
        ]);

        return back()->with('success', $provider === null
            ? __('Staff can sign in with a password again.')
            : __('Staff now sign in with :provider only.', ['provider' => $provider->label()]));
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $case = SocialProvider::tryFrom($provider) ?? abort(404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:512'],
            'issuer_url' => [
                $case->needsIssuerUrl() && $request->boolean('enabled') ? 'required' : 'nullable',
                'string',
                'max:255',
                // Plaintext here would let anyone on the path choose both
                // the page we send people to and the endpoint we post a
                // client secret to.
                'starts_with:https://',
            ],
            'tenant_id' => [
                $case->needsTenantId() && $request->boolean('enabled') ? 'required' : 'nullable',
                'string',
                'max:255',
                // "common" and "organizations" are multi-tenant, and Entra's
                // email claim is user-mutable — with either of those a
                // stranger's tenant can assert your address. This is the
                // nOAuth class of bug, and refusing the value is the fix.
                'not_in:common,organizations,consumers',
            ],
            'require_verified_email' => ['required', 'boolean'],
            'allowed_domains' => ['nullable', 'string', 'max:512'],
            'auto_provision' => ['required', 'boolean'],
            'auto_approve' => ['required', 'boolean'],
            'skip_two_factor' => ['required', 'boolean'],
        ], [
            'tenant_id.not_in' => __('Enter a specific tenant ID. The shared "common", "organizations" and "consumers" endpoints let any Microsoft tenant assert any email address.'),
            'issuer_url.starts_with' => __('The issuer URL must start with https://.'),
        ]);

        $settings = SocialSettings::for($case);

        $previousIssuer = $settings->issuer();

        $settings->fill([
            'provider' => $case->value,
            'enabled' => (bool) $validated['enabled'],
            'client_id' => $validated['client_id'] ?? null,
            'issuer_url' => $validated['issuer_url'] ?? null,
            'tenant_id' => $validated['tenant_id'] ?? null,
            'require_verified_email' => (bool) $validated['require_verified_email'],
            'allowed_domains' => $validated['allowed_domains'] ?? null,
            'auto_provision' => (bool) $validated['auto_provision'],
            'auto_approve' => (bool) $validated['auto_approve'],
            'skip_two_factor' => (bool) $validated['skip_two_factor'],
        ]);

        // Blank means "leave it alone", so editing the allow-list does not
        // wipe the credential.
        if (is_string($validated['client_secret'] ?? null) && $validated['client_secret'] !== '') {
            $settings->client_secret = $validated['client_secret'];
        }

        $settings->save();

        // An administrator who has just corrected an issuer should not wait
        // an hour to find out whether it worked.
        foreach (array_filter([$previousIssuer, $settings->issuer()]) as $issuer) {
            $this->discovery->forget($issuer);
        }

        // Whether the provider may stand in for two-factor is recorded
        // alongside the section, as the Security settings record theirs: it
        // is the one setting here that weakens a sign-in.
        $this->activity->log(Action::SettingsUpdated, context: [
            'section' => 'social-login.'.$case->value,
            'skip_two_factor' => $settings->skip_two_factor,
        ]);

        return back()->with('success', __(':provider settings saved.', ['provider' => $case->label()]));
    }
}
