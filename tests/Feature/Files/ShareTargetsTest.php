<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

// Files and folders are two separate edit screens that must offer the same
// sharing choices. They used to build these four lists from two copies of the
// same code, so each case below runs against both subjects.

dataset('subjects', [
    'file' => [fn () => File::factory()->create(['name' => 'Report']), 'files.edit'],
    'folder' => [fn () => Folder::query()->create(['name' => 'Reports', 'path' => '/']), 'folders.share'],
]);

test('both edit screens expose the same four sharing lists', function (Closure $make, string $route) {
    $subject = $make();

    $this->actingAs($this->admin)->get(route($route, $subject))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('assigned_clients')
            ->has('assigned_groups')
            ->has('available_clients')
            ->has('available_groups'),
    );
})->with('subjects');

test('a client already shared with moves out of available and into assigned', function (Closure $make, string $route) {
    $subject = $make();
    $client = User::factory()->client()->create(['name' => 'Acme Ltd']);

    $this->actingAs($this->admin)->get(route($route, $subject))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('assigned_clients', [])
            ->where('available_clients.0.name', 'Acme Ltd'),
    );

    $type = $subject instanceof File ? 'files' : 'folders';
    $this->actingAs($this->admin)->post("/{$type}/{$subject->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $this->actingAs($this->admin)->get(route($route, $subject))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('assigned_clients.0.name', 'Acme Ltd')
            ->where('available_clients', []),
    );
})->with('subjects');

test('a group already shared with moves out of available and into assigned', function (Closure $make, string $route) {
    $subject = $make();
    $group = Group::query()->create(['name' => 'Design Team']);

    $type = $subject instanceof File ? 'files' : 'folders';
    $this->actingAs($this->admin)->post("/{$type}/{$subject->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    $this->actingAs($this->admin)->get(route($route, $subject))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('assigned_groups.0.name', 'Design Team')
            ->where('available_groups', []),
    );
})->with('subjects');

// The reason this belongs next to StaffLibraryScope rather than in a
// controller: what a scoped viewer may share with is an access question, and
// getting it wrong on one of the two screens would leak the client roster.
//
// The subject has to be one the manager owns — a scoped viewer cannot open
// somebody else's file at all, so anything created by the admin would 403
// before it ever reached the sharing lists.
test('a client-scoped viewer is only offered their own clients', function (string $subjectType) {
    $mine = User::factory()->client()->create(['name' => 'My Client']);
    User::factory()->client()->create(['name' => 'Someone Elses Client']);

    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([$mine->id]);

    [$subject, $route] = $subjectType === 'file'
        ? [File::factory()->create(['name' => 'Report', 'uploaded_by' => $manager->id]), 'files.edit']
        : [Folder::query()->create(['name' => 'Reports', 'path' => '/', 'created_by' => $manager->id]), 'folders.share'];

    $this->actingAs($manager)->get(route($route, $subject))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('available_clients.0.name', 'My Client')
            ->count('available_clients', 1),
    );
})->with(['file', 'folder']);

test('an unscoped viewer is offered the whole roster', function (Closure $make, string $route) {
    $subject = $make();
    User::factory()->client()->create(['name' => 'Client A']);
    User::factory()->client()->create(['name' => 'Client B']);

    $this->actingAs($this->admin)->get(route($route, $subject))->assertInertia(
        fn (AssertableInertia $page) => $page->count('available_clients', 2),
    );
})->with('subjects');

// The details panel reads the same assignments through the same service, so
// it must agree with the edit screen about who a subject is shared with.
test('the details panel reports the same shares as the edit screen', function () {
    $file = File::factory()->create(['name' => 'Report']);
    $client = User::factory()->client()->create(['name' => 'Acme Ltd']);
    $group = Group::query()->create(['name' => 'Design Team']);

    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    $response = $this->actingAs($this->admin)->getJson("/files/{$file->id}/details");

    $response->assertOk()
        ->assertJsonPath('shares.clients.0.name', 'Acme Ltd')
        ->assertJsonPath('shares.groups.0.name', 'Design Team');
});
