<?php

use App\Http\Middleware\RedirectToWhatsNew;
use App\Modules\Api\Http\Controllers\ApiDashboardController;
use App\Modules\Api\Http\Controllers\ApiDocsController;
use App\Modules\Audit\Http\Controllers\ActivityLogController;
use App\Modules\Audit\Http\Controllers\DashboardController;
use App\Modules\Audit\Http\Controllers\DashboardWidgetPreferencesController;
use App\Modules\Audit\Http\Controllers\DownloadsController;
use App\Modules\Clients\Http\Controllers\AccountRequestsController;
use App\Modules\Clients\Http\Controllers\ClientCustomFieldsController;
use App\Modules\Clients\Http\Controllers\ClientsController;
use App\Modules\Comments\Http\Controllers\CommentDeepLinkController;
use App\Modules\Comments\Http\Controllers\CommentsController;
use App\Modules\Comments\Http\Controllers\FileCommentsController;
use App\Modules\Comments\Http\Controllers\PublicFileCommentsController;
use App\Modules\Files\Http\Controllers\CategoriesController;
use App\Modules\Files\Http\Controllers\ChunkedUploadsController;
use App\Modules\Files\Http\Controllers\ClientFilesController;
use App\Modules\Files\Http\Controllers\FileAssignmentsController;
use App\Modules\Files\Http\Controllers\FileDetailsController;
use App\Modules\Files\Http\Controllers\FileDownloadController;
use App\Modules\Files\Http\Controllers\FilesController;
use App\Modules\Files\Http\Controllers\FileThumbnailController;
use App\Modules\Files\Http\Controllers\FileVersionsController;
use App\Modules\Files\Http\Controllers\FolderAssignmentsController;
use App\Modules\Files\Http\Controllers\FoldersController;
use App\Modules\Files\Http\Controllers\MyFilesController;
use App\Modules\Files\Http\Controllers\MyFoldersController;
use App\Modules\Files\Http\Controllers\OrphanFilesController;
use App\Modules\Files\Http\Controllers\PublicShareController;
use App\Modules\Files\Http\Controllers\ShareLinksController;
use App\Modules\Files\Http\Controllers\ZipDownloadsController;
use App\Modules\Groups\Http\Controllers\GroupMembersController;
use App\Modules\Groups\Http\Controllers\GroupsController;
use App\Modules\Groups\Http\Controllers\MembershipRequestsController;
use App\Modules\Groups\Http\Controllers\MyGroupsController;
use App\Modules\Groups\Http\Controllers\PublicFoldersController;
use App\Modules\Groups\Http\Controllers\PublicGroupsController;
use App\Modules\Identity\Http\Controllers\AccountConversionController;
use App\Modules\Identity\Http\Controllers\RolesController;
use App\Modules\Identity\Http\Controllers\SetupController;
use App\Modules\Identity\Http\Controllers\UsersController;
use App\Modules\Notifications\Http\Controllers\NotificationsController;
use App\Modules\Platform\Http\Controllers\LocaleController;
use App\Modules\Platform\Http\Controllers\TimezoneController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::put('locale', [LocaleController::class, 'update'])
    ->name('locale.update');

// Unauthenticated callers are a no-op rather than a 401 — the detector
// mounts in layouts a guest can reach, and there is nowhere to store a
// guest's zone anyway (they resolve to the installation's setting).
Route::put('timezone', [TimezoneController::class, 'update'])
    ->name('timezone.update');

Route::get('setup', [SetupController::class, 'show'])->name('setup');
Route::post('setup', [SetupController::class, 'store'])->name('setup.store');
Route::get('setup/success', [SetupController::class, 'success'])->name('setup.success');

// Public share links: no login, the token itself is the authorization.
//
// Named bucket, like the public listing block further down — a bare
// `throttle:` shares one counter per IP with every other bare one in the
// application, and the tightest of them wins. These sat in the same counter
// as auth.php's `throttle:6,1` routes, so opening six share links locked the
// visitor out of the two-factor challenge and of password reset.
Route::get('s/{token}', [PublicShareController::class, 'show'])->middleware('throttle:30,1,share-link')->name('share.show');
Route::get('s/{token}/download', [PublicShareController::class, 'download'])->middleware('throttle:30,1,share-link')->name('share.download');

Route::middleware(['auth'])->group(function () {
    // The welcome middleware sits here and nowhere else — see the class
    // for why the dashboard is the right and only place to intercept.
    Route::get('dashboard', DashboardController::class)
        ->middleware(RedirectToWhatsNew::class)
        ->name('dashboard');
    Route::put('dashboard/widgets', [DashboardWidgetPreferencesController::class, 'update'])->name('dashboard.widgets.update');

    // Every account (staff or client) has their own notifications —
    // deliberately no 'staff' middleware here.
    Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('notifications/recent', [NotificationsController::class, 'recent'])->name('notifications.recent');
    Route::get('notifications/unread-count', [NotificationsController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('notifications/{notification}/read', [NotificationsController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/{notification}/unread', [NotificationsController::class, 'markUnread'])->name('notifications.unread');
    Route::post('notifications/read-all', [NotificationsController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('activity', [ActivityLogController::class, 'index'])
        ->middleware(['staff', 'can:view_actions_log'])
        ->name('activity.index');

    Route::get('activity/export', [ActivityLogController::class, 'export'])
        ->middleware(['staff', 'can:view_actions_log'])
        ->name('activity.export');

    Route::get('downloads', [DownloadsController::class, 'index'])
        ->middleware(['staff', 'can:view_actions_log'])
        ->name('downloads.index');

    // Files: staff management + downloads (policy-authorized for both
    // staff and assigned clients; bytes served by nginx via X-Accel).
    Route::get('files', [FoldersController::class, 'index'])->middleware(['staff', 'can:upload'])->name('files.index');
    Route::get('files/upload', [FilesController::class, 'create'])->middleware(['staff', 'can:upload'])->name('files.create');
    // No page posts here anymore — files/create.tsx uses the chunked
    // uploads.* endpoints below. Kept as a plain synchronous
    // fixture-seeding endpoint for tests (see the uploadImageFile()/
    // uploadPdfFile()-style helpers across ~11 suites); do not remove
    // without first extracting a non-HTTP File-fixture helper.
    Route::post('files', [FilesController::class, 'store'])->middleware(['staff', 'can:upload'])->name('files.store');

    // Must be registered before files/{file} below, or "orphans" would be
    // swallowed as a {file} route-key value first.
    Route::middleware(['staff', 'can:import_orphans'])->group(function () {
        Route::get('files/orphans', [OrphanFilesController::class, 'index'])->name('orphan-files.index');
        Route::post('files/orphans/import', [OrphanFilesController::class, 'import'])->name('orphan-files.import');
        Route::post('files/orphans/delete', [OrphanFilesController::class, 'destroy'])->name('orphan-files.delete');
    });

    Route::get('files/{file}', [FilesController::class, 'edit'])->middleware('staff')->name('files.edit');
    Route::get('files/{file}/details', [FileDetailsController::class, 'show'])->middleware('staff')->name('files.details');
    Route::get('files/{file}/activity', [FileDetailsController::class, 'activity'])->middleware('staff')->name('files.activity');
    Route::get('files/{file}/activity/history', [FileDetailsController::class, 'activityHistory'])->middleware('staff')->name('files.activity.history');
    Route::get('files/{file}/downloads', [FileDetailsController::class, 'downloads'])->middleware('staff')->name('files.downloads');
    Route::get('files/{file}/downloads/history', [FileDetailsController::class, 'downloadsHistory'])->middleware('staff')->name('files.downloads.history');
    // Must be registered before files/{file} below, or "bulk-edit" would be
    // swallowed as a {file} route-key value first.
    Route::patch('files/bulk-edit', [FilesController::class, 'bulkUpdate'])->middleware('staff')->name('files.bulk-update');
    Route::patch('files/{file}', [FilesController::class, 'update'])->middleware('staff')->name('files.update');
    Route::patch('files/{file}/move', [FilesController::class, 'move'])->middleware('staff')->name('files.move');
    Route::delete('files/{file}', [FilesController::class, 'destroy'])->middleware('staff')->name('files.destroy');
    Route::post('files/{file}/assignments', [FileAssignmentsController::class, 'store'])->middleware('staff')->name('files.assignments.store');
    Route::delete('files/{file}/assignments', [FileAssignmentsController::class, 'destroy'])->middleware('staff')->name('files.assignments.destroy');
    Route::get('files/{file}/version-candidates', [FileVersionsController::class, 'candidates'])->middleware('staff')->name('files.version.candidates');
    Route::get('files/{file}/version-preview', [FileVersionsController::class, 'preview'])->middleware('staff')->name('files.version.preview');
    Route::put('files/{file}/version', [FileVersionsController::class, 'store'])->middleware('staff')->name('files.version.store');
    Route::delete('files/{file}/version', [FileVersionsController::class, 'destroy'])->middleware('staff')->name('files.version.destroy');
    Route::post('files/{file}/share-links', [ShareLinksController::class, 'store'])->middleware('staff')->name('files.share-links.store');
    Route::delete('share-links/{shareLink}', [ShareLinksController::class, 'destroy'])->middleware('staff')->name('share-links.destroy');

    // Folders: the shared staff library tree.
    Route::get('folders/{folder}', [FoldersController::class, 'edit'])->middleware('staff')->name('folders.share');
    Route::get('folders/{folder}/details', [FileDetailsController::class, 'showFolder'])->middleware('staff')->name('folders.details');
    Route::get('folders/{folder}/activity', [FileDetailsController::class, 'folderActivity'])->middleware('staff')->name('folders.activity');
    Route::get('folders/{folder}/activity/history', [FileDetailsController::class, 'folderActivityHistory'])->middleware('staff')->name('folders.activity.history');
    Route::post('folders', [FoldersController::class, 'store'])->middleware(['staff', 'can:create_own_folders'])->name('folders.store');
    Route::patch('folders/{folder}', [FoldersController::class, 'update'])->middleware('staff')->name('folders.update');
    Route::patch('folders/{folder}/move', [FoldersController::class, 'move'])->middleware('staff')->name('folders.move');
    Route::delete('folders/{folder}', [FoldersController::class, 'destroy'])->middleware('staff')->name('folders.destroy');
    Route::post('folders/{folder}/assignments', [FolderAssignmentsController::class, 'store'])->middleware('staff')->name('folders.assignments.store');
    Route::delete('folders/{folder}/assignments', [FolderAssignmentsController::class, 'destroy'])->middleware('staff')->name('folders.assignments.destroy');

    // File categories: flat, cross-cutting labels (config, hard-deleted).
    Route::get('categories', [CategoriesController::class, 'index'])->middleware('staff')->name('categories.index');
    Route::get('categories/create', [CategoriesController::class, 'create'])->middleware(['staff', 'can:create_categories'])->name('categories.create');
    Route::post('categories', [CategoriesController::class, 'store'])->middleware(['staff', 'can:create_categories'])->name('categories.store');
    Route::get('categories/{category}', [CategoriesController::class, 'edit'])->middleware(['staff', 'can:edit_categories'])->name('categories.edit');
    Route::patch('categories/{category}', [CategoriesController::class, 'update'])->middleware(['staff', 'can:edit_categories'])->name('categories.update');
    Route::delete('categories/{category}', [CategoriesController::class, 'destroy'])->middleware(['staff', 'can:delete_categories'])->name('categories.destroy');

    // Comments: reachable by staff and clients alike, same as the download
    // and zip routes below — a client commenting on their own file is the
    // point of the feature, so the gate cannot be `staff`. Authorization is
    // per-file and per-comment inside the controller, through the policy
    // and VisibleCommentScope.
    Route::get('files/{file}/comments', [FileCommentsController::class, 'index'])->name('files.comments.index');
    Route::post('files/{file}/comments', [FileCommentsController::class, 'store'])->name('files.comments.store');
    Route::patch('comments/{comment}', [FileCommentsController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [FileCommentsController::class, 'destroy'])->name('comments.destroy');
    // Where a comment notification lands — staff and clients have no page
    // in common, so the redirect resolves it per viewer.
    Route::get('comments/go/{file}', CommentDeepLinkController::class)->name('comments.go');

    // Managing comments across every file: search, filter, approve what is
    // held, delete what should not stand. Staff with moderation rights
    // only — the list itself is still narrowed by VisibleCommentScope, so
    // holding the permission does not widen what a viewer may read.
    Route::middleware(['staff', 'can:moderate_comments'])->group(function () {
        Route::get('comments', [CommentsController::class, 'index'])->name('comments.index');
        Route::post('comments/{comment}/approve', [CommentsController::class, 'approve'])->name('comments.approve');
        Route::delete('comments/{comment}/moderate', [CommentsController::class, 'destroy'])->name('comments.moderate.destroy');
    });

    Route::get('files/{file}/download', FileDownloadController::class)->name('files.download');
    Route::get('files/{file}/thumbnail', [FileThumbnailController::class, 'thumbnail'])->name('files.thumbnail');
    Route::get('files/{file}/preview', [FileThumbnailController::class, 'preview'])->name('files.preview');

    // Zip downloads: reachable by both staff and clients, same as
    // files.download above — authorization happens per-item inside the
    // controller, not via route middleware.
    Route::post('zip-downloads', [ZipDownloadsController::class, 'store'])->name('zip-downloads.store');
    Route::get('zip-downloads/{zipDownload}', [ZipDownloadsController::class, 'show'])->name('zip-downloads.show');
    Route::get('zip-downloads/{zipDownload}/download', [ZipDownloadsController::class, 'download'])->name('zip-downloads.download');

    // Resumable chunked uploads (Uppy aws-s3 multipart contract). Shared
    // by staff and clients alike — ChunkedUploadsController's only
    // authorization check is per-session ownership (authorizeSession()),
    // so 'can:upload' alone is the correct gate. This is also the
    // client portal's own upload mechanism (see my-files/upload below).
    Route::middleware(['can:upload'])->group(function () {
        Route::post('uploads', [ChunkedUploadsController::class, 'store'])->name('uploads.store');
        Route::get('uploads/{session}/parts/{part}/sign', [ChunkedUploadsController::class, 'signPart'])->name('uploads.parts.sign');
        Route::get('uploads/{session}/parts', [ChunkedUploadsController::class, 'listParts'])->name('uploads.parts.index');
        Route::post('uploads/{session}/complete', [ChunkedUploadsController::class, 'complete'])->name('uploads.complete');
        Route::delete('uploads/{session}', [ChunkedUploadsController::class, 'destroy'])->name('uploads.destroy');
    });

    // Part receiver: the temporary signature is the grant (presigned
    // semantics); raw body, CSRF-exempt, ownership still enforced.
    Route::put('uploads/{session}/parts/{part}', [ChunkedUploadsController::class, 'putPart'])
        ->middleware(['can:upload', 'signed'])
        ->name('uploads.parts.put');

    // The API section. Staff-only; the dashboard scopes itself to the
    // viewer's own tokens unless they hold view_actions_log, so it needs no
    // permission of its own — every staff member has tokens to look after.
    Route::get('api', ApiDashboardController::class)->middleware('staff')->name('api.dashboard');

    // The reference is staff-level, not settings-level: it is read-only,
    // it describes an API whose credentials any staff member can hold, and
    // it now sits in the API section rather than under system settings.
    Route::get('api/docs', ApiDocsController::class)->middleware('staff')->name('api.docs');

    Route::get('my-files', [MyFilesController::class, 'index'])->name('my-files.index');
    // The upload mechanism itself is the shared uploads.* group above —
    // this is just the page that renders the Dashboard.
    Route::get('my-files/upload', [MyFilesController::class, 'upload'])->middleware('can:upload')->name('my-files.upload.create');
    Route::get('my-files/version-candidates', [MyFilesController::class, 'versionCandidates'])->middleware('can:upload')->name('my-files.version-candidates');

    // Client-created folders — MyFoldersController double-checks isClient()
    // and create_own_folders itself; the route-level gate here just keeps
    // staff from ever hitting these (they use folders.* instead).
    Route::middleware(['can:create_own_folders'])->group(function () {
        Route::post('my-folders', [MyFoldersController::class, 'store'])->name('my-folders.store');
        Route::patch('my-folders/{folder}', [MyFoldersController::class, 'update'])->name('my-folders.update');
        Route::delete('my-folders/{folder}', [MyFoldersController::class, 'destroy'])->name('my-folders.destroy');
    });

    // Client portal: the logged-in client's own groups.
    Route::get('my-groups', [MyGroupsController::class, 'index'])->name('my-groups.index');
    Route::post('my-groups', [MyGroupsController::class, 'store'])->name('my-groups.store');
    Route::delete('my-groups/{group}', [MyGroupsController::class, 'leave'])->name('my-groups.leave');

    // Clients live in BOTH editions — no capability gate, permissions only.
    Route::get('clients', [ClientsController::class, 'index'])->middleware(['staff', 'can:manage_clients'])->name('clients.index');
    Route::get('clients/create', [ClientsController::class, 'create'])->middleware(['staff', 'can:create_clients'])->name('clients.create');
    Route::post('clients', [ClientsController::class, 'store'])->middleware(['staff', 'can:create_clients'])->name('clients.store');
    Route::get('clients/{client}/files', [ClientFilesController::class, 'index'])->middleware(['staff', 'can:edit_clients'])->name('clients.files');
    Route::get('clients/{client}', [ClientsController::class, 'edit'])->middleware(['staff', 'can:edit_clients'])->name('clients.edit');
    Route::patch('clients/{client}', [ClientsController::class, 'update'])->middleware(['staff', 'can:edit_clients'])->name('clients.update');
    Route::delete('clients/{client}', [ClientsController::class, 'destroy'])->middleware(['staff', 'can:delete_clients'])->name('clients.destroy');
    // password.confirm for the same reason settings/two-factor carries it
    // (see routes/settings.php): this route ends somebody's second factor,
    // so a stolen session must not be enough to reach it. There it protects
    // the session owner's own account; here it protects everybody else's.
    Route::delete('clients/{client}/two-factor', [ClientsController::class, 'destroyTwoFactor'])
        ->middleware(['staff', 'can:edit_clients', 'password.confirm'])->name('clients.two-factor.destroy');

    Route::get('client-custom-fields', [ClientCustomFieldsController::class, 'index'])->middleware(['staff', 'can:manage_custom_fields'])->name('client-custom-fields.index');
    Route::get('client-custom-fields/create', [ClientCustomFieldsController::class, 'create'])->middleware(['staff', 'can:manage_custom_fields'])->name('client-custom-fields.create');
    Route::post('client-custom-fields', [ClientCustomFieldsController::class, 'store'])->middleware(['staff', 'can:manage_custom_fields'])->name('client-custom-fields.store');
    Route::get('client-custom-fields/{customField}', [ClientCustomFieldsController::class, 'edit'])->middleware(['staff', 'can:manage_custom_fields'])->name('client-custom-fields.edit');
    Route::patch('client-custom-fields/{customField}', [ClientCustomFieldsController::class, 'update'])->middleware(['staff', 'can:manage_custom_fields'])->name('client-custom-fields.update');
    Route::delete('client-custom-fields/{customField}', [ClientCustomFieldsController::class, 'destroy'])->middleware(['staff', 'can:manage_custom_fields'])->name('client-custom-fields.destroy');

    // Groups: collections of clients, both editions.
    Route::get('groups', [GroupsController::class, 'index'])->middleware(['staff', 'can:manage_groups'])->name('groups.index');
    Route::get('groups/create', [GroupsController::class, 'create'])->middleware(['staff', 'can:create_groups'])->name('groups.create');
    Route::post('groups', [GroupsController::class, 'store'])->middleware(['staff', 'can:create_groups'])->name('groups.store');
    Route::get('groups/{group}', [GroupsController::class, 'edit'])->middleware(['staff', 'can:edit_groups'])->name('groups.edit');
    Route::patch('groups/{group}', [GroupsController::class, 'update'])->middleware(['staff', 'can:edit_groups'])->name('groups.update');
    Route::delete('groups/{group}', [GroupsController::class, 'destroy'])->middleware(['staff', 'can:delete_groups'])->name('groups.destroy');
    Route::post('groups/{group}/members', [GroupMembersController::class, 'store'])->middleware(['staff', 'can:edit_groups'])->name('groups.members.store');
    Route::delete('groups/{group}/members/{member}', [GroupMembersController::class, 'destroy'])->middleware(['staff', 'can:edit_groups'])->name('groups.members.destroy');

    Route::middleware(['staff', 'can:approve_groups_memberships_requests'])->group(function () {
        Route::get('membership-requests', [MembershipRequestsController::class, 'index'])->name('membership-requests.index');
        Route::post('membership-requests/{membershipRequest}/approve', [MembershipRequestsController::class, 'approve'])->name('membership-requests.approve');
        Route::delete('membership-requests/{membershipRequest}', [MembershipRequestsController::class, 'deny'])->name('membership-requests.deny');
    });

    Route::middleware(['staff', 'can:approve_account_requests'])->group(function () {
        Route::get('account-requests', [AccountRequestsController::class, 'index'])->name('account-requests.index');
        Route::post('account-requests/{client}/approve', [AccountRequestsController::class, 'approve'])->name('account-requests.approve');
        Route::delete('account-requests/{client}', [AccountRequestsController::class, 'deny'])->name('account-requests.deny');
    });

    // Staff user & role management is community-only (managed installations
    // handle it outside the application) — both layers gate it:
    // capability + permission.
    Route::middleware(['staff', 'capability:users.manage', 'can:manage_users'])->group(function () {
        Route::get('users', [UsersController::class, 'index'])->name('users.index');
        Route::get('users/create', [UsersController::class, 'create'])->middleware('can:create_users')->name('users.create');
        Route::post('users', [UsersController::class, 'store'])->middleware('can:create_users')->name('users.store');

        // Before users/{user}, or "convert" is bound as a route-key value and
        // the tool 404s — the same ordering trap as files/orphans below.
        // Writes to both populations, so the mutation also needs
        // can:edit_clients: an Account Manager holding manage_users but not
        // edit_clients should not mint a client through a side door.
        Route::get('users/convert', [AccountConversionController::class, 'index'])
            ->middleware('can:edit_users')->name('users.convert');
        Route::post('users/convert/{user}', [AccountConversionController::class, 'store'])
            ->middleware(['can:edit_users', 'can:edit_clients'])->name('users.convert.store');

        Route::get('users/{user}', [UsersController::class, 'edit'])->middleware('can:edit_users')->name('users.edit');
        Route::patch('users/{user}', [UsersController::class, 'update'])->middleware('can:edit_users')->name('users.update');
        Route::delete('users/{user}', [UsersController::class, 'destroy'])->middleware('can:delete_users')->name('users.destroy');
        // See the note on clients.two-factor.destroy above.
        Route::delete('users/{user}/two-factor', [UsersController::class, 'destroyTwoFactor'])
            ->middleware(['can:edit_users', 'password.confirm'])->name('users.two-factor.destroy');

        Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RolesController::class, 'create'])->name('roles.create');
        Route::post('roles', [RolesController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}', [RolesController::class, 'edit'])->name('roles.edit');
        Route::patch('roles/{role}', [RolesController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Public group listings: no login, gated by Setting::PublicListingEnabled
// and matched against Setting::PublicListingSlug. Marked ->fallback() so
// these wildcards only ever apply when nothing else in the whole route
// collection matches first — registration order alone isn't enough,
// since routes registered later at runtime (e.g. in tests) would
// otherwise still be shadowed by these being defined here in web.php.
// **Each of these needs its own throttle bucket, and that is what the third
// argument is.** Without it `throttle:60,1` and `throttle:30,1` are not two
// limits — they are two readings of one counter. Laravel keys the bare form
// on `sha1(domain|ip)` alone (ThrottleRequests::resolveRequestSignature),
// with no route in it, so every public route increments the same number and
// the tightest limit on it decides for all of them. The visible symptom was
// a visitor who had posted no comments at all being told "Too Many
// Attempts" on their first one, having spent the budget on page views and
// thumbnails. Never add a bare `throttle:` to this block.
Route::get('{publicSlug}', [PublicGroupsController::class, 'index'])->middleware('throttle:60,1,public-browse')->name('public.index')->fallback();
Route::get('{publicSlug}/files/{file:slug}', [PublicGroupsController::class, 'showFile'])->middleware('throttle:60,1,public-browse')->name('public.file')->fallback();
// Comments on a public file. Reading rides with the rest of browsing;
// writing gets its own, much tighter one, since anyone at all can reach it.
//
// Ten a minute rather than five: the bucket is per visitor, not per file, so
// somebody working down a gallery leaving a note on each photo spends it the
// way a spammer would, and a refused attempt (a missing name, an audience
// that is not on offer) costs exactly as much as a posted one. A flood looks
// nothing like ten.
Route::get('{publicSlug}/files/{file:slug}/comments', [PublicFileCommentsController::class, 'index'])->middleware('throttle:60,1,public-browse')->name('public.comments.index')->fallback();
Route::post('{publicSlug}/files/{file:slug}/comments', [PublicFileCommentsController::class, 'store'])->middleware('throttle:10,1,public-comment')->name('public.comments.store')->fallback();
// One request per image, so a single grid of thumbnails is dozens of them —
// a page-sized burst is normal traffic here in a way it is not anywhere else
// on this list, which is why the number is high. It is still a hard ceiling
// against scraping the whole listing.
Route::get('{publicSlug}/files/{file:slug}/thumbnail', [PublicGroupsController::class, 'thumbnail'])->middleware('throttle:240,1,public-thumbnail')->name('public.thumbnail')->fallback();
Route::get('{publicSlug}/files/{file:slug}/download', [PublicGroupsController::class, 'download'])->middleware('throttle:30,1,public-download')->name('public.download')->fallback();
// Must be registered before the generic {groupSlug} catch-all below, or
// "folders" would be swallowed as a group slug value first.
Route::get('{publicSlug}/folders/{folderSlug}', [PublicFoldersController::class, 'show'])->middleware('throttle:60,1,public-browse')->name('public.folder')->fallback();
Route::get('{publicSlug}/{groupSlug}', [PublicGroupsController::class, 'show'])->middleware('throttle:60,1,public-browse')->name('public.show')->fallback();
