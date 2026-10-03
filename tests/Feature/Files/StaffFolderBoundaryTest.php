<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Permissions\SystemRole;
use Inertia\Testing\AssertableInertia;

/**
 * Two edges of the staff folder screens that the folder API made visible,
 * because the API had to answer the same questions and answered them more
 * strictly.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
});

/*
 * A client-scoped staff member can hold a client's folder that sits inside
 * somebody else's tree. The trail above it named every folder on the way,
 * including the ones their library does not show them. The client portal
 * already trims the same trail (BreadcrumbBuilder::visible).
 */
test('a client-scoped staff member is not told the names of folders above their reach', function () {
    $client = User::factory()->client()->create();
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([$client->id]);

    $secret = makeFolder('Board minutes');
    $acme = makeFolder('Acme', $secret);
    $year = makeFolder('2026', $acme);
    $this->actingAs($this->admin)->post("/folders/{$acme->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $this->actingAs($manager)->get("/files?folder={$year->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('breadcrumb', [
            ['id' => $acme->id, 'name' => 'Acme'],
            ['id' => $year->id, 'name' => '2026'],
        ]));

    $this->actingAs($manager)->get("/folders/{$acme->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('breadcrumb', [
            ['id' => $acme->id, 'name' => 'Acme'],
        ]));
});

test('an unscoped staff member still sees the whole trail', function () {
    $secret = makeFolder('Board minutes');
    $acme = makeFolder('Acme', $secret);

    $this->actingAs($this->admin)->get("/files?folder={$acme->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('breadcrumb', [
            ['id' => $secret->id, 'name' => 'Board minutes'],
            ['id' => $acme->id, 'name' => 'Acme'],
        ]));
});

/*
 * A folder inside a public folder is public, so creating one there is
 * placing something into a public folder — the question
 * Folder::uploadableBy answers for every other write of a parent_id.
 * Creation was the one write that did not ask it.
 */
test('a folder cannot be created inside a public folder without permission to publish', function () {
    $public = makeFolder('Press kit');
    $public->update(['public' => true]);

    $staff = staffWithPermissions(['create_own_folders', 'upload', 'edit_others_files']);

    $this->actingAs($staff)->post('/folders', ['name' => 'Drafts', 'parent_id' => $public->id])->assertForbidden();

    expect(Folder::query()->where('name', 'Drafts')->exists())->toBeFalse();
});

test('with upload_public, creating inside a public folder still works', function () {
    $public = makeFolder('Press kit');
    $public->update(['public' => true]);

    $staff = staffWithPermissions(['create_own_folders', 'upload', 'edit_others_files', 'upload_public']);

    $this->actingAs($staff)->post('/folders', ['name' => 'Drafts', 'parent_id' => $public->id])->assertRedirect();

    expect(Folder::query()->where('name', 'Drafts')->value('parent_id'))->toBe($public->id);
});

test('creating inside a private folder needs nothing extra', function () {
    $private = makeFolder('Internal');
    $staff = staffWithPermissions(['create_own_folders', 'upload', 'edit_others_files']);

    $this->actingAs($staff)->post('/folders', ['name' => 'Drafts', 'parent_id' => $private->id])->assertRedirect();

    expect(Folder::query()->where('name', 'Drafts')->value('parent_id'))->toBe($private->id);
});
