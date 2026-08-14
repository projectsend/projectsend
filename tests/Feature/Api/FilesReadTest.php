<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->token = $this->admin->createToken('t', [
        Permission::Upload->value,
        Permission::EditFiles->value,
        Permission::EditOthersFiles->value,
    ])->plainTextToken;
});

test('the listing returns files with an explicit field set', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Quarterly report']);

    $response = $this->withToken($this->token)->getJson('/api/v1/files')->assertOk();

    $response->assertJsonPath('data.0.id', $file->id)
        ->assertJsonPath('data.0.name', 'Quarterly report')
        ->assertJsonPath('data.0.mime_type', $file->mime_type);

    // Storage layout must never leave the server: a caller downloads
    // through the download endpoint, and publishing where the bytes sit is
    // the map you would want in order to reach them another way.
    $body = $response->json('data.0');
    expect($body)->not->toHaveKey('path')
        ->and($body)->not->toHaveKey('disk');
});

test('no response ever carries credential columns', function () {
    File::factory()->create(['uploaded_by' => $this->admin->id]);

    $bodies = [
        $this->withToken($this->token)->getJson('/api/v1/files')->getContent(),
        $this->withToken($this->token)->getJson('/api/v1/me')->getContent(),
    ];

    foreach ($bodies as $body) {
        expect($body)->not->toContain('password')
            ->and($body)->not->toContain('two_factor_secret')
            ->and($body)->not->toContain('two_factor_recovery_codes')
            ->and($body)->not->toContain('remember_token');
    }
});

test('a token without any view ability cannot list files', function () {
    $limited = staffWithPermissions([Permission::ViewNews->value]);
    $token = $limited->createToken('t', [Permission::ViewNews->value])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/files')->assertForbidden();
});

/*
 * The listing must be built from ViewableFileScope, never File::query().
 * A client-scoped staff member sees their own uploads plus their assigned
 * clients' files — and nothing else — exactly as in the UI.
 */
test('a client-scoped staff token sees only its own scope', function () {
    $client = User::factory()->client()->create();
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([$client->id]);

    $own = File::factory()->create(['uploaded_by' => $manager->id, 'name' => 'mine']);
    $unrelated = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'not mine']);

    $shared = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'shared with my client']);
    $this->actingAs($this->admin)->post("/files/{$shared->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $token = $manager->createToken('t', [Permission::Upload->value])->plainTextToken;

    $ids = $this->withToken($token)->getJson('/api/v1/files')->assertOk()->json('data.*.id');

    expect($ids)->toContain($own->id)
        ->and($ids)->toContain($shared->id)
        ->and($ids)->not->toContain($unrelated->id);

    // And direct access respects the same boundary.
    $this->withToken($token)->getJson("/api/v1/files/{$unrelated->id}")->assertForbidden();
});

test('filters narrow the listing', function () {
    $category = Category::query()->create(['name' => 'Invoices']);
    $other = User::factory()->create();

    $match = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'invoice march']);
    $match->categories()->attach($category->id);

    $byOther = File::factory()->create(['uploaded_by' => $other->id, 'name' => 'someone elses']);
    $expired = File::factory()->create(['uploaded_by' => $this->admin->id, 'expires_at' => now()->subDay()]);

    $ids = fn (string $query) => $this->withToken($this->token)->getJson("/api/v1/files?{$query}")->assertOk()->json('data.*.id');

    expect($ids('search=invoice'))->toBe([$match->id])
        ->and($ids("category_id={$category->id}"))->toBe([$match->id])
        ->and($ids("uploaded_by={$other->id}"))->toBe([$byOther->id])
        ->and($ids('expired=1'))->toBe([$expired->id])
        ->and($ids('expired=0'))->not->toContain($expired->id);
});

test('a malformed updated_since is rejected rather than ignored', function () {
    // Silently ignoring it would make a polling client re-read the whole
    // library every tick and never find out why.
    $this->withToken($this->token)->getJson('/api/v1/files?updated_since=not-a-date')
        ->assertStatus(422)
        ->assertJsonPath('type', 'validation_failed');
});

test('per_page is capped', function () {
    File::factory()->count(3)->create(['uploaded_by' => $this->admin->id]);

    $max = (int) config('api.pagination.max_per_page');

    $this->withToken($this->token)->getJson('/api/v1/files?per_page='.($max + 1))
        ->assertStatus(422);
});

/*
 * The polling contract: walking with updated_since + cursor must visit
 * every row exactly once, including rows created or touched mid-walk.
 */
test('the updated_since walk returns every file exactly once', function () {
    $since = now()->subMinute();

    foreach (range(1, 7) as $i) {
        File::factory()->create([
            'uploaded_by' => $this->admin->id,
            'name' => "file {$i}",
            'updated_at' => now()->addSeconds($i),
        ]);
    }

    $seen = [];
    $url = '/api/v1/files?per_page=2&updated_since='.urlencode($since->toIso8601String());

    for ($page = 0; $page < 10 && $url !== null; $page++) {
        $body = $this->withToken($this->token)->getJson($url)->assertOk()->json();
        $seen = array_merge($seen, array_column($body['data'], 'id'));
        $url = $body['links']['next'] ?? null;
    }

    expect($seen)->toHaveCount(7)
        ->and(array_unique($seen))->toHaveCount(7)
        // Ascending by updated_at, so a caller can take the last value as
        // the next poll's watermark.
        ->and($seen)->toBe(File::query()->orderBy('updated_at')->orderBy('id')->pluck('id')->all());
});

test('updated_since excludes files older than the watermark', function () {
    $old = File::factory()->create(['uploaded_by' => $this->admin->id, 'updated_at' => now()->subDays(2)]);
    $fresh = File::factory()->create(['uploaded_by' => $this->admin->id, 'updated_at' => now()]);

    $ids = $this->withToken($this->token)
        ->getJson('/api/v1/files?updated_since='.urlencode(now()->subHour()->toIso8601String()))
        ->assertOk()->json('data.*.id');

    expect($ids)->toBe([$fresh->id])
        ->and($ids)->not->toContain($old->id);
});

test('show includes the relations an integration needs', function () {
    $client = User::factory()->client()->create(['name' => 'Acme']);
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    $this->withToken($this->token)->getJson("/api/v1/files/{$file->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $file->id)
        ->assertJsonPath('data.uploaded_by.name', $this->admin->name)
        ->assertJsonPath('data.assignments.0.type', 'client')
        ->assertJsonPath('data.assignments.0.name', 'Acme');
});

test('downloading over the API authorizes and audits like the web route', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->withToken($this->token)->get("/api/v1/files/{$file->id}/download")
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path);

    expect(ActivityLog::query()
        ->where('action', Action::FileDownloaded)
        ->where('subject_id', $file->id)
        ->exists())->toBeTrue();
});

test('a token cannot download a file outside its scope', function () {
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $token = $manager->createToken('t', [Permission::Upload->value])->plainTextToken;

    $unrelated = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->withToken($token)->getJson("/api/v1/files/{$unrelated->id}/download")->assertForbidden();
});
