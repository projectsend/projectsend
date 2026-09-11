<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GHSA-rxf8-wh8v-jm9j, and it is the sibling of GHSA-237r-jx85-j3hr rather
 * than a new discovery: that advisory decided that putting content in a
 * public folder is publication, put the rule in Folder::uploadableBy(), and
 * wired it into the upload paths. Content arrives in a folder four other
 * ways — moved, bulk-moved, reparented through the edit form, or carried in
 * by its own folder being dragged somewhere — and none of them asked. So an
 * editor deliberately denied the publication permission could publish to the
 * anonymous site by choosing where things land.
 *
 * The fix to a report deserves the scrutiny the report got. These tests are
 * one per sink for that reason, and each has a private-destination control
 * beside it: the boundary is about publishing, not about moving.
 */
beforeEach(function () {
    Storage::fake('files');

    // A staff user must exist or every request redirects to setup.
    $this->admin = User::factory()->create();

    $this->publicFolder = Folder::query()->create([
        'name' => 'Brochures', 'slug' => 'brochures', 'path' => '/', 'public' => true,
    ]);
    $this->privateFolder = Folder::query()->create([
        'name' => 'Internal', 'slug' => 'internal', 'path' => '/', 'public' => false,
    ]);
});

/**
 * An editor: may change files, may not publish them. The exact role the
 * permission matrix says cannot reach the public site.
 */
function editorWhoCannotPublish(): User
{
    $role = Role::query()->create(['name' => 'Editor '.Str::random(6)]);

    foreach ([Permission::Upload, Permission::EditFiles, Permission::EditOthersFiles, Permission::CreateOwnFolders] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

function editorWhoCanPublish(): User
{
    $user = editorWhoCannotPublish();
    RolePermission::query()->create(['role_id' => $user->role_id, 'permission' => Permission::UploadPublic->value]);

    return $user;
}

function privateFileOwnedBy(User $owner, ?Folder $folder = null): File
{
    return File::factory()->create([
        'name' => 'Salaries',
        'slug' => 'salaries-'.Str::random(6),
        'uploaded_by' => $owner->id,
        'folder_id' => $folder?->id,
        'public' => false,
    ]);
}

test('the drag-and-drop move cannot publish', function () {
    $editor = editorWhoCannotPublish();
    $file = privateFileOwnedBy($editor, $this->privateFolder);

    $this->actingAs($editor)
        ->patch("/files/{$file->id}/move", ['folder_id' => $this->publicFolder->id])
        ->assertForbidden();

    expect($file->fresh()->folder_id)->toBe($this->privateFolder->id);
});

test('the same move into a private folder still works', function () {
    $editor = editorWhoCannotPublish();
    $file = privateFileOwnedBy($editor);

    $this->actingAs($editor)
        ->patch("/files/{$file->id}/move", ['folder_id' => $this->privateFolder->id])
        ->assertRedirect();

    expect($file->fresh()->folder_id)->toBe($this->privateFolder->id);
});

test('an editor who may publish can still move into a public folder', function () {
    $editor = editorWhoCanPublish();
    $file = privateFileOwnedBy($editor, $this->privateFolder);

    $this->actingAs($editor)
        ->patch("/files/{$file->id}/move", ['folder_id' => $this->publicFolder->id])
        ->assertRedirect();

    expect($file->fresh()->folder_id)->toBe($this->publicFolder->id);
});

test('bulk edit cannot publish a selection', function () {
    $editor = editorWhoCannotPublish();
    $one = privateFileOwnedBy($editor, $this->privateFolder);
    $two = privateFileOwnedBy($editor, $this->privateFolder);

    $this->actingAs($editor)->patch('/files/bulk-edit', [
        'file_ids' => [$one->id, $two->id],
        'folder_action' => 'move',
        'folder_id' => $this->publicFolder->id,
        'description_action' => 'no_change',
        'expiration_action' => 'no_change',
    ])->assertForbidden();

    expect($one->fresh()->folder_id)->toBe($this->privateFolder->id)
        ->and($two->fresh()->folder_id)->toBe($this->privateFolder->id);
});

test('bulk edit into a private folder still works', function () {
    $editor = editorWhoCannotPublish();
    $file = privateFileOwnedBy($editor);

    $this->actingAs($editor)->patch('/files/bulk-edit', [
        'file_ids' => [$file->id],
        'folder_action' => 'move',
        'folder_id' => $this->privateFolder->id,
        'description_action' => 'no_change',
        'expiration_action' => 'no_change',
    ])->assertRedirect();

    expect($file->fresh()->folder_id)->toBe($this->privateFolder->id);
});

test('the edit form cannot publish by reparenting', function () {
    $editor = editorWhoCannotPublish();
    $file = privateFileOwnedBy($editor, $this->privateFolder);

    $this->actingAs($editor)->patch("/files/{$file->id}", [
        'name' => 'Salaries',
        'folder_id' => $this->publicFolder->id,
    ])->assertForbidden();

    expect($file->fresh()->folder_id)->toBe($this->privateFolder->id);
});

test('the API twin cannot publish by reparenting either', function () {
    $editor = editorWhoCannotPublish();
    $file = privateFileOwnedBy($editor, $this->privateFolder);

    // The abilities a token may hold are bounded by the role behind it, so
    // this token is exactly as unable to publish as its owner.
    $token = $editor->createToken('t', [
        Permission::EditFiles->value,
        Permission::EditOthersFiles->value,
    ])->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/files/{$file->id}", ['folder_id' => $this->publicFolder->id])
        ->assertForbidden();

    expect($file->fresh()->folder_id)->toBe($this->privateFolder->id);
});

test('dragging a whole folder into a public one cannot publish its contents', function () {
    $editor = editorWhoCannotPublish();
    $file = privateFileOwnedBy($editor, $this->privateFolder);

    $this->actingAs($editor)
        ->patch("/folders/{$this->privateFolder->id}/move", ['parent_id' => $this->publicFolder->id])
        ->assertForbidden();

    expect($this->privateFolder->fresh()->parent_id)->toBeNull()
        ->and($file->fresh()->isEffectivelyPublic())->toBeFalse();
});

test('moving a folder somewhere private still works', function () {
    $editor = editorWhoCannotPublish();
    $nest = Folder::query()->create(['name' => 'Nested', 'slug' => 'nested', 'path' => '/', 'public' => false]);

    $this->actingAs($editor)
        ->patch("/folders/{$nest->id}/move", ['parent_id' => $this->privateFolder->id])
        ->assertRedirect();

    expect($nest->fresh()->parent_id)->toBe($this->privateFolder->id);
});

test('a private file stays unreachable to a stranger across every reparent path', function () {
    // The end of the chain the advisory follows, and the only assertion that
    // is really about impact: placement makes isEffectivelyPublic() true, and
    // the anonymous routes treat that as the whole of the authorization.
    app(Settings::class)->set(Setting::PublicListingEnabled, true);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');

    $editor = editorWhoCannotPublish();
    $file = privateFileOwnedBy($editor, $this->privateFolder);

    $this->get("/public/files/{$file->slug}/download")->assertNotFound();

    foreach ([
        fn () => $this->actingAs($editor)->patch("/files/{$file->id}/move", ['folder_id' => $this->publicFolder->id]),
        fn () => $this->actingAs($editor)->patch("/files/{$file->id}", ['name' => 'Salaries', 'folder_id' => $this->publicFolder->id]),
        fn () => $this->actingAs($editor)->patch("/folders/{$this->privateFolder->id}/move", ['parent_id' => $this->publicFolder->id]),
    ] as $attempt) {
        $attempt();

        // The anonymous fetch first, because that is the claim: not that a
        // flag stayed off, but that a stranger with no session, no token
        // and no assignment still cannot read the bytes.
        $this->get("/public/files/{$file->slug}/download")->assertNotFound();
        expect($file->fresh()->isEffectivelyPublic())->toBeFalse();
    }
});
