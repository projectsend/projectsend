<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\UserType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * Issue #1840: dropping a folder with subfolders into the uploader keeps
 * the subfolders, instead of flattening every file into one place.
 *
 * Only the start of an upload is exercised: that is where the folder is
 * decided and stored on the session, and complete() files the bytes there.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

function startFolderUpload(string $relativePath, ?int $folderId = null, string $filename = 'plan.pdf'): TestResponse
{
    return test()->postJson('/uploads', array_filter([
        'filename' => $filename,
        'size' => 1024,
        'type' => 'application/pdf',
        'folder_id' => $folderId,
        'relative_path' => $relativePath,
    ], fn ($value) => $value !== null));
}

function uploadSessionFolder(TestResponse $response): ?Folder
{
    $folderId = UploadSession::query()->findOrFail($response->json('uploadId'))->folder_id;

    return $folderId === null ? null : Folder::query()->find($folderId);
}

/**
 * @return list<string>
 */
function folderTrail(?Folder $folder): array
{
    $names = [];
    while ($folder !== null) {
        array_unshift($names, $folder->name);
        $folder = $folder->parent_id === null ? null : Folder::query()->find($folder->parent_id);
    }

    return $names;
}

test('a dropped folder\'s subfolders are recreated, inside the folder the upload started in', function () {
    $this->actingAs($this->admin);
    $base = app(FolderService::class)->create('Clients', null);

    $response = startFolderUpload('/Project_A/Drawings/Mechanical/plan.pdf', $base->id)->assertOk();

    expect(folderTrail(uploadSessionFolder($response)))->toBe(['Clients', 'Project_A', 'Drawings', 'Mechanical'])
        ->and(ActivityLog::query()->where('action', Action::FolderCreated->value)->count())->toBe(3); // the three it made; Clients was set up directly
});

test('the next file from the same folder goes into the folders the first one made', function () {
    $this->actingAs($this->admin);

    $first = startFolderUpload('Project_A/Drawings/Mechanical/one.pdf')->assertOk();
    $second = startFolderUpload('Project_A/Drawings/Electrical/two.pdf')->assertOk();
    $third = startFolderUpload('Project_A/Reports/three.pdf')->assertOk();

    expect(Folder::query()->where('name', 'Project_A')->count())->toBe(1)
        ->and(Folder::query()->where('name', 'Drawings')->count())->toBe(1)
        ->and(folderTrail(uploadSessionFolder($first)))->toBe(['Project_A', 'Drawings', 'Mechanical'])
        ->and(folderTrail(uploadSessionFolder($second)))->toBe(['Project_A', 'Drawings', 'Electrical'])
        ->and(folderTrail(uploadSessionFolder($third)))->toBe(['Project_A', 'Reports']);
});

test('a file picked on its own goes where it always did', function () {
    $this->actingAs($this->admin);
    $base = app(FolderService::class)->create('Clients', null);

    expect(uploadSessionFolder(startFolderUpload('plan.pdf', $base->id)->assertOk())?->id)->toBe($base->id)
        ->and(uploadSessionFolder(startFolderUpload('/plan.pdf')->assertOk()))->toBeNull()
        ->and(Folder::query()->count())->toBe(1);
});

test('a path that climbs out, or nests too deep, is refused and creates nothing', function (string $path) {
    $this->actingAs($this->admin);

    startFolderUpload($path)->assertStatus(422)->assertJsonValidationErrors('relative_path');

    expect(Folder::query()->count())->toBe(0);
})->with([
    'parent reference' => ['../../etc/plan.pdf'],
    'control character' => ["Bad\x01Name/plan.pdf"],
    'too deep' => [implode('/', array_fill(0, 12, 'Level')).'/plan.pdf'],
]);

test('a new version of a file ignores the folder path and stays beside the file it replaces', function () {
    $this->actingAs($this->admin);
    $original = App\Modules\Files\Models\File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->postJson('/uploads', [
        'filename' => 'plan.pdf',
        'size' => 1024,
        'relative_path' => 'Project_A/plan.pdf',
        'previous_file_id' => $original->id,
    ])->assertOk();

    expect(Folder::query()->count())->toBe(0);
});

test('a client\'s folder upload makes their own folders', function () {
    $client = User::factory()->client()->create();
    $this->actingAs($client);

    $response = startFolderUpload('Scans/2026/receipt.pdf')->assertOk();

    $folder = uploadSessionFolder($response);
    expect(folderTrail($folder))->toBe(['Scans', '2026'])
        ->and($folder->created_by)->toBe($client->id);
});

test('a client never reuses a folder they cannot see, even one with the same name', function () {
    $hidden = app(FolderService::class)->create('Scans', null);
    $client = User::factory()->client()->create();
    $this->actingAs($client);

    $folder = uploadSessionFolder(startFolderUpload('Scans/receipt.pdf')->assertOk());

    expect($folder->id)->not->toBe($hidden->id)
        ->and($folder->created_by)->toBe($client->id);
});

test('a client cannot make folders inside one that other clients see', function () {
    $client = User::factory()->client()->create();
    $group = Group::query()->create(['name' => 'Everyone']);
    $group->members()->attach($client->id);
    $shared = app(FolderService::class)->create('Shared', null);
    App\Modules\Files\Models\FolderAssignment::query()->create([
        'folder_id' => $shared->id,
        'assignable_type' => (new Group)->getMorphClass(),
        'assignable_id' => $group->id,
    ]);

    $this->actingAs($client);
    startFolderUpload('Mine/receipt.pdf', $shared->id)->assertForbidden();

    expect(Folder::query()->where('name', 'Mine')->exists())->toBeFalse();
});

test('someone who may not create folders cannot upload a folder that needs new ones', function () {
    $role = Role::query()->create(['name' => 'Upload only', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => 'upload']);
    $client = User::factory()->create(['type' => UserType::Client, 'role_id' => $role->id]);
    $this->actingAs($client);

    startFolderUpload('Scans/receipt.pdf')->assertForbidden();
    // A plain file is still fine.
    startFolderUpload('receipt.pdf')->assertOk();

    expect(Folder::query()->count())->toBe(0);
});
