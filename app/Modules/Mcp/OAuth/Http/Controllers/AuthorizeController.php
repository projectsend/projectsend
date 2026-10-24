<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Http\Controllers;

use App\Models\User;
use App\Modules\Identity\Http\ConfirmationRequired;
use App\Modules\Identity\PasswordVerification;
use App\Modules\Identity\Reauthentication;
use App\Modules\Mcp\Http\Middleware\EnsureConnectorCapability;
use App\Modules\Mcp\OAuth\AuthorizationRequest;
use App\Modules\Mcp\OAuth\AuthorizeRefused;
use App\Modules\Mcp\OAuth\Clients\Clients;
use App\Modules\Mcp\OAuth\Clients\RedirectUris;
use App\Modules\Mcp\OAuth\Grants;
use App\Modules\Mcp\OAuth\Scopes;
use App\Modules\Mcp\Support\ConnectorPlan;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The consent (design §4): who asks, as whom, for what, and the password
 * once more before anything is issued. Everything that can fail before
 * the client and its redirect are known fails on this screen; after
 * that, at the client's own address.
 */
final class AuthorizeController
{
    public function show(Request $request, Clients $clients): Response
    {
        if (! EnsureConnectorCapability::granted()) {
            return $this->screen($request, 'This site does not offer an AI assistant connector.', 404);
        }

        try {
            $authorization = AuthorizationRequest::fromQuery($request, $clients);
        } catch (AuthorizeRefused $e) {
            return $e->redirectTo !== null
                ? redirect()->away($e->redirectTo)
                : $this->screen($request, $e->description, 400);
        }

        $user = $request->user();
        $kind = $this->kindOf($user);

        if ($user === null || $kind === null) {
            return $this->screen($request, 'Your account cannot connect an assistant on this site. Ask the site\'s staff to connect it, or connect from your own ProjectSend account.', 403);
        }

        $client = $authorization->client;
        $site = app(Settings::class)->get(Setting::SiteName);

        return Inertia::render('mcp/consent', [
            'request_id' => $authorization->stash($request),
            'client' => [
                'name' => $client->name,
                'identity' => $client->identity(),
                'verified' => $client->isVerified(),
                'logo_uri' => $client->logo_uri,
                'client_uri' => $client->client_uri,
                'redirect_host' => RedirectUris::host($authorization->redirectUri) ?? $authorization->redirectUri,
                'loopback' => RedirectUris::isLoopback($authorization->redirectUri),
            ],
            'actor' => [
                'name' => (string) $user->getAttribute('name'),
                'email' => (string) $user->getAttribute('email'),
                'site' => is_string($site) && $site !== '' ? $site : (string) config('app.name', 'ProjectSend'),
            ],
            'grant' => [
                'kind' => $kind,
                'default_access' => Scopes::defaultAccess($authorization->requestedScopes),
            ],
        ])->toResponse($request);
    }

    public function store(Request $request, Grants $grants): Response
    {
        $validated = $request->validate([
            'request_id' => ['required', 'string', 'size:32'],
            'decision' => ['required', Rule::in(['allow', 'deny'])],
            'access' => ['nullable', Rule::in([Scopes::ACCESS_READ, Scopes::ACCESS_OPERATE])],
            'password' => ['nullable', 'string'],
        ]);

        $authorization = AuthorizationRequest::recall($request, $validated['request_id']);

        if ($authorization === null) {
            return $this->screen($request, 'This request has expired. Start again from your assistant.', 410);
        }

        if ($validated['decision'] === 'deny') {
            AuthorizationRequest::forget($request, $validated['request_id']);

            return Inertia::location($authorization->redirectWithError('access_denied', 'The person did not allow it.'));
        }

        $user = $request->user();
        $kind = $this->kindOf($user);

        if ($user === null || $kind === null || ! EnsureConnectorCapability::granted()) {
            return $this->screen($request, 'Your account cannot connect an assistant on this site.', 403);
        }

        // The password once more, or for an account with no password here a
        // fresh sign-in at its provider, through the dialog password.confirm
        // opens (Reauthentication).
        $reauthentication = app(Reauthentication::class);

        if (! $reauthentication->hasPassword($user)) {
            if (! $reauthentication->recentlyConfirmed($request)) {
                ConfirmationRequired::throw($request);
            }
        } elseif (! $this->passwordVerifies($user, (string) ($validated['password'] ?? ''))) {
            throw ValidationException::withMessages(['password' => __('The password is incorrect.')]);
        }

        $scopes = $kind === 'client'
            ? Scopes::forClient()
            : Scopes::forStaff($validated['access'] ?? Scopes::defaultAccess($authorization->requestedScopes));

        $code = $grants->issueCode($authorization, $user, $scopes);
        AuthorizationRequest::forget($request, $validated['request_id']);

        return Inertia::location($authorization->redirectWithCode($code));
    }

    /** Staff anywhere; a client only where client accounts may connect (ConnectorPlan). */
    private function kindOf(mixed $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($user->isStaff()) {
            return 'staff';
        }

        return $user->isClient() && ConnectorPlan::current()->clientsMayConnect() ? 'client' : null;
    }

    /**
     * The app's own check, which knows about LDAP and rehashing.
     */
    private function passwordVerifies(User $user, string $password): bool
    {
        return $password !== '' && app(PasswordVerification::class)->verify($user, $password);
    }

    private function screen(Request $request, string $message, int $status): Response
    {
        return Inertia::render('mcp/authorize-error', ['message' => $message])->toResponse($request)->setStatusCode($status);
    }
}
