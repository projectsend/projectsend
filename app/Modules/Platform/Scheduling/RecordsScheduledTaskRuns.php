<?php

declare(strict_types=1);

namespace App\Modules\Platform\Scheduling;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;

/**
 * Records the outcome of every projectsend:* scheduled command into
 * ScheduledTaskRun, purely from Laravel's own scheduler lifecycle events —
 * no changes to any command class needed.
 *
 * ScheduleRunCommand dispatches ScheduledTaskFinished unconditionally right
 * after a task runs (even one that's about to be judged a failure), and
 * only afterwards — if the exit code was non-zero — throws and dispatches
 * ScheduledTaskFailed. Two independent upserts, in that guaranteed order,
 * naturally leave a failing run's row as "failed": Finished's "success"
 * write lands first, and Failed's write overwrites it a moment later.
 */
class RecordsScheduledTaskRuns
{
    public function onFinished(ScheduledTaskFinished $event): void
    {
        $this->record($event->task, TaskRunStatus::Success, null, (int) round($event->runtime * 1000));
    }

    public function onFailed(ScheduledTaskFailed $event): void
    {
        $this->record($event->task, TaskRunStatus::Failed, $event->exception->getMessage(), null);
    }

    private function record(ScheduledEvent $task, TaskRunStatus $status, ?string $message, ?int $durationMs): void
    {
        // $task->command is the full shelled-out string (php binary,
        // artisan path, output redirection) — pull out just the artisan
        // signature. Anything that isn't one of our own commands (there
        // are none today, but a future package could schedule its own) is
        // simply not tracked here.
        if (! preg_match('/projectsend:[\w-]+/', (string) $task->command, $matches)) {
            return;
        }

        ScheduledTaskRun::query()->updateOrCreate(
            ['command' => $matches[0]],
            ['status' => $status, 'message' => $message, 'duration_ms' => $durationMs, 'ran_at' => now()],
        );
    }
}
