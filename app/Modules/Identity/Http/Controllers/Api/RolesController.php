<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\StaffAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The roles a caller may assign, so `POST`/`PATCH /users` has something to
 * name.
 *
 * Read-only, and deliberately so: this lists what exists, it does not
 * define it. Creating a role means choosing a set of permissions, which
 * is the single most consequential form in the application and the one
 * place a mistake grants authority nobody meant to grant. That belongs
 * behind the roles screen, with its permission matrix and its password
 * confirmation, until somebody has an actual integration that needs to
 * automate it. Recorded as a decision rather than left as a gap
 * someone has to rediscover.
 *
 * Narrowed to what *this* caller may grant, not every role in the table —
 * the same list the create form offers them, from the same
 * StaffAccounts::assignableRoles(). Showing a role you cannot use would
 * only produce a 422 later.
 *
 * Community only, alongside the user endpoints it serves.
 */
class RolesController extends Controller
{
    public function __construct(
        private readonly StaffAccounts $accounts,
    ) {}

    /**
     * List the roles you may assign to a staff account.
     *
     * `client_scoped` roles are the ones for which `assigned_clients`
     * means anything on a user; for any other role the assignment is
     * cleared. `is_administrator` marks the roles that hold every
     * permission, which only an administrator may hand out.
     *
     * The Client system role is never listed: clients are not staff and
     * are managed through `/clients`.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);

        return response()->json([
            'data' => $this->accounts->assignableRoles($actor)
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'is_system' => $role->is_system,
                    'is_administrator' => $role->is_administrator,
                    'client_scoped' => $role->client_scoped,
                ])
                ->all(),
        ]);
    }
}
