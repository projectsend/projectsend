<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Auth\TokenAbilities;
use App\Modules\Identity\Permissions\PermissionChecker;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * For the rest of an API request, the caller holds only what their token
 * was granted.
 *
 * `token-can:` on a route asks for one ability out of a pair -- edit_files
 * or edit_others_files -- because which one applies depends on the file,
 * and the route cannot know that. The policy then picks the one that does
 * apply, but asks the role, and the role may hold both. So a token minted
 * for "Edit own files" edited other people's files whenever its owner
 * could (GHSA-w79h-hrf3-846q). Capping the permission checker here makes
 * every policy, and every other permission check in the request, answer
 * for the token rather than for the person -- for the abilities a token
 * can hold. The finer keys no token can be given still follow the role;
 * see PermissionChecker::limitTo().
 *
 * The cap names the token's owner. Other people's permissions -- whom to
 * notify, say -- are still read from their roles.
 */
class LimitPermissionsToToken
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly TokenAbilities $abilities,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        /** @var object|null $token */
        $token = $user?->currentAccessToken();

        // A personal access token, not Sanctum's TransientToken: that one
        // stands for a first-party session and answers yes to everything.
        if ($user !== null && $token instanceof PersonalAccessToken) {
            $this->permissions->limitTo($user, $token, $this->abilities->inUse());
        }

        return $next($request);
    }
}
