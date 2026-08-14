<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\Social\SocialAccount;
use App\Modules\Identity\Social\SocialProvider;
use App\Modules\Identity\Social\SocialSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The providers a person has connected to their own account.
 *
 * Staff and clients alike — connecting a provider is a property of an
 * account, not of a role. Connecting from here is also the *safe* way to
 * use a provider that cannot verify an email address, because the
 * account is established by the session rather than by the address: the
 * person is already signed in, so nothing is being taken on trust.
 */
class ConnectedAccountsController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        $links = SocialAccount::query()
            ->where('user_id', $user?->getKey())
            ->get()
            ->keyBy(fn (SocialAccount $link): string => $link->provider->value);

        $providers = [];

        foreach (SocialSettings::allProviders() as $key => $settings) {
            if (! $settings->usable()) {
                continue;
            }

            $link = $links->get($key);

            $providers[] = [
                'provider' => $key,
                'label' => $settings->provider->label(),
                'connected' => $link !== null,
                'email' => $link?->email,
                'connected_at' => $link?->created_at?->toIso8601String(),
            ];
        }

        return Inertia::render('settings/connected-accounts', [
            'providers' => $providers,
            // Why a disconnect may be refused, said before it is tried
            // rather than after.
            'has_local_password' => $user?->auth_source === AuthSource::Local,
        ]);
    }

    public function destroy(Request $request, string $provider): RedirectResponse
    {
        $case = SocialProvider::tryFrom($provider) ?? throw new NotFoundHttpException;

        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $link = SocialAccount::query()
            ->where('user_id', $user->getKey())
            ->where('provider', $case->value)
            ->first();

        if ($link === null) {
            return back();
        }

        // The mirror image of AccountConversion::requiresNewPassword():
        // an account created by a provider holds a random password nobody
        // has ever seen, so the provider is the only way in. Removing the
        // last one locks the person out of their own files.
        $remaining = SocialAccount::query()
            ->where('user_id', $user->getKey())
            ->where('provider', '!=', $case->value)
            ->count();

        if ($remaining === 0 && $user->auth_source !== AuthSource::Local) {
            throw ValidationException::withMessages([
                'provider' => __('This is the only way you can sign in. Set a password first, then disconnect :provider.', [
                    'provider' => $case->label(),
                ]),
            ]);
        }

        $link->delete();

        $this->activity->log(Action::SocialAccountUnlinked, $user, $user, ['provider' => $case->label()]);

        return back()->with('success', __(':provider disconnected.', ['provider' => $case->label()]));
    }
}
