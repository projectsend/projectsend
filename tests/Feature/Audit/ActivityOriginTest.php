<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Audit\ActivityOrigin;
use App\Modules\Audit\Events\ResolvingActivityOrigin;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Event;
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

/*
|--------------------------------------------------------------------------
| Credentials core does not know about
|--------------------------------------------------------------------------
|
| "No personal access token" has always meant "a browser session", and
| that stops being true the moment anything else can authenticate a
| request — the AI connector in projectsend/cloud-modules is the first.
| ActivityOrigin is closed, so core publishes both the case and the hook;
| without them a connector's actions would be recorded as a person
| clicking, which is the one thing this column exists not to get wrong.
|
*/

test('a listener can say a request was not a browser after all', function () {
    Event::listen(ResolvingActivityOrigin::class, function (ResolvingActivityOrigin $asking): void {
        $asking->origin = ActivityOrigin::Mcp;
        $asking->credentialName = 'Claude';
    });

    $file = File::factory()->create(['uploaded_by' => $this->staff->id]);

    $this->actingAs($this->staff)->patch("/files/{$file->id}", ['name' => 'Renamed']);

    $entry = ActivityLog::query()->where('action', Action::FileUpdated)->latest('id')->firstOrFail();

    expect($entry->origin)->toBe(ActivityOrigin::Mcp)
        ->and($entry->api_token_name)->toBe('Claude')
        // The column means a row in personal_access_tokens, and this is
        // not one. Naming it alone is the whole point of the snapshot.
        ->and($entry->api_token_id)->toBeNull()
        // The person authorised it, so the person is the actor. An audit
        // trail naming the assistant instead would lose the only fact
        // that matters when something unexpected shows up.
        ->and($entry->actor_id)->toBe($this->staff->id);
});

test('a listener that recognises nothing leaves the answer alone', function () {
    // What every stock installation does, since nothing listens at all.
    Event::listen(ResolvingActivityOrigin::class, function (ResolvingActivityOrigin $asking): void {
        // Looked, did not recognise the credential, said nothing.
    });

    $file = File::factory()->create(['uploaded_by' => $this->staff->id]);

    $this->actingAs($this->staff)->patch("/files/{$file->id}", ['name' => 'Renamed']);

    $entry = ActivityLog::query()->where('action', Action::FileUpdated)->latest('id')->firstOrFail();

    expect($entry->origin)->toBe(ActivityOrigin::Ui)
        ->and($entry->api_token_name)->toBeNull();
});

test('a token holder is never offered to a listener', function () {
    // A request carrying a personal access token is the API and is in no
    // doubt, so a listener never gets the chance to relabel it. Otherwise
    // one package could quietly rewrite how every integration's actions
    // are attributed.
    $asked = false;

    Event::listen(ResolvingActivityOrigin::class, function () use (&$asked): void {
        $asked = true;
    });

    $created = $this->staff->createToken('Zapier', [Permission::EditFiles->value]);
    $file = File::factory()->create(['uploaded_by' => $this->staff->id]);

    $this->withToken($created->plainTextToken)
        ->patchJson("/api/v1/files/{$file->id}", ['name' => 'Renamed']);

    $entry = ActivityLog::query()->where('action', Action::FileUpdated)->latest('id')->firstOrFail();

    expect($asked)->toBeFalse()
        ->and($entry->origin)->toBe(ActivityOrigin::Api)
        ->and($entry->api_token_name)->toBe('Zapier');
});

test('nobody signed in is never offered to a listener either', function () {
    $asked = false;

    Event::listen(ResolvingActivityOrigin::class, function () use (&$asked): void {
        $asked = true;
    });

    app(ActivityLogger::class)->log(Action::UserCreated);

    expect($asked)->toBeFalse();
});

test('the AI assistant origin is not offered on an edition that cannot produce it', function () {
    // The suite runs as the community edition. Offering a filter that
    // could only ever return nothing would dangle a feature this edition
    // does not have — the one thing the edition boundary exists not to do.
    $response = $this->actingAs($this->staff)->get('/activity')->assertOk();

    $offered = collect($response->viewData('page')['props']['origins'])->pluck('key');

    expect($offered)->not->toContain(ActivityOrigin::Mcp->value)
        ->and($offered)->toContain(ActivityOrigin::Ui->value, ActivityOrigin::Api->value);
});
