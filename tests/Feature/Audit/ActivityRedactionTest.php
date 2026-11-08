<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;

/*
 * Seeing a log entry is not being told everybody in it. A file in a
 * client-scoped manager's library can also reach a client off their
 * roster -- here through a group that holds clients of both -- and that
 * client downloading it, or being shared it, writes entries about a file
 * the manager may see. The entry stays; the stranger's name, address and
 * share target go, on every surface that shows the log.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $this->mine = User::factory()->client()->create(['name' => 'Roster Client']);
    $this->stranger = User::factory()->client()->create(['name' => 'Stranger Corp']);
    $this->manager = User::factory()->role(SystemRole::ClientManager)->create();
    $this->manager->assignedClients()->sync([$this->mine->id]);

    $group = Group::query()->create(['name' => 'Both', 'public' => false]);
    $group->members()->attach([$this->mine->id, $this->stranger->id]);

    $this->file = File::factory()->create([
        'uploaded_by' => $this->admin->id, 'name' => 'shared-report', 'original_name' => 'r.pdf',
        'mime_type' => 'application/pdf', 'size' => 10,
    ]);
    $this->actingAs($this->admin)->post("/files/{$this->file->id}/assignments", ['type' => 'group', 'id' => $group->id]);
    $this->actingAs($this->admin)->post("/files/{$this->file->id}/assignments", ['type' => 'client', 'id' => $this->stranger->id]);

    ActivityLog::query()->create([
        'action' => Action::FileDownloaded,
        'subject_type' => $this->file->getMorphClass(),
        'subject_id' => $this->file->id,
        'subject_name' => $this->file->name,
        'actor_id' => $this->stranger->id,
        'actor_name' => $this->stranger->name,
        'actor_type' => 'client',
        'ip_address' => '203.0.113.9',
        'created_at' => now(),
    ]);
});

function redactionBody(\Illuminate\Testing\TestResponse $response): string
{
    $response->assertOk();

    if ($response->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
        return $response->streamedContent();
    }

    if ($response->baseResponse instanceof \Illuminate\Http\JsonResponse) {
        return (string) $response->getContent();
    }

    return (string) json_encode($response->viewData('page'));
}

test('a client-scoped manager reads entries about their library without the off-roster client in them', function () {
    $fileId = $this->file->id;

    $surfaces = [
        '/activity',
        '/activity/export',
        '/downloads',
        '/dashboard',
        "/files/{$fileId}/activity",
        "/files/{$fileId}/activity/history",
        "/files/{$fileId}/access",
        "/files/{$fileId}/downloads",
        "/files/{$fileId}/downloads/history",
    ];

    foreach ($surfaces as $url) {
        $body = redactionBody($this->actingAs($this->manager)->get($url));

        expect($body)->not->toContain('Stranger Corp', "{$url} names the stranger")
            ->and($body)->not->toContain('203.0.113.9', "{$url} gives the stranger's address");
    }

    // The entries themselves are still there.
    $downloads = json_decode((string) json_encode($this->actingAs($this->manager)->get('/downloads')->viewData('page')), true);
    expect(collect($downloads['props']['entries'])->pluck('actor_name'))->toContain('A client');

    $token = $this->manager->createToken('feed', ['view_actions_log'])->plainTextToken;
    $api = $this->withToken($token)->getJson('/api/v1/activity')->assertOk();
    expect((string) $api->getContent())->not->toContain('Stranger Corp');
});

test('the actor filter cannot be used to spell out a hidden name', function () {
    $page = json_decode((string) json_encode(
        $this->actingAs($this->manager)->get('/downloads?user=Stranger')->viewData('page')
    ), true);
    expect($page['props']['entries'])->toBe([]);

    $page = json_decode((string) json_encode(
        $this->actingAs($this->manager)->get('/activity?actor=Stranger')->viewData('page')
    ), true);
    expect($page['props']['entries'])->toBe([]);
});

test('an unscoped viewer still reads every name', function () {
    $body = redactionBody($this->actingAs($this->admin)->get('/downloads'));
    expect($body)->toContain('Stranger Corp')->toContain('203.0.113.9');

    $activity = redactionBody($this->actingAs($this->admin)->get('/activity'));
    expect($activity)->toContain('Stranger Corp');
});

test('the manager still reads the names of their own clients and groups', function () {
    $activity = redactionBody($this->actingAs($this->manager)->get('/activity'));

    expect($activity)->toContain('Both');
});

test('the API usage page reads the same entries without the off-roster client in them', function () {
    ActivityLog::query()->create([
        'action' => Action::FileAssigned,
        'subject_type' => $this->file->getMorphClass(),
        'subject_id' => $this->file->id,
        'subject_name' => $this->file->name,
        'actor_id' => $this->admin->id,
        'actor_name' => $this->admin->name,
        'actor_type' => 'staff',
        'origin' => \App\Modules\Audit\ActivityOrigin::Api,
        'context' => ['target' => 'Stranger Corp', 'target_type' => 'client', 'target_id' => $this->stranger->id],
        'created_at' => now(),
    ]);

    $body = redactionBody($this->actingAs($this->manager)->get('/api?all=1'));

    expect($body)->toContain('shared-report')->not->toContain('Stranger Corp');
});
