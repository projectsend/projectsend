<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * A file can be in a client-scoped staff member's library while the folder
 * it sits in, or the original it is a new version of, is not. The file is
 * theirs to see; the names around it are not. A client's home folder is
 * named after the client, and an original's name says what it is.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach (['upload', 'edit_files'] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    $this->mine = User::factory()->client()->create(['name' => 'Mine']);
    $this->rep = User::factory()->create(['role_id' => $role->id]);
    $this->rep->assignedClients()->sync([$this->mine->id]);
});

function outOfScopeFile(array $attributes): File
{
    return File::factory()->create(array_merge([
        'name' => 'a-file', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 10,
    ], $attributes));
}

test('a file\'s folder is not named when the viewer cannot reach the folder', function () {
    $private = makeFolder('Terminations Q4');
    $private->forceFill(['created_by' => $this->admin->id])->save();
    $file = outOfScopeFile(['uploaded_by' => $this->admin->id, 'folder_id' => $private->id]);
    shareFileWith($file, $this->mine);
    app()->forgetScopedInstances();

    $details = $this->actingAs($this->rep)->getJson("/files/{$file->id}/details")->assertOk();
    expect($details->json('folder'))->toBeNull()
        ->and($details->getContent())->not->toContain('Terminations Q4');

    $token = $this->rep->createToken('t', ['upload'])->plainTextToken;
    $api = $this->withToken($token)->getJson("/api/v1/files/{$file->id}")->assertOk();
    expect($api->json('data.folder'))->toBeNull()
        ->and($api->getContent())->not->toContain('Terminations Q4');
});

test('a revision\'s original is not named when the viewer cannot see it', function () {
    $original = outOfScopeFile(['uploaded_by' => $this->admin->id, 'name' => 'Board minutes draft']);
    $revision = outOfScopeFile(['uploaded_by' => $this->rep->id, 'previous_file_id' => $original->id, 'version_root_id' => $original->id]);
    app()->forgetScopedInstances();

    $details = $this->actingAs($this->rep)->getJson("/files/{$revision->id}/details")->assertOk();
    expect($details->json('sharing_root'))->toBeNull();

    $this->actingAs($this->rep)->get("/files/{$revision->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('sharing_root.name', 'the original version'),
    );

    $refused = $this->actingAs($this->rep)->post("/files/{$revision->id}/assignments", ['type' => 'client', 'id' => $this->mine->id]);
    $refused->assertSessionHasErrors('id');
    expect(session('errors')->first('id'))->not->toContain('Board minutes draft');

    $token = $this->rep->createToken('t', ['upload'])->plainTextToken;
    expect($this->withToken($token)->getJson("/api/v1/files/{$revision->id}")->json('data.sharing_root_id'))->toBeNull();
});

test('an original the viewer can see is still named', function () {
    $original = outOfScopeFile(['uploaded_by' => $this->rep->id, 'name' => 'My original']);
    $revision = outOfScopeFile(['uploaded_by' => $this->rep->id, 'previous_file_id' => $original->id, 'version_root_id' => $original->id]);

    $this->actingAs($this->rep)->getJson("/files/{$revision->id}/details")->assertJsonPath('sharing_root.name', 'My original');
});
