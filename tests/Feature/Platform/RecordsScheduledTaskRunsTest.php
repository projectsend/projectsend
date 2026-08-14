<?php

declare(strict_types=1);

use App\Modules\Platform\Scheduling\RecordsScheduledTaskRuns;
use App\Modules\Platform\Scheduling\ScheduledTaskRun;
use App\Modules\Platform\Scheduling\TaskRunStatus;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\EventMutex;

/**
 * Not a full `artisan schedule:run` integration test — that spawns real
 * subprocess `php artisan ...` children that can't see this test's
 * `:memory:` SQLite connection. Instead, exercises the listener directly
 * against real Illuminate\Console\Events objects, the same shape
 * ScheduleRunCommand actually dispatches (verified against its vendor
 * source before writing this feature).
 *
 * EventMutex isn't container-bound outside a real console boot, so a
 * trivial no-op stub stands in — Event's constructor just needs something
 * implementing the interface, nothing in these tests exercises locking.
 */
function fakeScheduledEvent(string $rawCommand): ScheduledEvent
{
    $mutex = new class implements EventMutex
    {
        public function create($event): bool
        {
            return true;
        }

        public function exists($event): bool
        {
            return false;
        }

        public function forget($event): void {}
    };

    return new ScheduledEvent($mutex, $rawCommand);
}

test('a finished task is recorded as succeeded', function () {
    $listener = app(RecordsScheduledTaskRuns::class);
    $task = fakeScheduledEvent("'/usr/bin/php' 'artisan' projectsend:purge-expired-files > '/dev/null' 2>&1");

    $listener->onFinished(new ScheduledTaskFinished($task, 1.23));

    $run = ScheduledTaskRun::query()->where('command', 'projectsend:purge-expired-files')->sole();
    expect($run->status)->toBe(TaskRunStatus::Success)
        ->and($run->message)->toBeNull()
        ->and($run->duration_ms)->toBe(1230);
});

test('a failed task is recorded as failed, overwriting a prior success', function () {
    $listener = app(RecordsScheduledTaskRuns::class);
    $task = fakeScheduledEvent("'/usr/bin/php' 'artisan' projectsend:fetch-news > '/dev/null' 2>&1");

    // ScheduleRunCommand always dispatches Finished first, then Failed
    // only if the exit code was non-zero — replicate that exact order.
    $listener->onFinished(new ScheduledTaskFinished($task, 0.5));
    $listener->onFailed(new ScheduledTaskFailed($task, new RuntimeException('Scheduled command [...] failed with exit code [1].')));

    $run = ScheduledTaskRun::query()->where('command', 'projectsend:fetch-news')->sole();
    expect($run->status)->toBe(TaskRunStatus::Failed)
        ->and($run->message)->toContain('exit code [1]')
        ->and($run->duration_ms)->toBeNull();
});

test('a non-projectsend command is not tracked', function () {
    $listener = app(RecordsScheduledTaskRuns::class);
    $task = fakeScheduledEvent("'/usr/bin/php' 'artisan' some:other-command");

    $listener->onFinished(new ScheduledTaskFinished($task, 0.1));

    expect(ScheduledTaskRun::query()->count())->toBe(0);
});
