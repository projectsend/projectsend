<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Files\Models\File;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function clientFilesProps(TestResponse $response): array
{
    $page = json_decode(json_encode($response->viewData('page')), true);

    return $page['props'];
}

test('the page lists everything the client can reach: own uploads, direct, group, and folder assignments', function () {
    $client = User::factory()->client()->create();

    // Uploaded by the client themself (bypassing the real upload endpoint,
    // same fixture convention FilesTest.php uses).
    $mine = File::factory()->create([
        'name' => 'mine', 'original_name' => 'mine.pdf', 'path' => 'test/mine.pdf',
        'mime_type' => 'application/pdf', 'size' => 10, 'checksum' => str_repeat('0', 64),
        'uploaded_by' => $client->id, 'public' => false,
    ]);

    $direct = uploadNamedFile($this->admin, 'direct');
    test()->actingAs($this->admin)->post("/files/{$direct->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $group = Group::query()->create(['name' => 'Recipients', 'public' => false]);
    $group->members()->attach($client->id);
    $viaGroup = uploadNamedFile($this->admin, 'via-group');
    test()->actingAs($this->admin)->post("/files/{$viaGroup->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    $folder = app(FolderService::class)->create('Shared Folder', null);
    test()->actingAs($this->admin)->post("/folders/{$folder->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $viaFolder = uploadNamedFile($this->admin, 'via-folder', $folder->id);

    $unrelated = uploadNamedFile($this->admin, 'unrelated');

    $response = test()->actingAs($this->admin)->get("/clients/{$client->id}/files");

    $response->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('clients/files')
            ->where('client.id', $client->id)
            ->has('files', 4)
    );

    $files = collect(clientFilesProps($response)['files']);
    $ids = $files->pluck('id');
    expect($ids)->toContain($mine->id, $direct->id, $viaGroup->id, $viaFolder->id)
        ->not->toContain($unrelated->id);

    expect($files->firstWhere('id', $mine->id)['uploaded_by_client'])->toBeTrue();
    expect($files->firstWhere('id', $direct->id)['uploaded_by_client'])->toBeFalse();
});

test('staff without edit_clients cannot reach the page', function () {
    $client = User::factory()->client()->create();
    $uploader = User::factory()->role(SystemRole::Uploader)->create();

    test()->actingAs($uploader)->get("/clients/{$client->id}/files")->assertForbidden();
});

test('a non-client id 404s', function () {
    $staffer = User::factory()->role(SystemRole::Uploader)->create();

    test()->actingAs($this->admin)->get("/clients/{$staffer->id}/files")->assertNotFound();
});

test('a client-scoped staff member may only view files for their assigned clients', function () {
    // None of the seeded system roles combine client_scoped with
    // edit_clients (Client Manager is file-focused, not client-management),
    // so a bespoke role stands in for "a scoped staffer who may reach this
    // page at all".
    $role = Role::query()->create(['name' => 'Scoped Client Editor', 'is_administrator' => false, 'is_system' => false, 'client_scoped' => true]);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => 'edit_clients']);
    $manager = User::factory()->create(['role_id' => $role->id]);

    $assigned = User::factory()->client()->create();
    $unassigned = User::factory()->client()->create();
    $manager->assignedClients()->sync([$assigned->id]);

    test()->actingAs($manager)->get("/clients/{$assigned->id}/files")->assertOk();
    test()->actingAs($manager)->get("/clients/{$unassigned->id}/files")->assertNotFound();
});

test('download is only offered when the viewer has file permissions', function () {
    $client = User::factory()->client()->create();
    $direct = uploadNamedFile($this->admin, 'direct');
    test()->actingAs($this->admin)->post("/files/{$direct->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    // A role that can manage clients but holds no file permissions at all.
    $role = Role::query()->create(['name' => 'Files-Blind Manager', 'is_administrator' => false, 'is_system' => false, 'client_scoped' => false]);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => 'edit_clients']);
    $blindManager = User::factory()->create(['role_id' => $role->id]);

    $response = test()->actingAs($blindManager)->get("/clients/{$client->id}/files");
    $response->assertOk();

    $row = collect(clientFilesProps($response)['files'])->firstWhere('id', $direct->id);
    expect($row['can_download'])->toBeFalse();

    $adminResponse = test()->actingAs($this->admin)->get("/clients/{$client->id}/files");
    $adminRow = collect(clientFilesProps($adminResponse)['files'])->firstWhere('id', $direct->id);
    expect($adminRow['can_download'])->toBeTrue();
});
