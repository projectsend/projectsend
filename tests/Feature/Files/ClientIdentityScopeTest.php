<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Access\ClientIdentityScope;
use App\Modules\Files\Models\File;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;

/**
 * A client-scoped staff member may hold a file whose uploader, or whose
 * other recipients, are clients off their roster — that is a legitimate
 * consequence of group and folder sharing, and the file boundary is right
 * to allow it. What is not legitimate is learning those clients' names and
 * ids from the file's metadata.
 *
 * Every one of these asserts on the rendered response body rather than on a
 * particular key, because the leak was never in one field: the same client
 * name arrived through the uploader, through the recipient list, and
 * through four different screens. A body that does not contain the name
 * anywhere is the only assertion that would have caught all of them.
 */
beforeEach(function () {
    Storage::fake('files');

    $this->admin = User::factory()->create();
    $this->onRoster = User::factory()->client()->create(['name' => 'Roster Client']);
    $this->offRoster = User::factory()->client()->create(['name' => 'Offroster Client']);

    $this->manager = User::factory()->role(SystemRole::ClientManager)->create();
    $this->manager->assignedClients()->sync([$this->onRoster->id]);

    $this->token = $this->manager->createToken('t', [Permission::Upload->value])->plainTextToken;
});

/** A file the manager may read, uploaded by a client they may not identify. */
function fileFromStranger(): File
{
    $file = File::factory()->create([
        'uploaded_by' => test()->offRoster->id,
        'name' => 'shared-onward',
    ]);
    shareFileWith($file, test()->onRoster);

    return $file;
}

/**
 * A client-scoped staff member holding wider permissions than the built-in
 * Client Manager — the roles that can open a colleague's file for editing
 * and browse a client's own library. Scoping and permissions are separate
 * axes, and the leak is a property of the scoping.
 *
 * @param  list<string>  $permissions
 */
function scopedStaffWith(array $permissions): User
{
    $role = Role::query()->create(['name' => 'Scoped '.uniqid(), 'client_scoped' => true]);

    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    $staff = User::factory()->create(['role_id' => $role->id]);
    $staff->assignedClients()->sync([test()->onRoster->id]);

    return $staff;
}

/** A file the manager may read that is also shared with a stranger client. */
function fileWithStrangerCoRecipient(): File
{
    $file = File::factory()->create(['uploaded_by' => test()->admin->id, 'name' => 'shared-both']);
    shareFileWith($file, test()->onRoster);
    shareFileWith($file, test()->offRoster);

    return $file;
}

test('the file boundary itself is unchanged: a stranger-only file is still refused', function () {
    $file = File::factory()->create(['uploaded_by' => $this->offRoster->id]);
    shareFileWith($file, $this->offRoster);

    $this->withToken($this->token)->getJson("/api/v1/files/{$file->id}")->assertForbidden();
});

test('the file boundary itself is unchanged on the web', function () {
    $file = File::factory()->create(['uploaded_by' => $this->offRoster->id]);
    shareFileWith($file, $this->offRoster);

    $this->actingAs($this->manager)->get("/files/{$file->id}/details")->assertForbidden();
});

test('the API does not name a stranger uploader of a file the caller may read', function () {
    $file = fileFromStranger();

    $show = $this->withToken($this->token)->getJson("/api/v1/files/{$file->id}")->assertOk();
    $index = $this->withToken($this->token)->getJson('/api/v1/files')->assertOk();

    expect($show->getContent())->not->toContain('Offroster Client')
        ->and($index->getContent())->not->toContain('Offroster Client')
        ->and($show->json('data.uploaded_by'))->toBeNull()
        // The file is still readable — this narrows the answer, it does
        // not withdraw it.
        ->and($show->json('data.id'))->toBe($file->id);
});

test('the API does not list a stranger co-recipient', function () {
    $file = fileWithStrangerCoRecipient();

    $show = $this->withToken($this->token)->getJson("/api/v1/files/{$file->id}")->assertOk();

    expect($show->getContent())->not->toContain('Offroster Client')
        ->and($show->json('data.assignments'))->toHaveCount(1)
        ->and($show->json('data.assignments.0.name'))->toBe('Roster Client');
});

test('a stranger co-recipient is not named in the reply to a write', function () {
    $file = fileWithStrangerCoRecipient();

    // The assignment endpoints re-load assignments.assignable and hand the
    // result straight back, which is a second serialisation path — and one
    // that a fix applied only to the read controllers would have missed.
    // The 200 is asserted on purpose: without it this passes on a 403,
    // whose body names nobody either.
    $writer = scopedStaffWith([Permission::Upload->value, Permission::EditFiles->value, Permission::EditOthersFiles->value]);
    $token = $writer->createToken('w', [Permission::EditFiles->value, Permission::EditOthersFiles->value])->plainTextToken;

    $response = $this->withToken($token)
        ->postJson("/api/v1/files/{$file->id}/assignments", ['type' => 'client', 'id' => $this->onRoster->id])
        ->assertOk();

    expect($response->getContent())->not->toContain('Offroster Client')
        ->and($response->json('data.assignments'))->toHaveCount(1);
});

test('uploaded_by cannot be used to probe for a client the caller may not identify', function () {
    fileFromStranger();

    $probe = $this->withToken($this->token)->getJson("/api/v1/files?uploaded_by={$this->offRoster->id}")->assertOk();

    expect($probe->json('data'))->toBeEmpty();
});

test('uploaded_by still filters by a client on the roster', function () {
    $own = File::factory()->create(['uploaded_by' => $this->onRoster->id, 'name' => 'theirs']);
    shareFileWith($own, $this->onRoster);
    fileFromStranger();

    $hit = $this->withToken($this->token)->getJson("/api/v1/files?uploaded_by={$this->onRoster->id}")->assertOk();

    expect($hit->json('data'))->toHaveCount(1)
        ->and($hit->json('data.0.id'))->toBe($own->id);
});

test('uploaded_by still filters by a staff member', function () {
    $file = fileWithStrangerCoRecipient();

    $hit = $this->withToken($this->token)->getJson("/api/v1/files?uploaded_by={$this->admin->id}")->assertOk();

    expect($hit->json('data'))->toHaveCount(1)
        ->and($hit->json('data.0.id'))->toBe($file->id);
});

test('the details panel names neither a stranger uploader nor a stranger recipient', function () {
    $stranger = fileFromStranger();
    $both = fileWithStrangerCoRecipient();

    $one = $this->actingAs($this->manager)->get("/files/{$stranger->id}/details")->assertOk();
    $two = $this->actingAs($this->manager)->get("/files/{$both->id}/details")->assertOk();

    expect($one->getContent())->not->toContain('Offroster Client')
        ->and($one->json('uploader'))->toBeNull()
        ->and($two->getContent())->not->toContain('Offroster Client')
        ->and($two->json('shares.clients'))->toHaveCount(1);
});

test('the library listing does not describe a stranger uploader', function () {
    fileFromStranger();

    $body = $this->actingAs($this->manager)->get('/files')->assertOk()->getContent();

    // Not the name, and not the "a client uploaded this" shape either.
    expect($body)->not->toContain('Offroster Client');
});

test('the edit page does not name a stranger uploader or recipient', function () {
    $file = fileWithStrangerCoRecipient();
    $file->update(['uploaded_by' => $this->offRoster->id]);

    $editor = scopedStaffWith([Permission::Upload->value, Permission::EditFiles->value, Permission::EditOthersFiles->value]);

    // route() rather than a built path: File binds by slug, so an id in
    // the URL is a 404 rather than the page under test.
    $body = $this->actingAs($editor)->get(route('files.edit', $file))->assertOk()->getContent();

    expect($body)->not->toContain('Offroster Client');
});

test('the per-client file listing does not name a stranger uploader', function () {
    fileFromStranger();

    $browser = scopedStaffWith([Permission::Upload->value, Permission::EditClients->value]);

    $body = $this->actingAs($browser)
        ->get("/clients/{$this->onRoster->id}/files")->assertOk()->getContent();

    expect($body)->not->toContain('Offroster Client');
});

test('a group holding none of the viewer clients is not named', function () {
    $group = Group::query()->create(['name' => 'Offroster Group']);
    $group->members()->sync([$this->offRoster->id]);

    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($file, $this->onRoster);
    shareFileWithGroup($file, $group);

    $api = $this->withToken($this->token)->getJson("/api/v1/files/{$file->id}")->assertOk();

    expect($api->getContent())->not->toContain('Offroster Group')
        ->and($api->json('data.assignments'))->toHaveCount(1);
});

test('a group holding none of the viewer clients is not named on the web', function () {
    $group = Group::query()->create(['name' => 'Offroster Group']);
    $group->members()->sync([$this->offRoster->id]);

    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($file, $this->onRoster);
    shareFileWithGroup($file, $group);

    $details = $this->actingAs($this->manager)->get("/files/{$file->id}/details")->assertOk();

    expect($details->getContent())->not->toContain('Offroster Group')
        ->and($details->json('shares.groups'))->toBeEmpty();
});

test('an unscoped administrator still sees every name', function () {
    $file = fileWithStrangerCoRecipient();
    $file->update(['uploaded_by' => $this->offRoster->id]);

    $token = $this->admin->createToken('a', [Permission::Upload->value])->plainTextToken;

    $api = $this->withToken($token)->getJson("/api/v1/files/{$file->id}")->assertOk();

    expect($api->json('data.uploaded_by.name'))->toBe('Offroster Client')
        ->and($api->json('data.assignments'))->toHaveCount(2);
});

test('an unscoped administrator still sees every name on the web', function () {
    $file = fileWithStrangerCoRecipient();
    $file->update(['uploaded_by' => $this->offRoster->id]);

    $details = $this->actingAs($this->admin)->get("/files/{$file->id}/details")->assertOk();

    expect($details->json('uploader'))->toBe('Offroster Client')
        ->and($details->json('shares.clients'))->toHaveCount(2);
});

test('a scoped viewer is still told about their own roster and their own uploads', function () {
    $mine = File::factory()->create(['uploaded_by' => $this->manager->id, 'name' => 'mine']);
    shareFileWith($mine, $this->onRoster);

    $api = $this->withToken($this->token)->getJson("/api/v1/files/{$mine->id}")->assertOk();

    expect($api->json('data.uploaded_by.name'))->toBe($this->manager->name)
        ->and($api->json('data.assignments.0.name'))->toBe('Roster Client');
});

test('the rule itself: staff are never hidden, strangers always are', function () {
    $identity = app(ClientIdentityScope::class);

    expect($identity->permits($this->manager, $this->admin))->toBeTrue()
        ->and($identity->permits($this->manager, $this->onRoster))->toBeTrue()
        ->and($identity->permits($this->manager, $this->offRoster))->toBeFalse()
        // A client may always be told who they themselves are.
        ->and($identity->permits($this->offRoster, $this->offRoster))->toBeTrue()
        // An unscoped viewer is narrowed by nothing.
        ->and($identity->permits($this->admin, $this->offRoster))->toBeTrue()
        ->and($identity->isNarrowed($this->admin))->toBeFalse()
        ->and($identity->isNarrowed($this->manager))->toBeTrue()
        // No viewer at all is the closed case, not the open one.
        ->and($identity->permits(null, $this->offRoster))->toBeFalse()
        ->and($identity->permits(null, $this->admin))->toBeTrue();
});
