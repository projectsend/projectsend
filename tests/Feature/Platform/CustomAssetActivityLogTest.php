<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Illuminate\Support\Str;
use ProjectSend\CommunityModules\Modules\CustomAssets\Models\CustomAsset;

beforeEach(function () {
    User::factory()->create();
});

/** A staff user whose role has exactly the given custom-asset permission keys. */
function staffWithCustomAssetPermissions(array $permissions): User
{
    $role = Role::query()->create(['name' => 'Role '.Str::random(6)]);
    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

test('creating, updating, toggling, and deleting a custom asset each record a real activity log entry', function () {
    $staff = staffWithCustomAssetPermissions(['create_assets', 'edit_assets', 'delete_assets']);

    $this->actingAs($staff)->post('/system/settings/custom-assets', [
        'title' => 'Tracking snippet',
        'language' => 'js',
        'content' => 'console.log(1);',
        'surfaces' => ['public'],
        'position' => 'head',
        'enabled' => false,
    ])->assertRedirect();

    $asset = CustomAsset::query()->sole();

    $created = ActivityLog::query()->where('action', Action::CustomAssetCreated)->sole();
    expect($created->actor_id)->toBe($staff->id)
        ->and($created->subject_name)->toBe('Tracking snippet');

    $this->actingAs($staff)->patch("/system/settings/custom-assets/{$asset->id}", [
        'title' => 'Tracking snippet (renamed)',
        'language' => 'js',
        'content' => 'console.log(2);',
        'surfaces' => ['public'],
        'position' => 'head',
        'enabled' => false,
    ])->assertRedirect();

    $updated = ActivityLog::query()->where('action', Action::CustomAssetUpdated)->sole();
    expect($updated->subject_name)->toBe('Tracking snippet (renamed)');

    $this->actingAs($staff)->patch("/system/settings/custom-assets/{$asset->id}/toggle")->assertRedirect();

    $enabled = ActivityLog::query()->where('action', Action::CustomAssetEnabled)->sole();
    expect($enabled->subject_id)->toBe($asset->id);

    $this->actingAs($staff)->delete("/system/settings/custom-assets/{$asset->id}")->assertRedirect();

    $deleted = ActivityLog::query()->where('action', Action::CustomAssetDeleted)->sole();
    expect($deleted->context)->toBe(['name' => 'Tracking snippet (renamed)'])
        ->and(ActivityLog::query()->where('action', Action::CustomAssetDisabled)->count())->toBe(0);
})->skip(! class_exists(CustomAsset::class), 'projectsend/community-modules is not installed in this environment.');
