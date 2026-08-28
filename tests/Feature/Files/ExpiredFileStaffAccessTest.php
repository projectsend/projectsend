<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Expiry hides a file from clients and the public site; staff keep it.
 * For a client-scoped staff member that is true of their own uploads and
 * not of their clients' files, because the second half of their library
 * is File::scopeVisibleToClient and it ends in notExpired().
 *
 * c8078f65 decided that deliberately rather than widening the scope —
 * "the single source of truth for client file access", changed for a
 * dashboard widget — and relabelled the widget instead. Nothing executed
 * that decision, so File::isExpired's docblock could go on promising
 * staff full access without anything failing. These pin both halves.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach ([Permission::Upload, Permission::EditFiles] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    $this->rep = User::factory()->create(['role_id' => $role->id]);
    $this->client = User::factory()->client()->create();
    $this->rep->assignedClients()->sync([$this->client->id]);
});

function libraryHoldsFile(User $staff, File $file): bool
{
    // The scope memoises its built query per user id, so a fresh
    // container is what makes a second question in one test honest.
    app()->forgetInstance(StaffLibraryScope::class);

    return app(StaffLibraryScope::class)->files($staff)->whereKey($file->id)->exists();
}

test('an unscoped staff member keeps a file after it expires', function () {
    $file = uploadNamedFile($this->admin, 'annual-report');
    shareFileWith($file, $this->client);

    $file->forceFill(['expires_at' => now()->subDay()])->save();

    expect(libraryHoldsFile($this->admin, $file))->toBeTrue();
    $this->actingAs($this->admin)->get("/files/{$file->id}/download")->assertOk();
});

test('a client-scoped staff member keeps their own expired upload', function () {
    $file = uploadNamedFile($this->rep, 'my-own-note');

    $file->forceFill(['expires_at' => now()->subDay()])->save();

    expect(libraryHoldsFile($this->rep, $file))->toBeTrue();
});

test('a client-scoped staff member loses a client file when it expires', function () {
    $file = uploadNamedFile($this->admin, 'annual-report');
    shareFileWith($file, $this->client);

    expect(libraryHoldsFile($this->rep, $file))->toBeTrue();
    $this->actingAs($this->rep)->get("/files/{$file->id}/download")->assertOk();

    $file->forceFill(['expires_at' => now()->subDay()])->save();

    // Deliberate, not an oversight — see the file docblock above. If this
    // ever changes, File::isExpired's docblock changes with it.
    expect(libraryHoldsFile($this->rep, $file))->toBeFalse();
    $this->actingAs($this->rep)->get("/files/{$file->id}/download")->assertForbidden();
});
