<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Jobs\BuildZipDownloadJob;
use App\Modules\Files\Models\ZipDownload;
use App\Modules\Files\Queue\StalledZipBuilds;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * The application cannot see its own worker processes, only whether work
 * gets done. These pin the two halves of how it tells "nobody is serving
 * the zips queue" apart from "the archive is big" — because getting that
 * wrong means either a silent breakage or a banner that cries wolf.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
});

function queuedZip(User $requester, ?string $createdAt = null, ?string $startedAt = null, string $status = ZipDownload::STATUS_PENDING): ZipDownload
{
    $row = ZipDownload::query()->create([
        'requested_by' => $requester->id,
        'status' => $status,
    ]);

    $row->forceFill(array_filter([
        'created_at' => $createdAt === null ? null : now()->parse($createdAt),
        'started_at' => $startedAt === null ? null : now()->parse($startedAt),
    ]))->save();

    return $row;
}

test('a build nobody picked up is reported once it has waited', function () {
    queuedZip($this->admin, createdAt: now()->subMinutes(30)->toDateTimeString());

    expect(app(StalledZipBuilds::class)->oldestUnstarted())->not->toBeNull();
});

test('a build that has only just been queued is not reported', function () {
    // Every request would raise the banner for a moment otherwise.
    queuedZip($this->admin, createdAt: now()->subMinute()->toDateTimeString());

    expect(app(StalledZipBuilds::class)->oldestUnstarted())->toBeNull();
});

test('a queue waiting behind a build in progress is a healthy queue', function () {
    // One worker builds one archive at a time, so a row can wait a long
    // while with a perfectly live worker in front of it. This is the case
    // that makes the "unstarted" test alone unusable.
    queuedZip($this->admin, createdAt: now()->subMinutes(30)->toDateTimeString());
    queuedZip($this->admin, createdAt: now()->subMinutes(40)->toDateTimeString(), startedAt: now()->subMinutes(35)->toDateTimeString());

    expect(app(StalledZipBuilds::class)->oldestUnstarted())->toBeNull();
});

test('a build held by a worker that died no longer counts as in progress', function () {
    // Past the job's own timeout it is not running, it is abandoned — and
    // the queue is as unattended as if it had never begun.
    queuedZip($this->admin, createdAt: now()->subHours(4)->toDateTimeString());
    queuedZip($this->admin, createdAt: now()->subHours(5)->toDateTimeString(), startedAt: now()->subHours(4)->toDateTimeString());

    expect(app(StalledZipBuilds::class)->oldestUnstarted())->not->toBeNull();
});

test('a build that finished says nothing about the queue', function () {
    queuedZip($this->admin, createdAt: now()->subDay()->toDateTimeString(), status: ZipDownload::STATUS_READY);
    queuedZip($this->admin, createdAt: now()->subDay()->toDateTimeString(), status: ZipDownload::STATUS_FAILED);

    expect(app(StalledZipBuilds::class)->oldestUnstarted())->toBeNull();
});

test('the banner reaches staff who may read system information', function () {
    queuedZip($this->admin, createdAt: now()->subMinutes(30)->toDateTimeString());

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('worker_notice.waiting_since', fn ($v) => $v !== null),
    );
});

test('and nobody else', function () {
    queuedZip($this->admin, createdAt: now()->subMinutes(30)->toDateTimeString());

    $role = Role::query()->create(['name' => 'No system info', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => 'upload']);
    $staffer = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staffer)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('worker_notice', null),
    );
});

test('a build the job actually ran is stamped as started', function () {
    // The whole mechanism rests on this one write landing before any of
    // the work, so it is worth pinning rather than assuming. Stamped even
    // for a build that goes on to fail, which is the point: it says a
    // worker had it, not that it succeeded.
    Storage::fake('files');

    $row = queuedZip($this->admin);

    (new BuildZipDownloadJob($row->id))->handle();

    $row->refresh();

    expect($row->started_at)->not->toBeNull()
        ->and($row->status)->toBe(ZipDownload::STATUS_FAILED);
});
