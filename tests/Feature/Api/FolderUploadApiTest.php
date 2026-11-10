<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;

/*
 * Issue #1840 over the API: the chunked upload routes are the browser's
 * controller mounted for tokens, so `relative_path` arrives here too, and
 * must be held to the same rules.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $this->actingAs($this->admin);
    $this->stranger = User::factory()->client()->create();
    $this->strangersFolder = app(FolderService::class)->create('Strangers', null);
    $this->post("/folders/{$this->strangersFolder->id}/assignments", ['type' => 'client', 'id' => $this->stranger->id]);
    auth()->logout();

    $this->manager = User::factory()->role(SystemRole::ClientManager)->create();
    $this->manager->assignedClients()->sync([User::factory()->client()->create()->id]);
});

test('a token upload with a folder path recreates the folders', function () {
    $token = $this->admin->createToken('t', [Permission::Upload->value, Permission::CreateOwnFolders->value])->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/uploads', [
        'filename' => 'plan.pdf',
        'size' => 2048,
        'folder_id' => $this->strangersFolder->id,
        'relative_path' => 'Project_A/Drawings/plan.pdf',
    ])->assertOk();

    $folder = Folder::query()->findOrFail(UploadSession::query()->findOrFail($response->json('uploadId'))->folder_id);

    expect($folder->name)->toBe('Drawings')
        ->and(Folder::query()->findOrFail($folder->parent_id)->name)->toBe('Project_A')
        ->and(Folder::query()->findOrFail($folder->parent_id)->parent_id)->toBe($this->strangersFolder->id);
});

test('a token that may upload but not create folders cannot upload a folder that needs new ones', function () {
    // The account could; the token was not given the right to.
    $token = $this->admin->createToken('t', [Permission::Upload->value])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/uploads', [
        'filename' => 'plan.pdf',
        'size' => 2048,
        'relative_path' => 'Project_A/plan.pdf',
    ])->assertForbidden();

    expect(Folder::query()->where('name', 'Project_A')->exists())->toBeFalse();
});

test('a client-scoped staff token cannot make folders inside a folder outside its library', function () {
    $token = $this->manager->createToken('t', [Permission::Upload->value])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/uploads', [
        'filename' => 'plan.pdf',
        'size' => 2048,
        'folder_id' => $this->strangersFolder->id,
        'relative_path' => 'Smuggled/plan.pdf',
    ])->assertForbidden();

    expect(Folder::query()->where('name', 'Smuggled')->exists())->toBeFalse();
});
