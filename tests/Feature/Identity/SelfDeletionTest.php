<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Erasure\Events\ResolvingSelfDeletion;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * What deleting your own account does to your files — SelfDeletion.
 *
 * Rule 1: from the moment the account is soft-deleted, its files are
 * served to nobody but staff. Rule 2, optional: they are deleted there and
 * then. Both follow Setting::AccountSelfDeleteScope.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    // Settings survive the per-test rollback in the cache, so every one
    // these tests lean on is set rather than assumed.
    $settings = app(Settings::class);
    $settings->set(Setting::AccountSelfDeleteFiles, 'after_grace_period');
    $settings->set(Setting::AccountSelfDeleteScope, 'any');
    $settings->set(Setting::AccountErasureGraceDays, 30);
    $settings->set(Setting::PublicListingEnabled, true);
    $settings->set(Setting::PublicListingSlug, 'public');
    $settings->set(Setting::Theme, 'default');
});

function selfDeletionFile(User $uploader, array $overrides = []): File
{
    $file = File::factory()->create(array_merge(['uploaded_by' => $uploader->id], $overrides));
    Storage::disk('files')->put($file->path, 'bytes');

    return $file;
}

function selfDeletionShareLink(File $file): string
{
    $token = Str::random(32);
    ShareLink::query()->create(['shareable_type' => $file->getMorphClass(), 'shareable_id' => $file->id, 'token' => $token]);

    return $token;
}

function selfDeleteAccount(User $user): void
{
    test()->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertRedirect('/');
    auth()->logout();
}

test('a self-deleted client\'s files stop being served to anybody but staff', function () {
    $owner = User::factory()->client()->create();
    $recipient = User::factory()->client()->create();
    $file = selfDeletionFile($owner, ['public' => true]);
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $recipient->id]);
    $token = selfDeletionShareLink($file);

    // Served everywhere first, so what follows is the deletion's doing.
    $this->actingAs($recipient)->get("/files/{$file->id}/download")->assertOk();
    auth()->logout();
    $this->get("/s/{$token}")->assertInertia(fn ($page) => $page->where('status', 'active'));
    $this->get("/public/files/{$file->slug}")->assertOk();

    selfDeleteAccount($owner);

    $this->actingAs($recipient)->get("/files/{$file->id}/download")->assertForbidden();
    $this->actingAs($recipient)->get("/files/{$file->id}/thumbnail")->assertForbidden();
    $this->actingAs($recipient)->get('/my-files')
        ->assertInertia(fn ($page) => $page->where('files', fn ($files) => collect($files)->pluck('id')->doesntContain($file->id)));
    auth()->logout();

    // Exactly what a link that never existed answers.
    $this->get("/s/{$token}")->assertInertia(fn ($page) => $page->where('status', 'not_found'));
    $this->get("/s/{$token}/download")->assertRedirect(route('share.show', $token));

    $this->get("/public/files/{$file->slug}")->assertNotFound();
    $this->get("/public/files/{$file->slug}/download")->assertNotFound();
    $this->get("/public/files/{$file->slug}/comments")->assertNotFound();
    $this->get('/public')->assertInertia(fn ($page) => $page->where('files', []));

    // Staff keep them: the grace period is for undoing a mistake.
    $this->actingAs($this->admin)->get("/files/{$file->id}/download")->assertOk();

    // Nothing was deleted to get here.
    expect(File::query()->find($file->id))->not->toBeNull()
        ->and(ShareLink::query()->where('token', $token)->exists())->toBeTrue();
});

test('group and shared-folder access is withdrawn too', function () {
    $owner = User::factory()->client()->create();
    $member = User::factory()->client()->create();
    $group = Group::query()->create(['name' => 'Team']);
    $group->members()->attach($member->id);

    $folder = Folder::query()->create(['name' => 'Shared']);
    $this->actingAs($this->admin)->post("/folders/{$folder->id}/assignments", ['type' => 'client', 'id' => $member->id]);
    $inFolder = selfDeletionFile($owner, ['folder_id' => $folder->id]);

    $viaGroup = selfDeletionFile($owner);
    $this->actingAs($this->admin)->post("/files/{$viaGroup->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    selfDeleteAccount($owner);

    $this->actingAs($member)->get("/files/{$inFolder->id}/download")->assertForbidden();
    $this->actingAs($member)->get("/files/{$viaGroup->id}/download")->assertForbidden();
});

test('restoring the account serves its files again', function () {
    $owner = User::factory()->client()->create();
    $file = selfDeletionFile($owner);
    $token = selfDeletionShareLink($file);

    selfDeleteAccount($owner);
    $this->get("/s/{$token}")->assertInertia(fn ($page) => $page->where('status', 'not_found'));

    User::withTrashed()->findOrFail($owner->id)->restore();

    $this->get("/s/{$token}")->assertInertia(fn ($page) => $page->where('status', 'active'));
});

test('a file whose uploader was erased long ago is still served', function () {
    // The null branch of notWithdrawn(): NOT IN never matches a NULL —
    // once the list has anything in it. An empty list matches everything,
    // NULL included, which is why somebody else is deleted first.
    selfDeleteAccount(User::factory()->client()->create());
    $recipient = User::factory()->client()->create();
    $file = selfDeletionFile($this->admin);
    $file->forceFill(['uploaded_by' => null])->save();
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $recipient->id]);

    $this->actingAs($recipient)->get("/files/{$file->id}/download")->assertOk();
});

test('with the clients-only scope, a staff member deleting their account leaves their uploads served', function () {
    $staff = User::factory()->create();
    $recipient = User::factory()->client()->create();
    $file = selfDeletionFile($staff);
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $recipient->id]);

    app(Settings::class)->set(Setting::AccountSelfDeleteScope, 'clients');
    selfDeleteAccount($staff);

    $this->actingAs($recipient)->get("/files/{$file->id}/download")->assertOk();

    // The same deletion under the default scope withdraws them.
    app(Settings::class)->set(Setting::AccountSelfDeleteScope, 'any');

    $this->actingAs($recipient)->get("/files/{$file->id}/download")->assertForbidden();
});

test('by default a self-delete leaves the files for the grace period', function () {
    $owner = User::factory()->client()->create();
    $file = selfDeletionFile($owner);

    selfDeleteAccount($owner);

    expect(File::query()->find($file->id))->not->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::AccountContentCascadeDeleted)->exists())->toBeFalse();
});

test('"right away" deletes their own uploads, and their folders only if nothing else is left', function () {
    app(Settings::class)->set(Setting::AccountSelfDeleteFiles, 'immediately');

    $owner = User::factory()->client()->create();
    $file = selfDeletionFile($owner);
    $emptyFolder = Folder::query()->create(['name' => 'Mine', 'created_by' => $owner->id]);
    $sharedFolder = Folder::query()->create(['name' => 'Ours', 'created_by' => $owner->id]);
    $somebodyElses = selfDeletionFile($this->admin, ['folder_id' => $sharedFolder->id]);
    $theirs = selfDeletionFile($owner, ['folder_id' => $sharedFolder->id]);

    selfDeleteAccount($owner);

    expect(File::query()->find($file->id))->toBeNull()
        ->and(File::query()->find($theirs->id))->toBeNull()
        ->and(File::query()->find($somebodyElses->id))->not->toBeNull()
        ->and(Folder::query()->find($emptyFolder->id))->toBeNull()
        ->and(Folder::query()->find($sharedFolder->id)?->created_by)->toBeNull()
        ->and(Folder::query()->find($sharedFolder->id))->not->toBeNull();

    // The account itself keeps its ordinary grace period.
    expect(User::withTrashed()->findOrFail($owner->id)->erase_after)->not->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::AccountContentCascadeDeleted)->exists())->toBeTrue();
});

test('"right away" does not reach a staff member when the scope is clients only', function () {
    app(Settings::class)->set(Setting::AccountSelfDeleteFiles, 'immediately');
    app(Settings::class)->set(Setting::AccountSelfDeleteScope, 'clients');

    $staff = User::factory()->create();
    $file = selfDeletionFile($staff);

    selfDeleteAccount($staff);

    expect(File::query()->find($file->id))->not->toBeNull();
});

test('the delete screen says what will happen to the files', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/settings/delete-account')->assertInertia(fn ($page) => $page
        ->where('filesWithdrawn', true)
        ->where('filesDeletedImmediately', false));

    app(Settings::class)->set(Setting::AccountSelfDeleteFiles, 'immediately');

    $this->actingAs($client)->get('/settings/delete-account')->assertInertia(fn ($page) => $page
        ->where('filesWithdrawn', true)
        ->where('filesDeletedImmediately', true));

    // Neither happens to staff under the clients-only scope, so neither
    // is said to them.
    app(Settings::class)->set(Setting::AccountSelfDeleteScope, 'clients');

    $this->actingAs(User::factory()->create())->get('/settings/delete-account')->assertInertia(fn ($page) => $page
        ->where('filesWithdrawn', false)
        ->where('filesDeletedImmediately', false));
});

test('a platform can make it "right away", and the settings screen says so instead of offering a switch', function () {
    Event::listen(ResolvingSelfDeletion::class, fn (ResolvingSelfDeletion $event) => $event->deleteFilesImmediately());

    $this->actingAs($this->admin)->get('/system/settings/privacy')->assertInertia(fn ($page) => $page
        ->where('account_self_delete_files', 'immediately')
        ->where('account_self_delete_files_managed', true));

    // What comes back from the disabled control is not stored.
    $this->actingAs($this->admin)->patch('/system/settings/privacy', [
        'download_ip_logging' => 'all',
        'account_erasure_grace_days' => 30,
        'account_erasure_content_action' => 'cascade_delete',
        'account_self_delete_files' => 'after_grace_period',
        'account_self_delete_scope' => 'any',
        'api_request_log_retention_days' => 30,
        'discourage_search_indexing' => false,
    ])->assertSessionHasNoErrors();

    $owner = User::factory()->client()->create();
    $file = selfDeletionFile($owner);
    selfDeleteAccount($owner);

    expect(File::query()->find($file->id))->toBeNull();
});
