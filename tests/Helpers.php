<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Models\Folder;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\ExternalStorageSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/*
|--------------------------------------------------------------------------
| Cross-file test helpers
|--------------------------------------------------------------------------
|
| Pest declares the functions in a test file as ordinary globals, so a helper
| written in one file is visible to every other file — but only once that
| first file has been loaded. Running the whole suite loads everything and
| hides the problem; running a single file, or anything with --filter, fails
| with "Call to undefined function".
|
| Helpers used by more than one test file therefore live here, loaded from
| tests/Pest.php so they exist no matter which files a given run touches. A
| helper used by exactly one file should stay in that file — this is for
| shared ones only, and moving a private helper here would just make it
| harder to find.
|
*/

/** A folder created through the real service, so path/depth invariants hold. */
function makeFolder(string $name, ?Folder $parent = null): Folder
{
    return app(FolderService::class)->create($name, $parent);
}

/** A staff user whose role has exactly the given permission keys. */
function staffWithPermissions(array $permissions): User
{
    $role = Role::query()->create(['name' => 'Role '.Str::random(6)]);
    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

/**
 * Clear the password.confirm gate by actually confirming the password,
 * rather than stamping the session key directly — that way tests keep
 * exercising the real gate instead of asserting around it.
 */
function confirmPassword(User $user): void
{
    test()->actingAs($user)->post('/confirm-password', ['password' => 'password'])->assertRedirect();
}

/**
 * Enrol a user in two-factor authentication for real — start, confirm with
 * a live TOTP code, and return the secret so a test can generate more.
 */
function enableTwoFactor(User $user): string
{
    confirmPassword($user);
    test()->actingAs($user)->post('/settings/two-factor');

    $secret = $user->refresh()->two_factor_secret;
    assert($secret !== null);

    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->actingAs($user)->post('/settings/two-factor/confirm', ['code' => $code]);

    expect($user->refresh()->hasTwoFactorEnabled())->toBeTrue();

    // Forget the replay guard for the code consumed during enrollment so
    // tests can log in within the same TOTP time window.
    Cache::flush();

    return $secret;
}

/**
 * Drop the per-request state the previous request memoised, so the next
 * one in the same test genuinely starts cold.
 *
 * A test process handles several requests against one application
 * instance. Two caches are deliberately request-scoped in production and
 * therefore leak across requests here: AuthManager holds the guard and the
 * User it resolved, and PermissionChecker is a singleton that memoises a
 * role's granted keys. A test that changes an account mid-test —
 * deactivating it, stripping a permission — and asserts on the *next*
 * request would otherwise be asserting against stale memory rather than
 * the database, and would pass no matter what the code did.
 */
function forgetRequestState(): void
{
    app('auth')->forgetGuards();
    app()->forgetInstance(PermissionChecker::class);
}

/** Bytes on disk with no File row pointing at them. */
function makeOrphanFile(string $path, string $content = 'hello-world', string $disk = 'files'): void
{
    Storage::disk($disk)->put($path, $content);
}

/** A complete, valid payload for the email settings form. */
function validEmailSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'email_notifications_enabled' => true,
        'admin_notification_emails' => ['admin@example.com'],
        'provider' => 'custom',
        'host' => 'smtp.example.test',
        'port' => 587,
        'username' => null,
        'password' => null,
        'encryption' => 'tls',
        'from_address' => 'hello@example.com',
        'from_name' => 'ProjectSend',
    ], $overrides);
}

/** Upload a real image through the intake endpoint and return its File row. */
function uploadImageFile(User $as, string $name = 'photo.jpg'): File
{
    test()->actingAs($as)->post('/files', [
        'file' => UploadedFile::fake()->image($name, 200, 100),
        'name' => '',
        'description' => '',
    ]);

    return File::query()->latest('id')->firstOrFail();
}

/** A named PDF document, for tests that assert on the name/description they set. */
function uploadDocumentFile(User $as, string $name = 'contract.pdf'): File
{
    test()->actingAs($as)->post('/files', [
        'file' => UploadedFile::fake()->create($name, 512, 'application/pdf'),
        'name' => '',
        'description' => 'Test document',
    ]);

    return File::query()->latest('id')->firstOrFail();
}

/**
 * A small PDF filed under an explicit display name and folder — for the
 * scoping tests, where which client can see which file is the point and the
 * bytes are irrelevant.
 */
function uploadNamedFile(User $as, string $name, ?int $folderId = null): File
{
    test()->actingAs($as)->post('/files', [
        'file' => UploadedFile::fake()->create($name.'.pdf', 12, 'application/pdf'),
        'name' => $name,
        'description' => '',
        'folder_id' => $folderId,
    ]);

    return File::query()->latest('id')->firstOrFail();
}

/**
 * Assigns a file directly to a client, the row the assignments endpoint
 * would have written. Use this when a test needs the client to *have*
 * access; go through the endpoint when the sharing itself (activity,
 * notifications, permissions) is what's under test.
 */
function shareFileWith(File $file, User $client): void
{
    FileAssignment::query()->create([
        'file_id' => $file->id,
        'assignable_type' => $client->getMorphClass(),
        'assignable_id' => $client->id,
    ]);
}

/** The group-keyed sibling of shareFileWith(), for public-listing cases. */
function shareFileWithGroup(File $file, Group $group): void
{
    FileAssignment::query()->create([
        'file_id' => $file->id,
        'assignable_type' => $group->getMorphClass(),
        'assignable_id' => $group->id,
    ]);
}

/** Activates external storage (Community-only) for the rest of the test. */
function activateExternalStorage(): void
{
    ExternalStorageSettings::current()->fill([
        'active' => true,
        'key' => 'AKIAEXAMPLE',
        'secret' => 'shh',
        'bucket' => 'my-bucket',
        'region' => 'us-east-1',
    ])->save();
    app(ExternalStorageConfigApplier::class)->flush();
}

/**
 * A public file row, with no bytes behind it — enough for any listing or
 * permission assertion that never opens the file.
 */
function publicListingFile(array $overrides = []): File
{
    return File::factory()->create(array_merge([
        'uploaded_by' => User::factory()->create()->id,
        'name' => 'Report',
        'original_name' => 'report.pdf',
        'path' => '2026/08/'.Str::uuid()->toString().'.pdf',
        'mime_type' => 'application/pdf',
        'size' => 2048,
        'public' => true,
    ], $overrides));
}

/**
 * A real, thumbnailable public image on the faked "files" disk — unlike
 * publicListingFile()'s bare PDF row, this one has actual bytes GD can
 * decode, needed to exercise the thumbnail-generation path.
 */
function publicListingImageFile(User $uploader): File
{
    test()->actingAs($uploader)->post('/files', [
        'file' => UploadedFile::fake()->image('photo.jpg', 200, 100),
        'name' => '',
        'description' => '',
    ]);

    $file = File::query()->latest('id')->firstOrFail();
    $file->update(['public' => true]);

    return $file;
}
