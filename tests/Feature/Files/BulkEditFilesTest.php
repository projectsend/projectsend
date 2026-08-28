<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::Theme, 'default');
});

/**
 * A staff role limited to editing only files it owns — no
 * set_file_expiration_date/set_file_categories either, mirroring the
 * "File Editor Only" fixture FilesTest.php already uses for the equivalent
 * single-edit permission tests.
 */
function ownFilesOnlyStaff(string $roleName = 'Own Files Only'): User
{
    $role = Role::query()->create(['name' => $roleName, 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'upload'],
        ['role_id' => $role->id, 'permission' => 'edit_files'],
    ]);

    return User::factory()->create(['role_id' => $role->id]);
}

function noEditPayload(array $overrides = []): array
{
    return array_merge([
        'folder_action' => 'no_change',
        'description_action' => 'no_change',
        'expiration_action' => 'no_change',
        'add_category_ids' => [],
        'remove_category_ids' => [],
    ], $overrides);
}

test('a bulk edit only applies the fields that were actually touched', function () {
    $file = uploadDocumentFile($this->admin);
    $folder = Folder::query()->create(['name' => 'Reports']);
    $category = Category::query()->create(['name' => 'Invoices']);
    $file->update(['folder_id' => $folder->id, 'expires_at' => '2030-01-01']);
    $file->categories()->attach($category->id);

    $this->actingAs($this->admin)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$file->id],
        'description_action' => 'set',
        'description' => 'Updated description',
    ]))->assertRedirect();

    $file->refresh();
    expect($file->description)->toBe('Updated description')
        ->and($file->folder_id)->toBe($folder->id)
        ->and($file->expires_at?->toDateString())->toBe('2030-01-01')
        ->and($file->categories->pluck('id')->all())->toBe([$category->id]);
});

test('adding categories in bulk does not remove categories not selected for removal', function () {
    $file = uploadDocumentFile($this->admin);
    $existing = Category::query()->create(['name' => 'Existing']);
    $new = Category::query()->create(['name' => 'New']);
    $file->categories()->attach($existing->id);

    $this->actingAs($this->admin)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$file->id],
        'add_category_ids' => [$new->id],
    ]))->assertRedirect();

    expect($file->refresh()->categories->pluck('id')->sort()->values()->all())
        ->toBe(collect([$existing->id, $new->id])->sort()->values()->all());
});

test('removing categories in bulk does not touch categories not selected for removal', function () {
    $file = uploadDocumentFile($this->admin);
    $keep = Category::query()->create(['name' => 'Keep']);
    $drop = Category::query()->create(['name' => 'Drop']);
    $file->categories()->attach([$keep->id, $drop->id]);

    $this->actingAs($this->admin)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$file->id],
        'remove_category_ids' => [$drop->id],
    ]))->assertRedirect();

    expect($file->refresh()->categories->pluck('id')->all())->toBe([$keep->id]);
});

test('bulk expiration set/clear/no-change behave independently per action', function () {
    $withExpiry = uploadDocumentFile($this->admin, 'a.pdf');
    $withExpiry->update(['expires_at' => '2030-06-01']);
    $noExpiry = uploadDocumentFile($this->admin, 'b.pdf');

    // "no_change" leaves differing existing values exactly as they were.
    $this->actingAs($this->admin)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$withExpiry->id, $noExpiry->id],
        'description_action' => 'set',
        'description' => 'touch something unrelated',
    ]))->assertRedirect();

    expect($withExpiry->refresh()->expires_at?->toDateString())->toBe('2030-06-01')
        ->and($noExpiry->refresh()->expires_at)->toBeNull();

    // "set" applies the same date to both, regardless of prior state.
    $this->actingAs($this->admin)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$withExpiry->id, $noExpiry->id],
        'expiration_action' => 'set',
        'expires_at' => '2031-01-01',
    ]))->assertRedirect();

    expect($withExpiry->refresh()->expires_at?->toDateString())->toBe('2031-01-01')
        ->and($noExpiry->refresh()->expires_at?->toDateString())->toBe('2031-01-01');

    // "clear" nulls both.
    $this->actingAs($this->admin)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$withExpiry->id, $noExpiry->id],
        'expiration_action' => 'clear',
    ]))->assertRedirect();

    expect($withExpiry->refresh()->expires_at)->toBeNull()
        ->and($noExpiry->refresh()->expires_at)->toBeNull();
});

test('bulk folder move reparents every selected file, including to root', function () {
    $folder = Folder::query()->create(['name' => 'Reports']);
    $fileA = uploadDocumentFile($this->admin, 'a.pdf');
    $fileB = uploadDocumentFile($this->admin, 'b.pdf');

    $this->actingAs($this->admin)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$fileA->id, $fileB->id],
        'folder_action' => 'move',
        'folder_id' => $folder->id,
    ]))->assertRedirect();

    expect($fileA->refresh()->folder_id)->toBe($folder->id)
        ->and($fileB->refresh()->folder_id)->toBe($folder->id);

    $this->actingAs($this->admin)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$fileA->id, $fileB->id],
        'folder_action' => 'move',
        'folder_id' => null,
    ]))->assertRedirect();

    expect($fileA->refresh()->folder_id)->toBeNull()
        ->and($fileB->refresh()->folder_id)->toBeNull();
});

test('a file the acting staff member cannot edit is silently skipped, not 403d', function () {
    $staff = ownFilesOnlyStaff();
    expect($staff->can('edit_others_files'))->toBeFalse();

    $ownFile = uploadDocumentFile($staff, 'own.pdf');
    $othersFile = uploadDocumentFile($this->admin, 'others.pdf');

    $response = $this->actingAs($staff)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$ownFile->id, $othersFile->id],
        'description_action' => 'set',
        'description' => 'edited',
    ]));

    $response->assertRedirect();
    expect(session('success'))->toContain('1 of 2 selected files were updated');

    expect($ownFile->refresh()->description)->toBe('edited')
        ->and($othersFile->refresh()->description)->not->toBe('edited');
});

test('a bulk edit where nothing is authorized returns 422, not a misleading success', function () {
    $role = Role::query()->create(['name' => 'No Edit', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);
    $staff = User::factory()->create(['role_id' => $role->id]);
    $file = uploadDocumentFile($this->admin);

    $this->actingAs($staff)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$file->id],
        'description_action' => 'set',
        'description' => 'nope',
    ]))->assertStatus(422);
});

test('expiration and category changes in a bulk edit are silently ignored without the matching permission', function () {
    $staff = ownFilesOnlyStaff('File Editor Only, No Expiry Or Categories');
    expect($staff->can('set_file_expiration_date'))->toBeFalse()
        ->and($staff->can('set_file_categories'))->toBeFalse();

    $file = uploadDocumentFile($staff);
    $category = Category::query()->create(['name' => 'Should Not Apply']);

    $this->actingAs($staff)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$file->id],
        'expiration_action' => 'set',
        'expires_at' => '2030-01-01',
        'add_category_ids' => [$category->id],
    ]))->assertRedirect();

    expect($file->refresh()->expires_at)->toBeNull()
        ->and($file->categories()->count())->toBe(0);
});

test('a skip for a missing field permission is not reported as a missing edit permission', function () {
    // They own all three files and hold edit_files. What they lack is
    // set_file_expiration_date, and that is what the message has to say --
    // "you don't have permission to edit them" is both wrong and
    // unactionable, since editing is exactly what they may do.
    $staff = ownFilesOnlyStaff('File Editor Only, No Expiry');
    expect($staff->can('edit_files'))->toBeTrue()
        ->and($staff->can('set_file_expiration_date'))->toBeFalse();

    $files = collect(['a.pdf', 'b.pdf', 'c.pdf'])
        ->map(fn (string $name) => uploadDocumentFile($staff, $name));

    $this->actingAs($staff)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => $files->pluck('id')->all(),
        'expiration_action' => 'set',
        'expires_at' => '2030-01-01',
    ]))->assertRedirect();

    expect(session('success'))
        ->toBe('0 of 3 selected files were updated. The rest were skipped because you don\'t have permission to make those changes.');
});

test('a skip for a missing edit permission still says so', function () {
    // The other reason, unchanged: every file that went unchanged here is
    // one this staff member may not edit at all.
    $staff = ownFilesOnlyStaff('Own Files Only, Two Reasons Apart');
    $own = uploadDocumentFile($staff, 'own.pdf');
    $theirs = uploadDocumentFile($this->admin, 'theirs.pdf');

    $this->actingAs($staff)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$own->id, $theirs->id],
        'description_action' => 'set',
        'description' => 'edited',
    ]))->assertRedirect();

    expect(session('success'))
        ->toBe('1 of 2 selected files were updated. The rest were skipped because you don\'t have permission to edit them.');
});

test('a mixture of both reasons reports the one that covers both', function () {
    $staff = ownFilesOnlyStaff('Own Files Only, No Expiry');
    $own = uploadDocumentFile($staff, 'own.pdf');
    $theirs = uploadDocumentFile($this->admin, 'theirs.pdf');

    // One file is not theirs to edit; the other is, but the only change
    // asked for needs a permission they do not hold.
    $this->actingAs($staff)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$own->id, $theirs->id],
        'expiration_action' => 'set',
        'expires_at' => '2030-01-01',
    ]))->assertRedirect();

    expect(session('success'))
        ->toBe('0 of 2 selected files were updated. The rest were skipped because you don\'t have permission to make those changes.');
});

test('a bulk edit that changes nothing is rejected', function () {
    $file = uploadDocumentFile($this->admin);

    $this->actingAs($this->admin)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$file->id],
    ]))->assertStatus(422);
});

test('each successfully updated file gets exactly one FileUpdated activity log entry', function () {
    $staff = ownFilesOnlyStaff('Own Files Only 2');

    $ownFile = uploadDocumentFile($staff, 'own.pdf');
    $othersFile = uploadDocumentFile($this->admin, 'others.pdf');

    $before = ActivityLog::query()->where('action', Action::FileUpdated)->count();

    $this->actingAs($staff)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$ownFile->id, $othersFile->id],
        'description_action' => 'set',
        'description' => 'edited',
    ]))->assertRedirect();

    expect(ActivityLog::query()->where('action', Action::FileUpdated)->count())->toBe($before + 1);
});

test('the bulk-edit route is not swallowed by the single-file {file} route', function () {
    $file = uploadDocumentFile($this->admin);

    $this->actingAs($this->admin)->patch('/files/bulk-edit', noEditPayload([
        'file_ids' => [$file->id],
        'description_action' => 'set',
        'description' => 'route sanity',
    ]))->assertRedirect();

    expect($file->refresh()->description)->toBe('route sanity');
});
