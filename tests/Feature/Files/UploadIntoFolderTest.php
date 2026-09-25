<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Permissions\SystemRole;

/*
 * Upload from inside a folder on the staff Files page opened the upload
 * page with no folder, so everything landed at the top of the library
 * (#1801). The portal already carried the folder; this is the staff twin.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('the upload page opened from a folder uploads into that folder', function () {
    $folder = Folder::query()->create(['name' => 'Wedding']);

    $this->actingAs($this->admin)->get(route('files.create', ['folder' => $folder->id]))
        ->assertInertia(fn ($page) => $page
            ->where('folder.id', $folder->id)
            ->where('folder.name', 'Wedding'));
});

test('without a folder it still uploads to the top of the library', function () {
    $this->actingAs($this->admin)->get(route('files.create'))
        ->assertInertia(fn ($page) => $page->where('folder', null));
});

test('a folder that does not exist is a 404, not a quiet upload to the top', function () {
    $this->actingAs($this->admin)->get(route('files.create', ['folder' => 999999]))->assertNotFound();
});

test('a folder outside a client-scoped staff member\'s library is a 404', function () {
    // Nobody is assigned to this manager, so the folder is outside what
    // they can see. The page must not confirm it exists by naming it.
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $folder = Folder::query()->create(['name' => 'Somebody else\'s']);

    $this->actingAs($manager)->get(route('files.create', ['folder' => $folder->id]))->assertNotFound();
});
