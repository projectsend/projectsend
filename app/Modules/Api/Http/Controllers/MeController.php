<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Api\ApiModuleRegistry;
use App\Modules\Api\Auth\TokenAbilities;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Who am I, what may I do, and what does this install offer.
 *
 * The first call any integration makes: it lets a client discover the
 * effective permission set and the available modules instead of probing
 * endpoints and reading 403s.
 */
class MeController extends Controller
{
    public function __construct(
        private readonly TokenAbilities $abilities,
        private readonly CapabilityRegistry $capabilities,
        private readonly ApiModuleRegistry $modules,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        // See CurrentTokenController: Sanctum types this as non-nullable
        // although the property is unset without token authentication.
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type->value,
                'role' => $user->role?->name,
                'locale' => $user->locale,

                // The *effective* set, not the token's stored list: a token
                // minted before a demotion still carries abilities its owner
                // has since lost, and reporting those would tell an
                // integration it can do things every request will refuse.
                // EnsureTokenCan enforces exactly this intersection.
                'abilities' => $this->effectiveAbilities($user, $token),

                'token' => [
                    'name' => $token?->name,
                    'expires_at' => $token?->expires_at?->toIso8601String(),
                ],
            ],
            'meta' => [
                'edition' => $this->capabilities->edition()->value,
                'capabilities' => $this->capabilities->enabledKeys(),
                'modules' => $this->modules->slugs(),
                'version' => config('projectsend.version'),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function effectiveAbilities(User $user, ?PersonalAccessToken $token): array
    {
        // Permission ∩ capability, so this never advertises an ability that
        // the edition has no feature behind — see TokenAbilities.
        $granted = $this->abilities->availableFor($user);

        if ($token === null) {
            return [];
        }

        // Sanctum's wildcard: a token created without an explicit list may
        // do anything its owner may do. Tokens minted through the settings
        // page always carry an explicit list, so this is only reachable for
        // tokens created in code — tests, tinker, a future first-party flow.
        if (in_array('*', $token->abilities ?? [], true)) {
            return $granted;
        }

        return array_values(array_intersect($granted, $token->abilities ?? []));
    }
}
