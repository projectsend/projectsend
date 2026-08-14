<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaProvider;
use App\Modules\Platform\Captcha\CaptchaSettings;
use App\Modules\Platform\Captcha\CaptchaVerifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuring the CAPTCHA on public forms.
 *
 * Available in **both** editions and behind no capability, for the reason
 * LDAP settled and social login repeated: this is an administrator's
 * setting, not an edition difference. What *is* an edition difference is
 * the option of using the platform's own keys, and that is enforced per
 * field rather than on the route — the shape EmailSettingsController uses
 * for SMTP, so a hand-crafted PATCH cannot select a key source this
 * installation has no keys for.
 *
 * The secret key follows the pattern MailProviderSettings established and
 * LdapSettings and SocialSettings repeated: it is never sent to the
 * browser (only `has_secret_key`), and a blank submission means "keep the
 * stored one". v1 rendered all six of these into the form's HTML `value=`.
 */
class CaptchaSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
        private readonly CapabilityRegistry $capabilities,
        private readonly Captcha $captcha,
    ) {}

    public function edit(Request $request): Response
    {
        $providers = [];

        foreach (CaptchaSettings::allProviders() as $key => $stored) {
            $providers[] = [
                'provider' => $key,
                'label' => $stored->provider->label(),
                'site_key' => $stored->site_key,
                // The secret itself never leaves the server.
                'has_secret_key' => is_string($stored->secret_key) && $stored->secret_key !== '',
                'score_threshold' => $stored->threshold(),
                'uses_score' => $stored->provider->usesScore(),
            ];
        }

        $active = $this->captcha->active();

        return Inertia::render('system/settings/captcha', [
            'provider' => $this->settings->get(Setting::CaptchaProvider),
            'key_source' => $this->captcha->managedKeysSelected() ? 'managed' : 'own',
            // Whether this installation may use the platform's keys at all.
            // False in community, and false in cloud if we have not
            // configured any — in which case the choice is not offered
            // rather than offered and broken.
            'managed_keys_available' => $this->captcha->managedKeysAvailable(),
            'providers' => $providers,
            'forms' => [
                'login' => $this->settings->get(Setting::CaptchaOnLogin),
                'registration' => $this->settings->get(Setting::CaptchaOnRegistration),
                'password_reset' => $this->settings->get(Setting::CaptchaOnPasswordReset),
                'public_comments' => $this->settings->get(Setting::CaptchaOnPublicComments),
            ],
            // So the page can say plainly whether anything is actually
            // protecting the forms — a half-filled configuration is
            // switched off, and silence about that is how an operator
            // believes they are covered when they are not.
            'active' => $active !== null,
            'using_managed_keys' => $active !== null && $active->managed,
            'last_error' => CaptchaVerifier::lastError(),
            'test_result' => $request->session()->get('captcha_test_result'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $canUseManagedKeys = $this->capabilities->has(Capability::CaptchaManagedKeys);

        $rules = [
            'on_login' => ['required', 'boolean'],
            'on_registration' => ['required', 'boolean'],
            'on_password_reset' => ['required', 'boolean'],
            'on_public_comments' => ['required', 'boolean'],
        ];

        // Read only where it means something. On community the field never
        // enters the rules and is never written, so a hand-crafted PATCH
        // cannot point a self-hosted install at credentials it has not got.
        if ($canUseManagedKeys) {
            $rules['key_source'] = ['required', Rule::in(['managed', 'own'])];
        }

        $usingManagedKeys = $canUseManagedKeys && $request->input('key_source') === 'managed';

        if (! $usingManagedKeys) {
            $selected = CaptchaProvider::tryFrom((string) $request->input('provider'));

            $rules['provider'] = ['required', Rule::in([
                'none',
                ...array_map(fn (CaptchaProvider $case): string => $case->value, CaptchaProvider::cases()),
            ])];

            $rules['site_key'] = [$selected !== null ? 'required' : 'nullable', 'string', 'max:255'];

            // Required on a first save, optional afterwards — so editing
            // the threshold or a form switch never wipes the credential,
            // while a provider cannot be switched on half-configured.
            $hasStoredSecret = $selected !== null && CaptchaSettings::for($selected)->usable();

            $rules['secret_key'] = [
                $selected !== null && ! $hasStoredSecret ? 'required' : 'nullable',
                'string',
                'max:512',
            ];

            $rules['score_threshold'] = [
                $selected?->usesScore() === true ? 'required' : 'nullable',
                'numeric',
                'between:0,1',
            ];
        }

        $validated = $request->validate($rules);

        if ($canUseManagedKeys) {
            $this->settings->set(Setting::CaptchaKeySource, $validated['key_source']);
        }

        if (! $usingManagedKeys) {
            $this->settings->set(Setting::CaptchaProvider, $validated['provider']);

            $selected = CaptchaProvider::tryFrom($validated['provider']);

            if ($selected !== null) {
                $stored = CaptchaSettings::for($selected);
                $stored->provider = $selected;
                $stored->site_key = $validated['site_key'] ?? null;

                if ($selected->usesScore()) {
                    $stored->score_threshold = (float) $validated['score_threshold'];
                }

                // Blank means "leave it alone".
                if (is_string($validated['secret_key'] ?? null) && $validated['secret_key'] !== '') {
                    $stored->secret_key = $validated['secret_key'];
                }

                $stored->save();
            }
        }

        $this->settings->set(Setting::CaptchaOnLogin, (bool) $validated['on_login']);
        $this->settings->set(Setting::CaptchaOnRegistration, (bool) $validated['on_registration']);
        $this->settings->set(Setting::CaptchaOnPasswordReset, (bool) $validated['on_password_reset']);
        $this->settings->set(Setting::CaptchaOnPublicComments, (bool) $validated['on_public_comments']);

        // This controller is the only writer of the settings half of the
        // display payload, so it is the only thing that has to remember
        // this. The credentials half flushes itself from the model.
        Captcha::forgetDisplayCache();

        // A configuration change deserves a fresh verdict rather than a
        // week-old complaint about the key that was just replaced.
        CaptchaVerifier::forgetOutage();

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'captcha']);

        return back()->with('success', __('CAPTCHA settings saved.'));
    }

    /**
     * Prove a secret key without anybody having to solve a challenge.
     *
     * The most common configuration failure is a key pasted from the wrong
     * field or with a trailing space, and because a bad secret deliberately
     * fails *open*, nothing on the public forms would reveal it. This does.
     */
    public function test(Request $request, CaptchaVerifier $verifier): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::enum(CaptchaProvider::class)],
            'secret_key' => ['nullable', 'string', 'max:512'],
        ]);

        $provider = CaptchaProvider::from($validated['provider']);

        // The one being typed if there is one, otherwise the one on file —
        // so the button tests what the administrator is looking at.
        $secret = $validated['secret_key'] ?? null;

        if (! is_string($secret) || $secret === '') {
            $secret = CaptchaSettings::for($provider)->secret_key;
        }

        if (! is_string($secret) || $secret === '') {
            return back()->with('captcha_test_result', [
                'ok' => false,
                'message' => __('Enter a secret key first.'),
            ]);
        }

        return back()->with('captcha_test_result', $verifier->test($provider, $secret));
    }
}
