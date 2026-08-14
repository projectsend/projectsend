<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->versions = app(FileVersions::class);
    app(Settings::class)->set(Setting::Theme, 'default');
});

test('the file editor reports a plain file as having no version links', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->get(route('files.edit', $file))->assertInertia(
        fn (AssertableInertia $page) => $page->component('files/edit')
            ->where('file.is_revision', false)
            ->where('file.version.previous', null)
            ->where('file.version.next', null)
            ->where('sharing_root', null)
            ->where('can_set_version', true),
    );
});

test('the file editor names both ends of a version link', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev D']);

    $this->versions->link($revision, $original, $this->admin);

    $this->actingAs($this->admin)->get(route('files.edit', $revision))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('file.is_revision', true)
            ->where('file.version.previous.name', 'Rev C')
            ->where('file.version.next', null)
            ->where('sharing_root.name', 'Rev C')
            ->where('can_update_root', true),
    );

    $this->actingAs($this->admin)->get(route('files.edit', $original))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('file.is_revision', false)
            ->where('file.version.next.name', 'Rev D')
            ->where('sharing_root', null),
    );
});

test('the editor sends the whole lineage in order', function () {
    $v1 = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'A']);
    $v2 = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'B']);
    $v3 = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'C']);

    $this->versions->link($v2, $v1, $this->admin);
    $this->versions->link($v3, $v2, $this->admin);

    $this->actingAs($this->admin)->get(route('files.edit', $v2))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('version_chain', 3)
            ->where('version_chain.0.name', 'A')
            ->where('version_chain.1.name', 'B')
            ->where('version_chain.1.is_current', true)
            ->where('version_chain.2.name', 'C'),
    );
});

test('a revision\'s sharing panel shows the inherited recipients', function () {
    $client = User::factory()->client()->create(['name' => 'Acme Ltd']);

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    $this->actingAs($this->admin)->get(route('files.edit', $revision))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('assigned_clients', 1)
            ->where('assigned_clients.0.name', 'Acme Ltd'),
    );
});

test('the editor says when the original is one this staffer cannot edit', function () {
    // edit_files only: may edit their own uploads, not a colleague's.
    $staffer = staffWithPermissions([Permission::EditFiles->value, Permission::Upload->value]);

    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $staffer->id]);

    // Linked by an admin who may touch both.
    $this->versions->link($revision, $original, $this->admin);

    $this->actingAs($staffer)->get(route('files.edit', $revision))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('sharing_root.name', 'Rev C')
            // The page must not offer a link that lands on a 404.
            ->where('can_update_root', false),
    );
});

test('the candidates endpoint returns pickable files as json', function () {
    $subject = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev D']);
    $candidate = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);

    $response = $this->actingAs($this->admin)
        ->getJson(route('files.version.candidates', $subject).'?search=Rev+C');

    $response->assertOk();

    expect($response->json('files'))->toHaveCount(1)
        ->and($response->json('files.0.id'))->toBe($candidate->id);
});

test('a client-scoped staffer gets a 404 linking to a file outside their library', function () {
    $mine = User::factory()->client()->create();
    $theirs = User::factory()->client()->create();

    $staffer = User::factory()->role(SystemRole::ClientManager)->create();
    $staffer->assignedClients()->attach($mine->id);

    // Their own upload, so the subject half of the check passes and the
    // failure is genuinely about reaching the original.
    $subject = File::factory()->create(['uploaded_by' => $staffer->id]);
    shareFileWith($subject, $mine);

    $outOfScope = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($outOfScope, $theirs);

    // 404, not 403: confirming the id exists is itself information.
    $this->actingAs($staffer)
        ->put(route('files.version.store', $subject), ['previous_file_id' => $outOfScope->id])
        ->assertNotFound();
});

test('staff can link and unlink through the endpoints', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->from(route('files.edit', $revision))
        ->put(route('files.version.store', $revision), ['previous_file_id' => $original->id])
        ->assertRedirect(route('files.edit', $revision));

    expect($revision->fresh()->previous_file_id)->toBe($original->id);

    $this->actingAs($this->admin)
        ->from(route('files.edit', $revision))
        ->delete(route('files.version.destroy', $revision));

    expect($revision->fresh()->previous_file_id)->toBeNull();
});

test('the preview endpoint reports the recipients a link would move', function () {
    $client = User::factory()->client()->create(['name' => 'Acme Ltd']);

    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($revision, $client);

    $response = $this->actingAs($this->admin)
        ->getJson(route('files.version.preview', $revision).'?previous_file_id='.$original->id);

    $response->assertOk();

    expect($response->json('empty'))->toBeFalse()
        ->and($response->json('clients'))->toBe(['Acme Ltd']);
});

test('linking through the endpoint reports a validation error rather than crashing', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $taken = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->versions->link($taken, $original, $this->admin);

    $this->actingAs($this->admin)
        ->from(route('files.edit', $revision))
        ->put(route('files.version.store', $revision), ['previous_file_id' => $original->id])
        ->assertSessionHasErrors('previous_file_id');

    expect(FileAssignment::query()->where('file_id', $revision->id)->count())->toBe(0);
});
