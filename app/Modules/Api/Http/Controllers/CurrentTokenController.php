<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Self-revocation: an integration that is being decommissioned, or one that
 * suspects it has leaked its own credential, can retire it without anyone
 * logging into the web UI.
 *
 * Scoped to the calling token on purpose — see routes/api.php.
 */
class CurrentTokenController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        // Sanctum's own docblock types this as non-nullable, but the
        // underlying property is simply unset when the request was not
        // token-authenticated. Narrowing it here keeps the null branch
        // honest instead of letting the analyser delete it.
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        if ($token !== null) {
            // `via` and `api_token` are stamped by ActivityLogger itself.
            $this->activity->log(Action::ApiTokenRevoked, $user, context: ['token_name' => $token->name]);

            $token->delete();
        }

        return response()->json(status: 204);
    }
}
