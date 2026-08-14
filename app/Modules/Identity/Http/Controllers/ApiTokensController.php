<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Api\Auth\ApiTokens;
use App\Modules\Api\Auth\TokenAbilities;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Self-service API tokens — the only way to obtain a credential for
 * /api/v1 in v1. There is deliberately no password-login API endpoint:
 * minting happens here, behind a session that has already passed login,
 * two-factor enforcement and (for the mutating routes) a fresh password
 * confirmation.
 *
 * Staff-only, because the API itself is staff-only.
 */
class ApiTokensController extends Controller
{
    public function __construct(
        private readonly TokenAbilities $abilities,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        return Inertia::render('settings/api-tokens/index', [
            'tokens' => $this->tokensFor($user),
            // Flashed by store() and never persisted anywhere: this is the
            // one and only time the plaintext exists outside the caller's
            // clipboard. The database holds a SHA-256 hash.
            'created_token' => $request->session()->get('created_api_token'),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        return Inertia::render('settings/api-tokens/create', [
            'available_abilities' => $this->availableAbilities($user),
            'defaults' => [
                'expires_in_days' => (int) config('api.tokens.default_days'),
                'max_days' => (int) config('api.tokens.max_days'),
            ],
        ]);
    }

    /**
     * Editing covers the name, the abilities and the expiry — never the
     * secret, which exists only as a hash and cannot be shown or changed.
     *
     * Widening an existing token's abilities is allowed, and is worth being
     * clear about: it changes what an already-issued secret can do without
     * that secret changing. It can never exceed the owner's own permissions
     * (EnsureTokenCan re-checks them live on every request), it is behind
     * the same password confirmation as minting, and the before/after lands
     * in the activity log. The alternative — forcing revoke-and-recreate —
     * would mean re-pasting a new secret into every integration for a
     * rename, which pushes people toward long-lived over-scoped tokens.
     */
    public function edit(Request $request, string $token): Response
    {
        $user = $request->user();
        assert($user !== null);

        $accessToken = $this->findOwnToken($user, $token);

        $available = $this->abilities->availableFor($user);
        $current = $accessToken->abilities ?? [];

        return Inertia::render('settings/api-tokens/edit', [
            'token' => [
                'id' => (string) $accessToken->getKey(),
                'name' => $accessToken->name,
                'abilities' => array_values(array_intersect($current, $available)),
                // The full instant, matching every other expires_at this
                // controller emits. The page turns it into a "days
                // remaining" default (edit.tsx), and a date truncated to
                // midnight makes that arithmetic up to a day out; nothing
                // here feeds a date picker, so there is no reason to
                // narrow it.
                'expires_at' => $accessToken->expires_at?->toIso8601String(),
                'created_at' => $accessToken->created_at?->toIso8601String(),
                'last_used_at' => $accessToken->last_used_at?->toIso8601String(),
                // Abilities the token still carries but that no longer
                // apply — the owner lost the permission, the edition
                // changed, or the endpoint was retired. They do nothing
                // today and saving drops them, which should not be a
                // surprise.
                'retired_abilities' => array_values(array_diff($current, $available)),
            ],
            'available_abilities' => $this->availableAbilities($user),
            'defaults' => [
                'expires_in_days' => (int) config('api.tokens.default_days'),
                'max_days' => (int) config('api.tokens.max_days'),
            ],
        ]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $accessToken = $this->findOwnToken($user, $token);

        $grantedKeys = $this->abilities->availableFor($user);
        $maxDays = (int) config('api.tokens.max_days');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in($grantedKeys)],
            'never_expires' => ['boolean'],
            'expires_in_days' => [
                Rule::requiredIf(fn (): bool => ! $request->boolean('never_expires')),
                'nullable', 'integer', 'min:1', 'max:'.$maxDays,
            ],
        ]);

        $before = $accessToken->abilities ?? [];
        $newAbilities = array_values(array_unique($validated['abilities']));

        $accessToken->forceFill([
            'name' => $validated['name'],
            'abilities' => $newAbilities,
            // Counted from now, not from the original issue date: the field
            // asks "how much longer", which is the question someone editing
            // a token is actually answering.
            'expires_at' => $request->boolean('never_expires')
                ? null
                : now()->addDays((int) $validated['expires_in_days']),
        ])->save();

        $this->activity->log(Action::ApiTokenUpdated, $user, context: [
            'token_name' => $validated['name'],
            'abilities_added' => array_values(array_diff($newAbilities, $before)),
            'abilities_removed' => array_values(array_diff($before, $newAbilities)),
        ]);

        return redirect()->route('api-tokens.index')->with('success', __('Token updated.'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        // Both gates, not just the role's permissions — see TokenAbilities.
        $grantedKeys = $this->abilities->availableFor($user);
        $maxDays = (int) config('api.tokens.max_days');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            // The ceiling on what a token may do is the issuer's own
            // permission set at this moment — not the full Permission enum.
            // A token must never be a way to acquire an ability its owner
            // does not have, and EnsureTokenCan re-checks the same
            // intersection on every request in case the role changes later.
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in($grantedKeys)],

            'never_expires' => ['boolean'],
            'expires_in_days' => [
                Rule::requiredIf(fn (): bool => ! $request->boolean('never_expires')),
                'nullable',
                'integer',
                'min:1',
                'max:'.$maxDays,
            ],
        ]);

        $expiresAt = $request->boolean('never_expires')
            ? null
            : now()->addDays((int) $validated['expires_in_days']);

        $token = $user->createToken(
            $validated['name'],
            array_values(array_unique($validated['abilities'])),
            $expiresAt,
        );

        $this->activity->log(Action::ApiTokenCreated, $user, context: [
            'token_name' => $validated['name'],
            'abilities' => $validated['abilities'],
            'expires_at' => $expiresAt?->toIso8601String(),
        ]);

        return redirect()->route('api-tokens.index')->with('created_api_token', [
            'name' => $validated['name'],
            'plain_text' => $token->plainTextToken,
        ]);
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $accessToken = $user->tokens()->whereKey($token)->first();

        if ($accessToken instanceof PersonalAccessToken) {
            $this->activity->log(Action::ApiTokenRevoked, $user, context: [
                'token_name' => $accessToken->name,
            ]);

            $accessToken->delete();
        }

        return back();
    }

    /**
     * Scoped to the caller's own tokens: managing them is not an
     * administrative power here, and the relation is what enforces it — a
     * bare PersonalAccessToken::find() would let any staff member rename,
     * re-scope or revoke anyone's integration by guessing an id. A miss is
     * a 404 rather than a 403, so ids cannot be probed for existence.
     */
    private function findOwnToken(User $user, string $token): PersonalAccessToken
    {
        $accessToken = $user->tokens()->whereKey($token)->first();

        abort_unless($accessToken instanceof PersonalAccessToken, 404);

        return $accessToken;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tokensFor(User $user): array
    {
        return array_values($user->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => (string) $token->getKey(),
                'name' => $token->name,
                'abilities' => $token->abilities ?? [],
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'expired' => ! ApiTokens::isActive($token),
                'created_at' => $token->created_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * Grouped by category so the form reads like the roles screen rather
     * than a flat wall of forty checkboxes.
     *
     * @return list<array{category: string, label: string, abilities: list<array{key: string, label: string}>}>
     */
    private function availableAbilities(User $user): array
    {
        $groups = [];

        foreach ($this->abilities->casesFor($user) as $permission) {
            $groups[$permission->category()->value]['label'] = $permission->category()->label();
            $groups[$permission->category()->value]['abilities'][] = [
                'key' => $permission->value,
                'label' => $permission->label(),
            ];
        }

        return array_map(
            static fn (string $category, array $group): array => [
                'category' => $category,
                'label' => $group['label'],
                'abilities' => $group['abilities'],
            ],
            array_keys($groups),
            $groups,
        );
    }
}
