<?php

declare(strict_types=1);

namespace App\Modules\Files;

use Illuminate\Support\Facades\Cache;

/**
 * The one background "import all" run on the orphans screen: how far it
 * has got, and whether it is still going. Kept in the cache rather than a
 * table: it is a progress readout for whoever is watching, not a record
 * (every imported file is already in the activity log).
 *
 * There is only ever one run. Two at once would each scan the same disk
 * and adopt the same paths twice, so a new one is refused while the
 * current one is active.
 *
 * @phpstan-type Run array{status: string, total: int, imported: int, error: ?string, started_at: int, updated_at: int}
 */
class OrphanImportProgress
{
    private const KEY = 'orphan-files:import-run';

    /** A run kept for a day, so its outcome is still there after lunch. */
    private const TTL_SECONDS = 86400;

    /**
     * Each chunk of the job runs for under a minute, and every imported
     * file touches updated_at. A run silent for this long has no worker
     * behind it (crashed, or no worker listening), and must not block
     * the next one forever.
     */
    private const STALL_SECONDS = 300;

    /**
     * Starts a run unless one is active, under a lock so two people
     * clicking "Import all" at the same moment cannot both get one.
     */
    public function tryStart(int $total): bool
    {
        return (bool) Cache::lock(self::KEY.':start', 10)->get(function () use ($total): bool {
            if ($this->isActive()) {
                return false;
            }

            $this->put([
                'status' => 'running',
                'total' => $total,
                'imported' => 0,
                'error' => null,
                'started_at' => now()->getTimestamp(),
            ]);

            return true;
        });
    }

    public function advance(): void
    {
        $run = $this->raw();

        if ($run !== null) {
            $run['imported']++;
            $this->put($run);
        }
    }

    public function finish(): void
    {
        $this->end('finished');
    }

    public function fail(string $error): void
    {
        $this->end('failed', $error);
    }

    public function isActive(): bool
    {
        return ($this->current()['status'] ?? null) === 'running';
    }

    /**
     * The run as the page shows it. A running run that has gone quiet is
     * reported as stalled.
     *
     * @return array{status: string, total: int, imported: int, error: ?string, started_at: int}|null
     */
    public function current(): ?array
    {
        $run = $this->raw();

        if ($run === null) {
            return null;
        }

        if ($run['status'] === 'running' && now()->getTimestamp() - $run['updated_at'] > self::STALL_SECONDS) {
            $run['status'] = 'stalled';
        }

        unset($run['updated_at']);

        return $run;
    }

    private function end(string $status, ?string $error = null): void
    {
        $run = $this->raw();

        if ($run !== null) {
            $this->put([...$run, 'status' => $status, 'error' => $error]);
        }
    }

    /**
     * @return Run|null
     */
    private function raw(): ?array
    {
        return Cache::get(self::KEY);
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function put(array $run): void
    {
        Cache::put(self::KEY, [...$run, 'updated_at' => now()->getTimestamp()], self::TTL_SECONDS);
    }
}
