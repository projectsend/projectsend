<?php

declare(strict_types=1);

use App\Modules\Api\Events\ApiModule;
use App\Modules\Api\Events\RegisteringApiModules;
use App\Modules\Api\Http\Controllers\CurrentTokenController;
use App\Modules\Api\Http\Controllers\MeController;
use App\Modules\Audit\Http\Controllers\Api\ActivityController;
use App\Modules\Api\Http\Controllers\OpenApiController;
use App\Modules\Clients\Http\Controllers\Api\ClientsController;
use App\Modules\Comments\Http\Controllers\Api\CommentModerationController;
use App\Modules\Comments\Http\Controllers\Api\FileCommentsController;
use App\Modules\Files\Http\Controllers\Api\FileAssignmentsController;
use App\Modules\Files\Http\Controllers\Api\FilesController;
use App\Modules\Files\Http\Controllers\Api\FileVersionsController as ApiFileVersionsController;
use App\Modules\Files\Http\Controllers\ChunkedUploadsController;
use App\Modules\Files\Http\Controllers\FileDownloadController;
use App\Modules\Groups\Http\Controllers\Api\GroupMembersController;
use App\Modules\Groups\Http\Controllers\Api\GroupsController;
use App\Modules\Identity\Http\Controllers\Api\RolesController;
use App\Modules\Identity\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API v1
|--------------------------------------------------------------------------
|
| Mounted at /api/v1 (see bootstrap/app.php). Bearer tokens only — there is
| no session on these routes, by design; see config/sanctum.php.
|
| Every route below carries the same stack:
|
|   auth:sanctum   the token resolves to a user
|   api-active     that user has not since been deactivated
|   staff-token    that user is staff (the API is staff-only in v1)
|
| plus, per route, `token-can:<permission>` — which checks both that the
| token was granted the ability and that the user still holds it today.
|
| The permission on each route is the same one its routes/web.php twin
| carries. The staff/client boundary and the own/others split are mirrored
| from the web surface, never reinvented here.
|
*/

/*
 * The only unauthenticated route in the API, and deliberately so: a client
 * needs the specification before it can have a token, and the document
 * describes the shape of the API without exposing anything about this
 * installation. See OpenApiController.
 */
Route::get('openapi.json', OpenApiController::class)
    ->middleware('throttle:api')
    ->name('api.openapi');

Route::middleware(['auth:sanctum', 'api-active', 'staff-token'])->group(function () {
    Route::get('me', MeController::class)->name('api.me');

    // Revokes only the token that made the call. Revoking someone else's
    // token is a web-only action: doing it here would let a leaked token
    // lock the real owner out of their own integrations.
    Route::delete('tokens/current', CurrentTokenController::class)->name('api.tokens.current.destroy');

    /*
    |----------------------------------------------------------------------
    | Files (read)
    |----------------------------------------------------------------------
    |
    | The ability list mirrors FilePolicy::view()'s staff branch rather
    | than the web `files.index` route's `can:upload`. Those differ, and
    | the policy is the right one to follow here: every row returned is
    | individually view-authorized through ViewableFileScope, so requiring
    | `upload` — a *write* permission — to list files a user is already
    | allowed to read would be a stricter boundary than the app applies
    | anywhere else. (Zip downloads already list by view rules alone.)
    |
    */
    Route::middleware('token-can:upload,edit_files,edit_others_files')->group(function () {
        Route::get('files', [FilesController::class, 'index'])->name('api.files.index');
        Route::get('files/{file}', [FilesController::class, 'show'])->name('api.files.show');

        // The web controller, reused verbatim: it already authorizes with
        // FilePolicy::view, writes the FileDownloaded activity entry (which
        // honours Setting::DownloadIpLogging centrally), and answers with
        // X-Accel-Redirect locally or a presigned redirect on external
        // storage. Both work unchanged for an HTTP client, and duplicating
        // that logic for the API is how the two would drift.
        Route::get('files/{file}/download', FileDownloadController::class)->name('api.files.download');
    });

    /*
    |----------------------------------------------------------------------
    | Files (write)
    |----------------------------------------------------------------------
    |
    | Each route carries the permission its routes/web.php twin carries,
    | including the own/others split — which FilePolicy resolves per file,
    | so both keys appear here and the policy decides which one applies.
    |
    */
    Route::post('files', [FilesController::class, 'store'])
        ->middleware(['token-can:upload', 'throttle:api-uploads'])
        ->name('api.files.store');

    Route::patch('files/{file}', [FilesController::class, 'update'])
        ->middleware('token-can:edit_files,edit_others_files')
        ->name('api.files.update');

    Route::delete('files/{file}', [FilesController::class, 'destroy'])
        ->middleware('token-can:delete_files,delete_others_files')
        ->name('api.files.destroy');

    /*
    |----------------------------------------------------------------------
    | Chunked, resumable uploads
    |----------------------------------------------------------------------
    |
    | Token-authenticated twins of the browser's Uppy-contract routes,
    | reusing ChunkedUploadsController unchanged — it was already
    | JSON-in/JSON-out, and it is where the real upload rules live (size
    | setting, extension policy, storage quota re-checked against the
    | assembled bytes, mime re-detected from those bytes).
    |
    | The part PUT carries `signed` as well as the token: the signature is
    | the grant for that one part, exactly as on the web side. Unlike the
    | web route it needs no CSRF exemption, because the API group has no
    | CSRF to exempt it from.
    |
    */
    Route::middleware(['token-can:upload', 'throttle:api-uploads'])->group(function () {
        Route::post('uploads', [ChunkedUploadsController::class, 'store'])->name('api.uploads.store');
        Route::get('uploads/{session}/parts/{part}/sign', [ChunkedUploadsController::class, 'signPart'])
            ->name('api.uploads.parts.sign');
        Route::put('uploads/{session}/parts/{part}', [ChunkedUploadsController::class, 'putPart'])
            ->middleware('signed')->name('api.uploads.parts.put');
        Route::get('uploads/{session}/parts', [ChunkedUploadsController::class, 'listParts'])
            ->name('api.uploads.parts.index');
        Route::post('uploads/{session}/complete', [ChunkedUploadsController::class, 'complete'])
            ->name('api.uploads.complete');
        Route::delete('uploads/{session}', [ChunkedUploadsController::class, 'destroy'])
            ->name('api.uploads.destroy');
    });

    // Sharing is gated by "may edit this file" — see the controller.
    Route::middleware('token-can:edit_files,edit_others_files')->group(function () {
        Route::post('files/{file}/assignments', [FileAssignmentsController::class, 'store'])
            ->name('api.files.assignments.store');
        Route::delete('files/{file}/assignments', [FileAssignmentsController::class, 'destroy'])
            ->name('api.files.assignments.destroy');

        // Same gate as sharing, and for the same reason: linking moves this
        // file's recipients onto the original, so it changes who can see
        // that file too.
        Route::put('files/{file}/version', [ApiFileVersionsController::class, 'store'])
            ->name('api.files.version.store');
        Route::delete('files/{file}/version', [ApiFileVersionsController::class, 'destroy'])
            ->name('api.files.version.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Comments
    |----------------------------------------------------------------------
    |
    | Reading and writing a comment is gated by "may see this file", the
    | same three keys the file endpoints use — there is no comment
    | permission, because who may comment is an installation setting rather
    | than a per-role one (see App\Modules\Comments\CommentAuthors).
    | `moderate_comments` gates the queue and approving; deleting stays
    | under the file abilities because the policy also lets an author
    | delete their own within the window, and that is not moderation.
    |
    */
    Route::middleware('token-can:upload,edit_files,edit_others_files')->group(function () {
        Route::get('files/{file}/comments', [FileCommentsController::class, 'index'])->name('api.files.comments.index');
        Route::post('files/{file}/comments', [FileCommentsController::class, 'store'])->name('api.files.comments.store');
        Route::patch('comments/{comment}', [FileCommentsController::class, 'update'])->name('api.comments.update');
        Route::delete('comments/{comment}', [FileCommentsController::class, 'destroy'])->name('api.comments.destroy');
    });

    // Registered before comments/{comment} would swallow "pending" as an id.
    Route::middleware('token-can:moderate_comments')->group(function () {
        Route::get('comments/pending', [CommentModerationController::class, 'index'])->name('api.comments.pending');
        Route::post('comments/{comment}/approve', [CommentModerationController::class, 'approve'])->name('api.comments.approve');
    });

    /*
    |----------------------------------------------------------------------
    | Clients
    |----------------------------------------------------------------------
    |
    | Permissions mirror routes/web.php exactly: listing is `manage_clients`,
    | reading and editing one is `edit_clients`, and creating and deleting
    | have keys of their own.
    |
    */
    Route::get('clients', [ClientsController::class, 'index'])
        ->middleware('token-can:manage_clients')->name('api.clients.index');
    Route::get('clients/{client}', [ClientsController::class, 'show'])
        ->middleware('token-can:edit_clients')->name('api.clients.show');
    Route::post('clients', [ClientsController::class, 'store'])
        ->middleware('token-can:create_clients')->name('api.clients.store');
    Route::patch('clients/{client}', [ClientsController::class, 'update'])
        ->middleware('token-can:edit_clients')->name('api.clients.update');
    Route::delete('clients/{client}', [ClientsController::class, 'destroy'])
        ->middleware('token-can:delete_clients')->name('api.clients.destroy');
    // `edit_clients`, not `delete_clients`: this changes how an account
    // signs in, it does not remove one — the same key its web twin uses.
    Route::delete('clients/{client}/two-factor', [ClientsController::class, 'destroyTwoFactor'])
        ->middleware('token-can:edit_clients')->name('api.clients.two-factor.destroy');

    /*
    |----------------------------------------------------------------------
    | Staff accounts and the roles assigned to them
    |----------------------------------------------------------------------
    |
    | Both editions since 2.2.0. This was Community-only while a managed
    | installation's staff accounts were expected to arrive from outside;
    | a platform provisions the seat count and the tenant decides who
    | fills it, so the screen and the endpoints stay open and
    | PROJECTSEND_PLATFORM_MAX_STAFF_USERS is what a platform actually
    | owns. `capability:users.manage` is the same gate routes/web.php puts
    | on the /users screens, and both now pass in both editions.
    |
    | The middleware stays rather than being deleted: the capability is
    | the seam an edition difference has to travel through, and a
    | capability that is currently true everywhere is still where a future
    | edition would say otherwise. A caller in an edition without it gets
    | 403 `capability_unavailable`, which says what is wrong; an endpoint
    | that silently was not there would not. The OpenAPI document is
    | committed and served unauthenticated, so it must be identical on
    | every install either way (OpenApiContractTest compares it against
    | the route table).
    |
    | Permissions mirror routes/web.php exactly: `manage_users` to reach
    | the area at all, then a key per action on top of it. Two `token-can:`
    | middlewares stack as AND, which is how the web group + route pairing
    | reads there.
    |
    */
    Route::middleware(['capability:users.manage', 'token-can:manage_users'])->group(function () {
        Route::get('users', [UsersController::class, 'index'])->name('api.users.index');
        Route::get('roles', [RolesController::class, 'index'])->name('api.roles.index');

        Route::get('users/{user}', [UsersController::class, 'show'])
            ->middleware('token-can:edit_users')->name('api.users.show');
        Route::post('users', [UsersController::class, 'store'])
            ->middleware('token-can:create_users')->name('api.users.store');
        Route::patch('users/{user}', [UsersController::class, 'update'])
            ->middleware('token-can:edit_users')->name('api.users.update');
        Route::delete('users/{user}', [UsersController::class, 'destroy'])
            ->middleware('token-can:delete_users')->name('api.users.destroy');
        // See the note on api.clients.two-factor.destroy above.
        Route::delete('users/{user}/two-factor', [UsersController::class, 'destroyTwoFactor'])
            ->middleware('token-can:edit_users')->name('api.users.two-factor.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Groups
    |----------------------------------------------------------------------
    */
    Route::get('groups', [GroupsController::class, 'index'])
        ->middleware('token-can:manage_groups')->name('api.groups.index');
    Route::get('groups/{group}', [GroupsController::class, 'show'])
        ->middleware('token-can:edit_groups')->name('api.groups.show');
    Route::post('groups', [GroupsController::class, 'store'])
        ->middleware('token-can:create_groups')->name('api.groups.store');
    Route::patch('groups/{group}', [GroupsController::class, 'update'])
        ->middleware('token-can:edit_groups')->name('api.groups.update');
    Route::delete('groups/{group}', [GroupsController::class, 'destroy'])
        ->middleware('token-can:delete_groups')->name('api.groups.destroy');

    Route::middleware('token-can:edit_groups')->group(function () {
        Route::post('groups/{group}/members', [GroupMembersController::class, 'store'])
            ->name('api.groups.members.store');
        Route::delete('groups/{group}/members/{member}', [GroupMembersController::class, 'destroy'])
            ->name('api.groups.members.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Activity
    |----------------------------------------------------------------------
    |
    | The one endpoint that answers "what happened", rather than "what is
    | there now". An integration reacting to events has nothing else to
    | poll: sharing a file writes an assignment row and leaves the file
    | untouched, and a download is only ever recorded here, so neither is
    | visible from any other list.
    |
    | `view_actions_log` is the same permission the activity screen uses,
    | and ActivityLogScope narrows the rows the same way it does there —
    | a staff member limited to their assigned clients must not read the
    | whole installation's log through a token when the screen would not
    | show it to them.
    |
    */
    Route::get('activity', [ActivityController::class, 'index'])
        ->middleware('token-can:view_actions_log')->name('api.activity.index');

    /*
    |----------------------------------------------------------------------
    | Module endpoints
    |----------------------------------------------------------------------
    |
    | cloud-modules and community-modules add their own endpoints here.
    | Core owns the frame: the prefix, the route-name namespace, the auth
    | stack above, and the capability gate. A module supplies paths and
    | controllers and nothing else.
    |
    | Dispatched from the route file rather than from a service provider
    | because route files load inside RouteServiceProvider's booted()
    | callback — every package provider has already registered its listener
    | by then — and because registering here keeps module routes inside
    | `route:cache` instead of being bolted on after the cache is read.
    |
    */
    $modules = new RegisteringApiModules;
    Event::dispatch($modules);

    foreach ($modules->modules() as $module) {
        /** @var ApiModule $module */
        Route::prefix("modules/{$module->slug}")
            ->name("api.modules.{$module->slug}.")
            ->middleware($module->capability !== null ? ["capability:{$module->capability}"] : [])
            ->group($module->routes);
    }
});
