<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/**
 * A client publishing a file of their own.
 *
 * The switch that marks a file public promised "anyone with the link can
 * open it" and then showed no link: making one was a staff-only route, so
 * a client could publish a file and never get the URL. Reported by
 * Ricardo Cazati, whose colleagues are client accounts.
 *
 * The key is `upload_public` — the same one that lets them mark the file
 * public in the first place — and the file still has to be one they may
 * edit, which for a client means one they uploaded.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::Theme, 'default');
});

/** A client whose role holds exactly these permissions. */
function clientWith(Permission ...$permissions): User
{
    $role = Role::query()->create(['name' => 'Clients '.Str::random(6)]);

    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    return User::factory()->client()->create(['role_id' => $role->id]);
}

function fileOf(User $client): File
{
    return File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Theirs']);
}

test('a client who may publish can make a public link for their own file', function () {
    $client = clientWith(Permission::EditFiles, Permission::UploadPublic);
    $file = fileOf($client);

    $this->actingAs($client)->post("/files/{$file->id}/share-links")->assertSessionHasNoErrors();

    $link = ShareLink::query()->sole();

    expect($link->shareable_id)->toBe($file->id)
        ->and($link->created_by)->toBe($client->id);

    // And it works for somebody who is not signed in at all, which is the
    // whole point of it.
    $this->get("/s/{$link->token}")->assertOk();
});

test('the link is shown on the file, so it can actually be copied', function () {
    $client = clientWith(Permission::EditFiles, Permission::UploadPublic);
    $file = fileOf($client);

    $this->actingAs($client)->post("/files/{$file->id}/share-links");

    $this->actingAs($client)->get("/my-files/{$file->id}/edit")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('portal/edit-file')
            ->has('share_links', 1)
            ->where('share_links.0.url', route('share.show', ShareLink::query()->value('token'))),
    );
});

test('a client who may not publish is refused', function () {
    // They can edit the file; publishing is the key they do not hold.
    $client = clientWith(Permission::EditFiles);
    $file = fileOf($client);

    $this->actingAs($client)->post("/files/{$file->id}/share-links")->assertForbidden();

    expect(ShareLink::query()->count())->toBe(0);
});

test('a client cannot publish somebody else\'s file', function () {
    $client = clientWith(Permission::EditFiles, Permission::UploadPublic);
    $theirs = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($client)->post("/files/{$theirs->id}/share-links")->assertForbidden();

    expect(ShareLink::query()->count())->toBe(0);
});

test('a client can revoke a link on their own file, even after losing the right to publish', function () {
    // Revoking takes access away. Somebody whose permission to publish was
    // withdrawn must still be able to undo what they published.
    $publisher = clientWith(Permission::EditFiles, Permission::UploadPublic);
    $file = fileOf($publisher);
    $this->actingAs($publisher)->post("/files/{$file->id}/share-links");

    $link = ShareLink::query()->sole();
    $publisher->role->permissions()->where('permission', Permission::UploadPublic->value)->delete();

    $this->actingAs($publisher->refresh())->delete("/share-links/{$link->id}")->assertSessionHasNoErrors();

    expect(ShareLink::query()->count())->toBe(0);
});

test('a client cannot revoke a link on a file that is not theirs', function () {
    $client = clientWith(Permission::EditFiles, Permission::UploadPublic);
    $theirs = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $link = ShareLink::query()->create([
        'shareable_type' => (new File)->getMorphClass(),
        'shareable_id' => $theirs->id,
        'token' => Str::random(32),
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($client)->delete("/share-links/{$link->id}")->assertForbidden();

    expect(ShareLink::query()->count())->toBe(1);
});

test('staff are not asked for the publishing key they never needed', function () {
    // Every installation that upgrades has staff roles without it; asking
    // would be a new refusal on a thing they could already do.
    $staff = User::factory()->role(SystemRole::Uploader)->create();
    $file = File::factory()->create(['uploaded_by' => $staff->id]);

    expect($staff->can('upload_public'))->toBeTrue();

    $staff->role->permissions()->where('permission', Permission::UploadPublic->value)->delete();

    $this->actingAs($staff->refresh())->post("/files/{$file->id}/share-links")->assertSessionHasNoErrors();

    expect(ShareLink::query()->count())->toBe(1);
});
