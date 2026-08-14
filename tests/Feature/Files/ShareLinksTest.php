<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function shareTestFile(User $uploader): File
{
    return File::factory()->create([
        'uploaded_by' => $uploader->id,
        'name' => 'Report',
        'original_name' => 'report.pdf',
        'path' => '2026/07/'.Str::uuid()->toString().'.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);
}

/** A staff user whose role has exactly the given permission keys. */
function shareStaffWith(array $permissions): User
{
    $role = Role::query()->create(['name' => 'Role '.Str::random(6)]);
    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

test('creating a share link requires update permission on the file', function () {
    $file = shareTestFile($this->admin);

    $noAccess = shareStaffWith(['upload']);
    $this->actingAs($noAccess)->post("/files/{$file->id}/share-links")->assertForbidden();

    $this->actingAs($this->admin)->post("/files/{$file->id}/share-links")->assertRedirect();
    $link = ShareLink::query()->sole();
    expect($link->shareable_id)->toBe($file->id)
        ->and(strlen($link->token))->toBe(32)
        ->and(ActivityLog::query()->where('action', Action::ShareLinkCreated)->exists())->toBeTrue();
});

test('a custom token is used verbatim instead of a random one', function () {
    $file = shareTestFile($this->admin);

    $this->actingAs($this->admin)->post("/files/{$file->id}/share-links", ['token' => 'my-custom-link'])->assertRedirect();

    $link = ShareLink::query()->sole();
    expect($link->token)->toBe('my-custom-link');
});

test('a custom token must be unique', function () {
    $file = shareTestFile($this->admin);
    ShareLink::query()->create(['shareable_type' => $file->getMorphClass(), 'shareable_id' => $file->id, 'token' => 'taken-token']);

    $this->actingAs($this->admin)->post("/files/{$file->id}/share-links", ['token' => 'taken-token'])
        ->assertSessionHasErrors('token');
});

test('a custom token cannot match the file\'s own public slug', function () {
    $file = shareTestFile($this->admin);
    $file->update(['slug' => 'report-slug']);

    $this->actingAs($this->admin)->post("/files/{$file->id}/share-links", ['token' => 'report-slug'])
        ->assertSessionHasErrors('token');

    expect(ShareLink::query()->count())->toBe(0);
});

test('a custom token must match the expected format', function () {
    $file = shareTestFile($this->admin);

    $this->actingAs($this->admin)->post("/files/{$file->id}/share-links", ['token' => 'has spaces!'])
        ->assertSessionHasErrors('token');

    $this->actingAs($this->admin)->post("/files/{$file->id}/share-links", ['token' => 'ab'])
        ->assertSessionHasErrors('token');
});

test('expiry and download-limit fields are dropped without their permission, honored with it', function () {
    $file = shareTestFile($this->admin);
    $futureDate = now()->addDays(3)->toDateString();

    // edit_others_files alone (the file belongs to $this->admin): neither
    // field is honored, even if submitted.
    $editorOnly = shareStaffWith(['upload', 'edit_others_files']);
    $this->actingAs($editorOnly)->post("/files/{$file->id}/share-links", [
        'expires_at' => $futureDate,
        'max_downloads' => 5,
    ])->assertRedirect();
    $plain = ShareLink::query()->sole();
    expect($plain->expires_at)->toBeNull()->and($plain->max_downloads)->toBeNull();
    $plain->delete();

    // With both permissions, both fields are honored.
    $privileged = shareStaffWith(['upload', 'edit_others_files', 'set_file_expiration_date', 'limit_downloads']);
    $this->actingAs($privileged)->post("/files/{$file->id}/share-links", [
        'expires_at' => $futureDate,
        'max_downloads' => 5,
    ])->assertRedirect();
    $full = ShareLink::query()->sole();
    expect($full->expires_at?->toDateString())->toBe($futureDate)
        ->and($full->max_downloads)->toBe(5);
});

test('the read-only details panel lists share links but no way to create or revoke one', function () {
    $file = shareTestFile($this->admin);
    ShareLink::query()->create(['shareable_type' => $file->getMorphClass(), 'shareable_id' => $file->id, 'token' => Str::random(32)]);

    $this->actingAs($this->admin)->getJson("/files/{$file->id}/details")->assertOk()->assertJson(fn ($json) => $json
        ->has('share_links', 1)
        ->missing('share_link_store_url')
        ->missing('can_set_expiration')
        ->missing('can_limit_downloads')
        ->missing('assign_url')
        ->missing('unassign_url')
        ->etc());
});

test('the edit page carries the share-link create form and capability flags', function () {
    $file = shareTestFile($this->admin);
    ShareLink::query()->create(['shareable_type' => $file->getMorphClass(), 'shareable_id' => $file->id, 'token' => Str::random(32)]);

    $this->actingAs($this->admin)->get("/files/{$file->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('files/edit')
        ->has('share_links', 1)
        ->where('can_set_expiration', true)
        ->where('can_limit_downloads', true));
});

test('the public show and download routes work with no authenticated user at all', function () {
    $file = shareTestFile($this->admin);
    $link = ShareLink::query()->create([
        'shareable_type' => $file->getMorphClass(),
        'shareable_id' => $file->id,
        'token' => Str::random(32),
    ]);

    // Critical: a guest must never be redirected to login.
    $this->get("/s/{$link->token}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('share/show')
        ->where('status', 'active')
        ->where('file.original_name', 'report.pdf'));

    $response = $this->get("/s/{$link->token}/download");
    $response->assertOk()->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path);

    $entry = ActivityLog::query()->where('action', Action::ShareLinkDownloaded)->sole();
    expect($link->refresh()->downloads_count)->toBe(1)
        ->and($entry->actor_id)->toBeNull()
        ->and($entry->actor_name)->toBeNull();
});

test('an unknown token shows a not-found state instead of a 404', function () {
    $this->get('/s/does-not-exist')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('status', 'not_found'));
    $this->get('/s/does-not-exist/download')->assertRedirect(route('share.show', 'does-not-exist'));
});

test('an expired link refuses the download and shows an expired state', function () {
    $file = shareTestFile($this->admin);
    $link = ShareLink::query()->create([
        'shareable_type' => $file->getMorphClass(),
        'shareable_id' => $file->id,
        'token' => Str::random(32),
        'expires_at' => now()->subDay(),
    ]);

    $this->get("/s/{$link->token}")->assertInertia(fn (AssertableInertia $page) => $page->where('status', 'expired'));
    $this->get("/s/{$link->token}/download")->assertRedirect();
    expect($link->refresh()->downloads_count)->toBe(0);
});

test('a link stops working the instant it hits its download limit', function () {
    $file = shareTestFile($this->admin);
    $link = ShareLink::query()->create([
        'shareable_type' => $file->getMorphClass(),
        'shareable_id' => $file->id,
        'token' => Str::random(32),
        'max_downloads' => 2,
    ]);

    $this->get("/s/{$link->token}/download")->assertOk();
    $this->get("/s/{$link->token}/download")->assertOk();
    expect($link->refresh()->downloads_count)->toBe(2);

    $this->get("/s/{$link->token}")->assertInertia(fn (AssertableInertia $page) => $page->where('status', 'limit_reached'));
    $this->get("/s/{$link->token}/download")->assertRedirect();
    expect($link->refresh()->downloads_count)->toBe(2);
});

test('revoking a share link deletes it and the token stops working', function () {
    $file = shareTestFile($this->admin);
    $link = ShareLink::query()->create([
        'shareable_type' => $file->getMorphClass(),
        'shareable_id' => $file->id,
        'token' => Str::random(32),
    ]);

    $noAccess = shareStaffWith(['upload']);
    $this->actingAs($noAccess)->delete("/share-links/{$link->id}")->assertForbidden();

    $this->actingAs($this->admin)->delete("/share-links/{$link->id}")->assertRedirect();

    expect(ShareLink::query()->count())->toBe(0)
        ->and(ActivityLog::query()->where('action', Action::ShareLinkRevoked)->exists())->toBeTrue();

    $this->get("/s/{$link->token}")->assertInertia(fn (AssertableInertia $page) => $page->where('status', 'not_found'));
});

// expires_at is how a file's access gets revoked everywhere else (clients,
// public listing), so a link that outlived it would be a way around that.
test('a share link stops working once the file itself expires, even if the link has not', function () {
    $file = shareTestFile($this->admin);
    $link = ShareLink::query()->create([
        'shareable_type' => $file->getMorphClass(),
        'shareable_id' => $file->id,
        'token' => Str::random(32),
        'expires_at' => now()->addYear(),
    ]);

    $this->get("/s/{$link->token}")->assertInertia(fn (AssertableInertia $page) => $page->where('status', 'active'));

    $file->update(['expires_at' => now()->subDay()]);

    $this->get("/s/{$link->token}")->assertInertia(fn (AssertableInertia $page) => $page->where('status', 'expired'));
    $this->get("/s/{$link->token}/download")->assertRedirect(route('share.show', $link->token));

    // A refused download must not burn a slot off the limit either.
    expect($link->refresh()->downloads_count)->toBe(0);
});

test('the share page shows the file\'s categories, the same as every other surface a visitor can reach', function () {
    $category = Category::query()->create(['name' => 'Tenders', 'color' => 'blue']);
    $file = shareTestFile($this->admin);
    $file->categories()->attach($category->id);

    $link = ShareLink::query()->create([
        'shareable_type' => $file->getMorphClass(),
        'shareable_id' => $file->id,
        'token' => Str::random(32),
    ]);

    $this->get("/s/{$link->token}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('file.categories', [['id' => $category->id, 'name' => 'Tenders', 'color' => 'blue']]));
});
