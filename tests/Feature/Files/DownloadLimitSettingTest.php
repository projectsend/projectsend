<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\DownloadLimitScope;
use App\Modules\Files\Models\File;
use Inertia\Testing\AssertableInertia;

/**
 * Setting the cap, and — more to the point — not being able to set it
 * without `limit_downloads`. The house rule for a permission-gated field
 * is that it is silently left alone rather than 422'd, so that a form
 * which does not show the field can still be submitted.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('an editor with the permission sets a limit and its scope', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", [
            'name' => $file->name,
            'download_limit' => 5,
            'download_limit_scope' => 'per_user',
        ])
        ->assertRedirect();

    $file->refresh();

    expect($file->download_limit)->toBe(5)
        ->and($file->download_limit_scope)->toBe(DownloadLimitScope::PerUser);
});

test('an editor without the permission leaves the limit untouched', function () {
    $file = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'download_limit' => 3,
        'download_limit_scope' => DownloadLimitScope::PerUser,
    ]);

    $staff = staffWithPermissions(['upload', 'edit_files', 'edit_others_files']);

    expect($staff->can('limit_downloads'))->toBeFalse();

    $this->actingAs($staff)
        ->patch("/files/{$file->id}", [
            'name' => 'Renamed',
            'download_limit' => 999,
            'download_limit_scope' => 'total',
        ])
        ->assertRedirect();

    $file->refresh();

    // The rename went through; the cap they may not set did not.
    expect($file->name)->toBe('Renamed')
        ->and($file->download_limit)->toBe(3)
        ->and($file->download_limit_scope)->toBe(DownloadLimitScope::PerUser);
});

test('omitting the field clears the limit for someone who may set it', function () {
    // Same shape as expires_at: the form always posts the field, so an
    // empty one means "no limit" rather than "leave it".
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'download_limit' => 4]);

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => $file->name])
        ->assertRedirect();

    expect($file->refresh()->download_limit)->toBeNull();
});

test('a limit below one is refused', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", ['name' => $file->name, 'download_limit' => 0])
        ->assertSessionHasErrors('download_limit');
});

test('an unknown scope is refused rather than silently stored', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->patch("/files/{$file->id}", [
            'name' => $file->name,
            'download_limit' => 2,
            'download_limit_scope' => 'per_ip',
        ])
        ->assertSessionHasErrors('download_limit_scope');
});

test('the editor is told whether it may set a limit, and what has been spent', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'download_limit' => 2]);

    $this->actingAs($this->admin)
        ->get("/files/{$file->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can_limit_downloads', true)
            ->where('file.download_limit', 2)
            ->where('file.download_limit_scope', 'total')
            ->where('file.downloads_used', 0));
});

test('a bulk edit sets and clears limits, and skips them without the permission', function () {
    $files = File::factory()->count(2)->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->patch('/files/bulk-edit', [
            'file_ids' => $files->pluck('id')->all(),
            'folder_action' => 'no_change',
            'description_action' => 'no_change',
            'expiration_action' => 'no_change',
            'download_limit_action' => 'set',
            'download_limit' => 7,
            'download_limit_scope' => 'per_user',
        ])
        ->assertRedirect();

    foreach ($files as $file) {
        expect($file->refresh()->download_limit)->toBe(7)
            ->and($file->download_limit_scope)->toBe(DownloadLimitScope::PerUser);
    }

    // Clearing puts them back to unlimited.
    $this->actingAs($this->admin)
        ->patch('/files/bulk-edit', [
            'file_ids' => $files->pluck('id')->all(),
            'folder_action' => 'no_change',
            'description_action' => 'no_change',
            'expiration_action' => 'no_change',
            'download_limit_action' => 'clear',
        ])
        ->assertRedirect();

    expect($files->first()->refresh()->download_limit)->toBeNull();
});

test('a bulk edit silently ignores a limit the editor may not set', function () {
    // The same shape BulkEditFilesTest already pins for expiry and
    // categories: the request is accepted and the field it was not
    // allowed to touch is left exactly as it was.
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $staff = staffWithPermissions(['upload', 'edit_files', 'edit_others_files']);

    $this->actingAs($staff)
        ->patch('/files/bulk-edit', [
            'file_ids' => [$file->id],
            'folder_action' => 'no_change',
            'description_action' => 'no_change',
            'expiration_action' => 'no_change',
            'download_limit_action' => 'set',
            'download_limit' => 9,
        ])
        ->assertRedirect();

    expect($file->refresh()->download_limit)->toBeNull();
});

test('the API sets a limit and reports what is left', function () {
    $token = $this->admin->createToken('t', ['upload', 'edit_files', 'edit_others_files'])->plainTextToken;
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->withToken($token)
        ->patchJson("/api/v1/files/{$file->id}", ['download_limit' => 3, 'download_limit_scope' => 'per_user'])
        ->assertOk()
        ->assertJsonPath('data.download_limit', 3)
        ->assertJsonPath('data.download_limit_scope', 'per_user')
        ->assertJsonPath('data.downloads_used', 0);
});

test('the API leaves the limit alone for a token whose user lacks the permission', function () {
    $staff = staffWithPermissions(['upload', 'edit_files', 'edit_others_files']);
    $token = $staff->createToken('t', ['upload', 'edit_files', 'edit_others_files'])->plainTextToken;
    $file = File::factory()->create(['uploaded_by' => $staff->id, 'download_limit' => 2]);

    $this->withToken($token)
        ->patchJson("/api/v1/files/{$file->id}", ['download_limit' => 50])
        ->assertOk()
        ->assertJsonPath('data.download_limit', 2);
});
