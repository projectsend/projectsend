<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Scheduling\ScheduledTaskRun;
use App\Modules\Platform\Scheduling\TaskRunStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    config()->set('projectsend.edition', Edition::Community);
    $this->admin = User::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function schedulerPageProps(TestResponse $response): array
{
    return json_decode(json_encode($response->viewData('page')), true)['props'];
}

test('the scheduler page lists every known command, flagging ones that have never run', function () {
    ScheduledTaskRun::query()->create([
        'command' => 'projectsend:purge-expired-files',
        'status' => TaskRunStatus::Success,
        'message' => null,
        'duration_ms' => 42,
        'ran_at' => now(),
    ]);
    ScheduledTaskRun::query()->create([
        'command' => 'projectsend:fetch-news',
        'status' => TaskRunStatus::Failed,
        'message' => 'Scheduled command [...] failed with exit code [1].',
        'duration_ms' => null,
        'ran_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)->get('/system/settings/scheduler');
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('system/settings/scheduler')->has('tasks', 7));

    $tasks = collect(schedulerPageProps($response)['tasks'])->keyBy('command');
    expect($tasks->get('projectsend:purge-expired-files')['status'])->toBe('success')
        ->and($tasks->get('projectsend:purge-expired-files')['duration_ms'])->toBe(42)
        ->and($tasks->get('projectsend:fetch-news')['status'])->toBe('failed')
        ->and($tasks->get('projectsend:fetch-news')['message'])->toContain('exit code [1]')
        ->and($tasks->get('projectsend:purge-orphan-files')['status'])->toBeNull();
});

function makeFailedJob(string $uuid, string $connection = 'database'): void
{
    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => $connection,
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Fake']),
        'exception' => "RuntimeException: boom\n#0 stack trace line",
        'failed_at' => now(),
    ]);
}

test('failed jobs are listed with a pending-jobs count', function () {
    $uuid = (string) Str::uuid();
    makeFailedJob($uuid);
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Fake']),
        'attempts' => 0,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    $response = $this->actingAs($this->admin)->get('/system/settings/scheduler');
    $props = schedulerPageProps($response);

    expect($props['pending_jobs_count'])->toBe(1)
        ->and($props['failed_jobs'])->toHaveCount(1)
        ->and($props['failed_jobs'][0]['id'])->toBe($uuid)
        ->and($props['failed_jobs'][0]['exception'])->toBe('RuntimeException: boom');
});

test('delete forgets a failed job', function () {
    $uuid = (string) Str::uuid();
    makeFailedJob($uuid);

    $this->actingAs($this->admin)->delete("/system/settings/scheduler/failed-jobs/{$uuid}")->assertRedirect();

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse();
});

test('the page opens on the tasks tab, and ?tab=failed selects the failed-jobs tab', function () {
    $this->actingAs($this->admin)->get('/system/settings/scheduler')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tab', 'tasks'));

    $this->actingAs($this->admin)->get('/system/settings/scheduler?tab=failed')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tab', 'failed'));
});

test('failed jobs are paginated', function () {
    for ($i = 0; $i < 25; $i++) {
        makeFailedJob((string) Str::uuid());
    }

    $first = schedulerPageProps($this->actingAs($this->admin)->get('/system/settings/scheduler?tab=failed'));
    expect($first['failed_jobs'])->toHaveCount(20)
        ->and($first['failed_total'])->toBe(25)
        ->and($first['failed_pagination']['last_page'])->toBe(2)
        ->and($first['failed_pagination']['page'])->toBe(1);

    $second = schedulerPageProps($this->actingAs($this->admin)->get('/system/settings/scheduler?tab=failed&page=2'));
    expect($second['failed_jobs'])->toHaveCount(5)
        ->and($second['failed_pagination']['page'])->toBe(2);
});

test('a page past the end redirects to the failed tab last page', function () {
    makeFailedJob((string) Str::uuid());

    $this->actingAs($this->admin)->get('/system/settings/scheduler?tab=failed&page=9')
        ->assertRedirect('/system/settings/scheduler?tab=failed');
});

test('delete all forgets every failed job', function () {
    makeFailedJob((string) Str::uuid());
    makeFailedJob((string) Str::uuid());
    makeFailedJob((string) Str::uuid());

    $this->actingAs($this->admin)->delete('/system/settings/scheduler/failed-jobs')->assertRedirect();

    expect(DB::table('failed_jobs')->count())->toBe(0);
});

test('retry re-queues the job onto its original connection and forgets the failed row', function () {
    // The 'database' connection (config/queue.php) writes back into the
    // 'jobs' table via the app's normal DB connection — safe to exercise
    // for real in tests, unlike 'redis', which needs a live server.
    $uuid = (string) Str::uuid();
    makeFailedJob($uuid, 'database');

    $this->actingAs($this->admin)->post("/system/settings/scheduler/failed-jobs/{$uuid}/retry")->assertRedirect();

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
        ->and(DB::table('jobs')->count())->toBe(1);
});

test('the whole surface is unavailable on the cloud edition', function () {
    config()->set('projectsend.edition', Edition::Cloud);

    $this->actingAs($this->admin)->get('/system/settings/scheduler')->assertNotFound();
});

test('clients cannot access the scheduler page', function () {
    $this->admin; // setup complete

    $this->actingAs(User::factory()->client()->create())
        ->get('/system/settings/scheduler')
        ->assertRedirect(route('dashboard'));
});

test('staff without edit_settings cannot access the scheduler page', function () {
    $role = Role::query()->create(['name' => 'No Settings', 'is_administrator' => false, 'is_system' => false]);
    $staffer = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staffer)->get('/system/settings/scheduler')->assertForbidden();
});
