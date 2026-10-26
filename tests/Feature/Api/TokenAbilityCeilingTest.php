<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A token holds what it was granted, not what its owner holds.
 *
 * Every own/others route accepts either key of the pair, because which one
 * applies depends on the file. The policy then picks the right key, but
 * asked the role -- so a token minted for "Edit own files" edited other
 * people's files whenever its owner could. Reported by @Ratstack2 as
 * GHSA-w79h-hrf3-846q.
 *
 * Real tokens throughout, not Sanctum::actingAs(): the cap reads the token
 * the request authenticated with.
 */
beforeEach(function () {
    Storage::fake('files');

    $this->owner = User::factory()->role(SystemRole::AccountManager)->create();
    $this->colleague = User::factory()->create();

    $this->own = File::factory()->create(['uploaded_by' => $this->owner->id, 'description' => 'own']);
    $this->others = File::factory()->create(['uploaded_by' => $this->colleague->id, 'description' => 'others']);
});

function ceilingToken(User $user, array $abilities): string
{
    return $user->createToken('integration', $abilities)->plainTextToken;
}

test('the owner holds both edit keys, so the role alone would allow every case below', function () {
    expect($this->owner->can('edit_files'))->toBeTrue()
        ->and($this->owner->can('edit_others_files'))->toBeTrue()
        ->and($this->owner->can('delete_others_files'))->toBeTrue();
});

test('an edit-own token edits its owner\'s file', function () {
    $this->withToken(ceilingToken($this->owner, ['edit_files']))
        ->patchJson("/api/v1/files/{$this->own->id}", ['description' => 'changed'])
        ->assertOk();

    expect($this->own->fresh()->description)->toBe('changed');
});

test('an edit-own token cannot edit somebody else\'s file', function () {
    $this->withToken(ceilingToken($this->owner, ['edit_files']))
        ->patchJson("/api/v1/files/{$this->others->id}", ['description' => 'changed'])
        ->assertForbidden();

    expect($this->others->fresh()->description)->toBe('others');
});

test('an edit-others token edits somebody else\'s file, and not its owner\'s', function () {
    $token = ceilingToken($this->owner, ['edit_others_files']);

    $this->withToken($token)
        ->patchJson("/api/v1/files/{$this->others->id}", ['description' => 'changed'])
        ->assertOk();

    // The pair is two keys on a role, and two keys on a token.
    $this->withToken($token)
        ->patchJson("/api/v1/files/{$this->own->id}", ['description' => 'changed'])
        ->assertForbidden();
});

test('a delete-own token cannot delete somebody else\'s file', function () {
    $this->withToken(ceilingToken($this->owner, ['delete_files']))
        ->deleteJson("/api/v1/files/{$this->others->id}")
        ->assertForbidden();

    expect(File::query()->whereKey($this->others->id)->exists())->toBeTrue();
});

test('an edit-own token cannot share somebody else\'s file', function () {
    $this->withToken(ceilingToken($this->owner, ['edit_files']))
        ->postJson("/api/v1/files/{$this->others->id}/assignments", [])
        ->assertForbidden();
});

test('an edit-own token cannot rename or delete somebody else\'s folder', function () {
    $folder = Folder::query()->create([
        'name' => 'Theirs', 'slug' => 'theirs', 'path' => '/', 'created_by' => $this->colleague->id,
    ]);

    $this->withToken(ceilingToken($this->owner, ['edit_files', 'delete_files']))
        ->patchJson("/api/v1/folders/{$folder->id}", ['name' => 'Mine now'])
        ->assertForbidden();

    $this->withToken(ceilingToken($this->owner, ['edit_files', 'delete_files']))
        ->deleteJson("/api/v1/folders/{$folder->id}")
        ->assertForbidden();

    expect($folder->fresh()?->name)->toBe('Theirs');
});

test('an administrator\'s token is capped too', function () {
    $admin = User::factory()->create();

    $this->withToken(ceilingToken($admin, ['edit_files']))
        ->patchJson("/api/v1/files/{$this->others->id}", ['description' => 'changed'])
        ->assertForbidden();
});

test('the cap holds its own user only', function () {
    // Other people's permissions are still read from their roles: deciding
    // whom to notify, for one, asks about staff who are not the caller.
    $checker = app(PermissionChecker::class);
    $token = PersonalAccessToken::findToken(ceilingToken($this->owner, ['edit_files']));

    $checker->limitTo($this->owner, $token, ['edit_files', 'edit_others_files']);

    expect($checker->allows($this->owner, Permission::EditOthersFiles))->toBeFalse()
        ->and($checker->allows($this->colleague, Permission::EditOthersFiles))->toBeTrue();
});
