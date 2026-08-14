<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Permissions\Permission;

/*
|--------------------------------------------------------------------------
| manage_clients / manage_groups are section access, not management
|--------------------------------------------------------------------------
|
| Despite the key names, each gates exactly one thing: the list page (and
| its sidebar link). Creating, editing and deleting have keys of their own,
| and holding a "manage" key grants none of them.
|
| The names are v1's, preserved verbatim so the importer needs no mapping
| table — see Permission's docblock. Their *labels* now say what they do;
| these assert the semantics behind the labels, because "manage" reading as
| a superset of create/edit/delete is exactly the wrong guess to make when
| assigning permissions to a role.
|
| Which roles hold these keys by default is a separate question, covered by
| ClientsManagementTest and GroupsManagementTest.
|
*/

beforeEach(function () {
    User::factory()->create();
});

test('a list key opens the list page and grants nothing else', function () {
    $lister = staffWithPermissions([Permission::ManageClients->value, Permission::ManageGroups->value]);
    $client = User::factory()->client()->create();

    $this->actingAs($lister)->get('/clients')->assertOk();
    $this->actingAs($lister)->get('/groups')->assertOk();

    $this->actingAs($lister)->patch("/clients/{$client->id}", ['name' => 'Renamed'])->assertForbidden();
    $this->actingAs($lister)->delete("/clients/{$client->id}")->assertForbidden();
});

test('write permissions alone do not open the list pages', function () {
    // The inverse, and the one that surprises people: a role can be able to
    // change every client it can name while having no way to browse them.
    $writer = staffWithPermissions([
        Permission::CreateClients->value,
        Permission::EditClients->value,
        Permission::DeleteClients->value,
        Permission::CreateGroups->value,
        Permission::EditGroups->value,
        Permission::DeleteGroups->value,
    ]);

    $this->actingAs($writer)->get('/clients')->assertForbidden();
    $this->actingAs($writer)->get('/groups')->assertForbidden();

    // But the create screens, reached directly, do work.
    $this->actingAs($writer)->get('/clients/create')->assertOk();
});

test('the labels describe what the keys actually gate', function () {
    expect(Permission::ManageClients->label())->toBe('View the client list')
        ->and(Permission::ManageGroups->label())->toBe('View the group list');
});
