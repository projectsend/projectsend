<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Audit\ActivityOrigin;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Every audit entry records how the action arrived
|--------------------------------------------------------------------------
|
| The same person deleting the same file from the UI and from an
| integration produces two otherwise identical entries. "Did I do that, or
| did the Zapier token?" is the first question asked when something
| unexpected turns up in the log, and the answer has to be in the row.
|
*/

beforeEach(function () {
    Storage::fake('files');
    $this->staff = User::factory()->create();
});

test('an action taken in the UI is recorded as such', function () {
    $file = File::factory()->create(['uploaded_by' => $this->staff->id]);

    $this->actingAs($this->staff)->patch("/files/{$file->id}", ['name' => 'Renamed']);

    $entry = ActivityLog::query()->where('action', Action::FileUpdated)->latest('id')->firstOrFail();

    expect($entry->origin)->toBe(ActivityOrigin::Ui)
        ->and($entry->api_token_id)->toBeNull()
        ->and($entry->api_token_name)->toBeNull();
});

test('an action taken through the API records the token that did it', function () {
    $created = $this->staff->createToken('Zapier', [
        Permission::EditFiles->value,
        Permission::EditOthersFiles->value,
    ]);
    $file = File::factory()->create(['uploaded_by' => $this->staff->id]);

    $this->withToken($created->plainTextToken)
        ->patchJson("/api/v1/files/{$file->id}", ['name' => 'Renamed by robot'])
        ->assertOk();

    $entry = ActivityLog::query()->where('action', Action::FileUpdated)->latest('id')->firstOrFail();

    expect($entry->origin)->toBe(ActivityOrigin::Api)
        ->and($entry->api_token_id)->toBe($created->accessToken->getKey())
        ->and($entry->api_token_name)->toBe('Zapier');
});

test('the token name survives the token being revoked', function () {
    // The whole point of investigating a leaked token is reading what it
    // did, which usually happens after revoking it.
    $created = $this->staff->createToken('Doomed', [Permission::Upload->value]);

    app(ActivityLogger::class)->log(Action::FileUploaded, $this->staff->fresh()->withAccessToken($created->accessToken));

    $created->accessToken->delete();

    $entry = ActivityLog::query()->latest('id')->firstOrFail();

    expect($entry->api_token_name)->toBe('Doomed')
        ->and($entry->origin)->toBe(ActivityOrigin::Api);
});

test('a system action is recorded as system', function () {
    app(ActivityLogger::class)->logSystem(Action::ExpiredFileDeleted, ['name' => 'old.pdf']);

    expect(ActivityLog::query()->latest('id')->firstOrFail()->origin)->toBe(ActivityOrigin::System);
});

/*
|--------------------------------------------------------------------------
| Surfaced in the log, the filter and the export
|--------------------------------------------------------------------------
*/

test('the activity list reports the origin of each entry', function () {
    $created = $this->staff->createToken('Zapier', [Permission::Upload->value]);
    app(ActivityLogger::class)->log(Action::FileUploaded, $this->staff->fresh()->withAccessToken($created->accessToken));

    $this->actingAs($this->staff)->get('/activity')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entries.0.origin', 'api')
            ->where('entries.0.api_token_name', 'Zapier'));
});

test('entries can be filtered by origin', function () {
    $created = $this->staff->createToken('Zapier', [Permission::Upload->value]);
    $logger = app(ActivityLogger::class);

    $logger->log(Action::FileUploaded, $this->staff->fresh()->withAccessToken($created->accessToken));
    $logger->log(Action::FileDeleted, $this->staff, context: ['name' => 'from the ui']);

    $apiOnly = $this->actingAs($this->staff)->get('/activity?origin=api')
        ->assertOk()
        ->viewData('page')['props']['entries'];

    expect($apiOnly)->toHaveCount(1)
        ->and($apiOnly[0]['action'])->toBe(Action::FileUploaded->value);
});

test('the CSV export carries an origin and token column', function () {
    $created = $this->staff->createToken('Zapier', [Permission::Upload->value]);
    app(ActivityLogger::class)->log(Action::FileUploaded, $this->staff->fresh()->withAccessToken($created->accessToken));

    $csv = $this->actingAs($this->staff)->get('/activity/export')->streamedContent();

    expect($csv)->toContain('Origin,"API token"')
        ->and($csv)->toContain('Zapier')
        ->and($csv)->toContain('API');
});

test('an unknown origin filter is rejected', function () {
    $this->actingAs($this->staff)->get('/activity?origin=carrier-pigeon')
        ->assertSessionHasErrors('origin');
});
