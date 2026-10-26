<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The orphan tool works on the storage, not on a library. Stray bytes have
 * no row, so no owner and no client to filter by; the missing tab lists
 * rows from every client. A role limited to assigned clients therefore
 * never holds `import_orphans`, ticked or not. Reported by @Haind03 as
 * GHSA-767q-xvq3-r3fv.
 */
beforeEach(function () {
    Storage::fake('files');
    Storage::fake('files_external');
    User::factory()->create();
});

function orphanToolStaff(bool $clientScoped): User
{
    $role = Role::query()->create(['name' => 'Role '.Str::random(6), 'client_scoped' => $clientScoped]);

    foreach ([Permission::Upload, Permission::ImportOrphans] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

test('a client-scoped role cannot open either tab, even with the permission ticked', function () {
    $staff = orphanToolStaff(clientScoped: true);
    File::factory()->create(['name' => 'Someone else\'s contract.pdf', 'scan_status' => ScanStatus::Missing]);

    $this->actingAs($staff)->get('/files/orphans')->assertForbidden();
    $this->actingAs($staff)->get('/files/orphans?tab=missing')->assertForbidden();

    expect($staff->can('import_orphans'))->toBeFalse();
});

test('a client-scoped role cannot import or delete stray files either', function () {
    $staff = orphanToolStaff(clientScoped: true);
    Storage::disk('files')->put('2026/10/stray.txt', 'hello world');

    $this->actingAs($staff)->post('/files/orphans/delete', ['items' => [['disk' => 'files', 'path' => '2026/10/stray.txt']]])
        ->assertForbidden();

    expect(Storage::disk('files')->exists('2026/10/stray.txt'))->toBeTrue();
});

test('an unscoped role with the permission still uses the tool', function () {
    $staff = orphanToolStaff(clientScoped: false);

    $this->actingAs($staff)->get('/files/orphans')->assertOk();
    $this->actingAs($staff)->get('/files/orphans?tab=missing')->assertOk();
});

test('the menu is not offered the tool for a client-scoped role', function () {
    $scoped = orphanToolStaff(clientScoped: true);
    $unscoped = orphanToolStaff(clientScoped: false);
    $checker = app(PermissionChecker::class);

    expect($checker->grantedKeys($scoped))->not->toContain('import_orphans')
        ->and($checker->grantedKeys($unscoped))->toContain('import_orphans');
});
