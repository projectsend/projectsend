<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| A date sent as a JSON number
|--------------------------------------------------------------------------
|
| `date` accepts a number whenever date_parse reads it as a real day —
| 20301231 validates — and hands it on unconverted. Every one of these
| fields then reaches a strictly typed `string` parameter (FileExpiry,
| LocalDay), so the number was a 500 instead of a validation error. A form
| never sends one, because form values are always text; a JSON body can.
|
*/

const NUMBER_DATE = 20301231;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

test('the files API refuses a numeric expiry instead of failing', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $token = $this->admin->createToken('t', [
        Permission::EditFiles->value,
        Permission::SetFileExpirationDate->value,
    ])->plainTextToken;

    $this->withToken($token)->patchJson("/api/v1/files/{$file->id}", ['expires_at' => NUMBER_DATE])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expires_at');
});

test('the staff file editor refuses a numeric expiry instead of failing', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->patchJson("/files/{$file->id}", [
        'name' => $file->name,
        'expires_at' => NUMBER_DATE,
    ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');
});

test('the bulk editor refuses a numeric expiry instead of failing', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->patchJson('/files/bulk-edit', [
        'file_ids' => [$file->id],
        'folder_action' => 'no_change',
        'description_action' => 'no_change',
        'expiration_action' => 'set',
        'expires_at' => NUMBER_DATE,
    ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');
});

test('a new share link refuses a numeric expiry instead of failing', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->postJson("/files/{$file->id}/share-links", ['expires_at' => NUMBER_DATE])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expires_at');
});

test('the client file editor refuses a numeric expiry instead of failing', function () {
    $role = Role::query()->create(['name' => 'Client Role '.Str::random(6)]);
    foreach ([Permission::EditFiles, Permission::SetFileExpirationDate] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission->value]);
    }
    $client = User::factory()->client()->create(['role_id' => $role->id]);
    $file = File::factory()->create(['uploaded_by' => $client->id]);

    $this->actingAs($client)->patchJson("/my-files/{$file->id}", [
        'name' => $file->name,
        'expires_at' => NUMBER_DATE,
    ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');
});

test('the same dates written as text are still accepted', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $token = $this->admin->createToken('t', [
        Permission::EditFiles->value,
        Permission::SetFileExpirationDate->value,
    ])->plainTextToken;

    $this->withToken($token)->patchJson("/api/v1/files/{$file->id}", ['expires_at' => '2030-12-31'])->assertOk();

    expect($file->refresh()->expires_at)->not->toBeNull();
});
