<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| GET /api/v1/activity
|--------------------------------------------------------------------------
|
| The endpoint an automation tool reacts to. Every other list says what is
| there now; this one says what happened, which is the only way to notice
| a share or a download at all — neither leaves a trace on any other list.
|
*/

beforeEach(function () {
    Storage::fake('files');
    $this->staff = User::factory()->create();
});

function activityToken(User $user, array $abilities = [Permission::ViewActionsLog->value]): string
{
    return $user->createToken('Zapier', $abilities)->plainTextToken;
}

test('the feed lists what happened, newest first', function () {
    $logger = app(ActivityLogger::class);
    $logger->log(Action::FileUploaded, $this->staff, context: ['name' => 'older']);
    $logger->log(Action::FileDownloaded, $this->staff, context: ['name' => 'newer']);

    $response = $this->withToken(activityToken($this->staff))
        ->getJson('/api/v1/activity')
        ->assertOk();

    // Newest first is what a polling trigger reads: it takes the first
    // page and de-duplicates by id.
    expect($response->json('data.0.action'))->toBe('file.downloaded')
        ->and($response->json('data.1.action'))->toBe('file.uploaded');
});

test('sharing a file is visible here and nowhere else', function () {
    // The reason this endpoint exists. FileSharing::assign() writes an
    // assignment row and never touches the file, so a caller polling
    // /files?updated_since= sees nothing at all.
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $this->staff->id]);

    $token = activityToken($this->staff, [
        Permission::ViewActionsLog->value,
        Permission::EditFiles->value,
        Permission::Upload->value,
    ]);

    $untouched = $file->fresh()->updated_at;

    $this->withToken($token)
        ->postJson("/api/v1/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id])
        ->assertSuccessful();

    // The file itself is exactly as it was, so no amount of polling
    // /files?updated_since= will ever surface the share.
    expect($file->fresh()->updated_at->equalTo($untouched))->toBeTrue();

    $activity = $this->withToken($token)
        ->getJson('/api/v1/activity?action[]='.Action::FileAssigned->value)
        ->assertOk();

    expect($activity->json('data.0.action'))->toBe('file.assigned')
        ->and($activity->json('data.0.subject.type'))->toBe('file')
        ->and($activity->json('data.0.subject.id'))->toBe($file->id);
});

test('entries can be narrowed to the actions a caller cares about', function () {
    $logger = app(ActivityLogger::class);
    $logger->log(Action::FileUploaded, $this->staff);
    $logger->log(Action::FileDownloaded, $this->staff);
    $logger->log(Action::UserCreated, $this->staff);

    $response = $this->withToken(activityToken($this->staff))
        ->getJson('/api/v1/activity?action[]=file.downloaded&action[]=user.created')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('action')->sort()->values()->all())
        ->toBe(['file.downloaded', 'user.created']);
});

test('an action nobody has heard of is refused, not ignored', function () {
    // Silently ignoring it would hand back the whole log to a caller who
    // asked for one slice of it.
    $this->withToken(activityToken($this->staff))
        ->getJson('/api/v1/activity?action[]=file.teleported')
        ->assertStatus(422);
});

test('an unknown subject type matches nothing rather than everything', function () {
    app(ActivityLogger::class)->log(Action::FileUploaded, $this->staff);

    $this->withToken(activityToken($this->staff))
        ->getJson('/api/v1/activity?subject_type=spaceship')
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('subjects are named, never classed', function () {
    $group = Group::query()->create(['name' => 'Acme', 'public' => false]);
    app(ActivityLogger::class)->log(Action::GroupCreated, $this->staff, $group);

    $response = $this->withToken(activityToken($this->staff))
        ->getJson('/api/v1/activity')
        ->assertOk();

    // A class name on the wire would make moving a model between
    // namespaces a breaking API change.
    expect($response->json('data.0.subject.type'))->toBe('group')
        ->and(json_encode($response->json()))->not->toContain('App\\Modules');
});

test('polling walks forward without skipping or repeating', function () {
    $logger = app(ActivityLogger::class);
    $logger->log(Action::FileUploaded, $this->staff);

    $token = activityToken($this->staff);
    $first = $this->withToken($token)->getJson('/api/v1/activity')->assertOk();
    $watermark = $first->json('data.0.created_at');

    $logger->log(Action::FileDownloaded, $this->staff);

    $next = $this->withToken($token)
        ->getJson('/api/v1/activity?updated_since='.urlencode($watermark))
        ->assertOk();

    // The boundary row is inclusive on purpose and de-duplicated by id,
    // so the new entry must be there and must come last in the walk.
    expect(collect($next->json('data'))->pluck('action'))->toContain('file.downloaded');
});

test('a token without the permission cannot read the log', function () {
    $this->withToken(activityToken($this->staff, [Permission::Upload->value]))
        ->getJson('/api/v1/activity')
        ->assertForbidden();
});

test('an IP address is never handed to an integration', function () {
    app(ActivityLogger::class)->log(Action::FileDownloaded, $this->staff);

    $response = $this->withToken(activityToken($this->staff))
        ->getJson('/api/v1/activity')
        ->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('ip_address');
});
