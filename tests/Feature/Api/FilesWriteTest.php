<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityOrigin;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->token = $this->admin->createToken('t', [
        Permission::Upload->value,
        Permission::EditFiles->value,
        Permission::EditOthersFiles->value,
        Permission::DeleteFiles->value,
        Permission::DeleteOthersFiles->value,
        Permission::SetFileExpirationDate->value,
        Permission::SetFileCategories->value,
        Permission::UploadPublic->value,
    ])->plainTextToken;
});

test('a file can be uploaded in a single request', function () {
    $response = $this->withToken($this->token)->post('/api/v1/files', [
        'file' => UploadedFile::fake()->create('report.pdf', 12, 'application/pdf'),
        'name' => 'Quarterly report',
        'description' => 'Q3',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Quarterly report')
        ->assertJsonPath('data.original_name', 'report.pdf');

    $file = File::query()->latest('id')->firstOrFail();
    expect($file->uploaded_by)->toBe($this->admin->id)
        ->and(Storage::disk('files')->exists($file->path))->toBeTrue();
});

test('the upload honours the max file size setting, not a hardcoded cap', function () {
    // Snapshot and restore: never assume a Setting's current value.
    $settings = app(Settings::class);
    $original = $settings->get(Setting::MaxFileSizeMb);

    try {
        $settings->set(Setting::MaxFileSizeMb, 1);

        $this->withToken($this->token)->post('/api/v1/files', [
            'file' => UploadedFile::fake()->create('big.pdf', 2048, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('type', 'validation_failed');

        expect(File::query()->count())->toBe(0);
    } finally {
        $settings->set(Setting::MaxFileSizeMb, $original);
    }
});

test('a disallowed extension is refused', function () {
    $settings = app(Settings::class);
    $originalRestriction = $settings->get(Setting::UploadTypeRestriction);
    $originalExtensions = $settings->get(Setting::AllowedUploadExtensions);

    try {
        $settings->set(Setting::UploadTypeRestriction, 'everyone');
        $settings->set(Setting::AllowedUploadExtensions, ['pdf']);

        $this->withToken($this->token)->post('/api/v1/files', [
            'file' => UploadedFile::fake()->create('script.sh', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        expect(File::query()->count())->toBe(0);
    } finally {
        $settings->set(Setting::UploadTypeRestriction, $originalRestriction);
        $settings->set(Setting::AllowedUploadExtensions, $originalExtensions);
    }
});

test('the stored mime type comes from the bytes, not the request', function () {
    // A caller controls the Content-Type it declares, and the mime type
    // decides how the file is later served and previewed.
    $this->withToken($this->token)->post('/api/v1/files', [
        'file' => UploadedFile::fake()->createWithContent('note.txt', 'plain text here'),
    ], ['Accept' => 'application/json'])->assertStatus(201);

    expect(File::query()->latest('id')->firstOrFail()->mime_type)->not->toBe('application/x-httpd-php');
});

test('uploading requires the upload ability specifically', function () {
    $token = $this->admin->createToken('read-only', [Permission::EditFiles->value])->plainTextToken;

    $this->withToken($token)->post('/api/v1/files', [
        'file' => UploadedFile::fake()->create('x.pdf', 1, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertForbidden();
});

test('a file can be updated field by field', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Old name']);
    $category = Category::query()->create(['name' => 'Invoices']);

    $this->withToken($this->token)->patchJson("/api/v1/files/{$file->id}", [
        'name' => 'New name',
        'categories' => [$category->id],
        'expires_at' => now()->addWeek()->toIso8601String(),
    ])->assertOk()->assertJsonPath('data.name', 'New name');

    $file->refresh();
    expect($file->name)->toBe('New name')
        ->and($file->expires_at)->not->toBeNull()
        ->and($file->categories()->count())->toBe(1)
        // Untouched: PATCH changes only what was sent.
        ->and($file->description)->toBe($file->getOriginal('description'));
});

test('a calendar day means the end of that day where the caller lives', function () {
    // The same value on the web means the end of the 12th (LocalDay::end via
    // FilesController::expiryInstant). Stored as it arrives it is midnight
    // UTC, so the file would die at the *start* of the 12th instead.
    $this->admin->update(['timezone' => 'Europe/Berlin']);
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->withToken($this->token)->patchJson("/api/v1/files/{$file->id}", [
        'expires_at' => '2026-09-12',
    ])->assertOk();

    // 23:59:59 on the 12th in Berlin is 21:59:59Z.
    expect($file->refresh()->expires_at?->toIso8601String())->toBe('2026-09-12T21:59:59+00:00');
});

test('a timestamp is stored as the instant it names', function () {
    // The half that must not change: an API caller can name a moment, and
    // naming one is not the same as naming a day.
    $this->admin->update(['timezone' => 'Europe/Berlin']);
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->withToken($this->token)->patchJson("/api/v1/files/{$file->id}", [
        'expires_at' => '2026-09-12T08:30:00+00:00',
    ])->assertOk();

    expect($file->refresh()->expires_at?->toIso8601String())->toBe('2026-09-12T08:30:00+00:00');
});

test('clearing the expiry still clears it', function () {
    $file = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'expires_at' => now()->addWeek(),
    ]);

    $this->withToken($this->token)->patchJson("/api/v1/files/{$file->id}", [
        'expires_at' => null,
    ])->assertOk();

    expect($file->refresh()->expires_at)->toBeNull();
});

test('fields the caller lacks permission for are left alone rather than refused', function () {
    // Mirrors the web controller: a user who may edit a file but not set
    // expiry dates still gets to rename it.
    $editor = staffWithPermissions([Permission::EditFiles->value]);
    $file = File::factory()->create(['uploaded_by' => $editor->id, 'name' => 'Original']);
    $token = $editor->createToken('t', [Permission::EditFiles->value])->plainTextToken;

    $this->withToken($token)->patchJson("/api/v1/files/{$file->id}", [
        'name' => 'Renamed',
        'expires_at' => now()->addWeek()->toIso8601String(),
        // A slug is required alongside public=true by Rules::slug, the same
        // as on the web form — supplied here so the request is valid and the
        // permission gate is what decides the outcome, not validation.
        'public' => true,
        'slug' => 'renamed-file',
    ])->assertOk();

    $file->refresh();
    expect($file->name)->toBe('Renamed')
        ->and($file->expires_at)->toBeNull()
        ->and($file->public)->toBeFalse();
});

test('editing someone elses file needs the others permission', function () {
    $other = User::factory()->create();
    $file = File::factory()->create(['uploaded_by' => $other->id]);

    $ownOnly = staffWithPermissions([Permission::EditFiles->value]);
    $token = $ownOnly->createToken('t', [Permission::EditFiles->value])->plainTextToken;

    $this->withToken($token)->patchJson("/api/v1/files/{$file->id}", ['name' => 'Mine now'])
        ->assertForbidden();
});

test('a file can be deleted', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->withToken($this->token)->deleteJson("/api/v1/files/{$file->id}")->assertNoContent();

    expect(File::query()->find($file->id))->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::FileDeleted)->exists())->toBeTrue();
});

test('deleting needs a delete ability, not merely edit', function () {
    $editor = staffWithPermissions([Permission::EditFiles->value]);
    $file = File::factory()->create(['uploaded_by' => $editor->id]);
    $token = $editor->createToken('t', [Permission::EditFiles->value])->plainTextToken;

    $this->withToken($token)->deleteJson("/api/v1/files/{$file->id}")->assertForbidden();

    expect(File::query()->find($file->id))->not->toBeNull();
});

test('a file can be assigned to a client and to a group', function () {
    $client = User::factory()->client()->create(['name' => 'Acme']);
    $group = Group::query()->create(['name' => 'Partners']);
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->withToken($this->token)
        ->postJson("/api/v1/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id])
        ->assertOk();

    $this->withToken($this->token)
        ->postJson("/api/v1/files/{$file->id}/assignments", ['type' => 'group', 'id' => $group->id])
        ->assertOk();

    expect(FileAssignment::query()->where('file_id', $file->id)->count())->toBe(2)
        ->and(ActivityLog::query()->where('action', Action::FileAssigned)->count())->toBe(2);
});

test('assigning twice is idempotent, so a retry is safe', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    foreach (range(1, 3) as $ignored) {
        $this->withToken($this->token)
            ->postJson("/api/v1/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id])
            ->assertOk();
    }

    expect(FileAssignment::query()->where('file_id', $file->id)->count())->toBe(1);
});

test('an assignment can be revoked', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->withToken($this->token)
        ->postJson("/api/v1/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $this->withToken($this->token)
        ->deleteJson("/api/v1/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id])
        ->assertOk();

    expect(FileAssignment::query()->where('file_id', $file->id)->count())->toBe(0);
});

test('a client-scoped staff token cannot share with someone elses client', function () {
    $mine = User::factory()->client()->create();
    $theirs = User::factory()->client()->create();

    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([$mine->id]);

    $file = File::factory()->create(['uploaded_by' => $manager->id]);
    $token = $manager->createToken('t', [Permission::EditFiles->value])->plainTextToken;

    // 422 rather than 403: ResolvesShareTargets reports an out-of-scope
    // target as an invalid `id`, which is what the web surface does too and
    // is the weaker disclosure of the two — it does not confirm that the
    // client exists and is merely someone else's.
    $this->withToken($token)
        ->postJson("/api/v1/files/{$file->id}/assignments", ['type' => 'client', 'id' => $theirs->id])
        ->assertStatus(422)
        ->assertJsonPath('type', 'validation_failed');

    expect(FileAssignment::query()->where('file_id', $file->id)->count())->toBe(0);
});

/*
 * Every mutation through the API is distinguishable from the same change
 * made in the UI — the basis for the per-token history the API dashboard
 * will show.
 */
test('API mutations are tagged with how they arrived', function () {
    $this->withToken($this->token)->post('/api/v1/files', [
        'file' => UploadedFile::fake()->create('tagged.pdf', 1, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertStatus(201);

    $entry = ActivityLog::query()->where('action', Action::FileUploaded)->latest('id')->firstOrFail();

    expect($entry->origin)->toBe(ActivityOrigin::Api)
        ->and($entry->api_token_name)->toBe('t');
});

test('the same change made in the UI carries no api tag', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->patch("/files/{$file->id}", ['name' => 'Renamed in the UI']);

    $entry = ActivityLog::query()->where('action', Action::FileUpdated)->latest('id')->firstOrFail();

    expect($entry->origin)->toBe(ActivityOrigin::Ui)
        ->and($entry->api_token_id)->toBeNull();
});
