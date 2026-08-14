<?php

declare(strict_types=1);

namespace App\Modules\Files\Console;

use App\Modules\Files\Models\File;
use App\Modules\Files\Models\FileAssignment;
use App\Modules\Files\Versions\FileVersions;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Verify — and optionally repair — the two invariants FileVersions owns.
 *
 * Versioning changes who can see a file: a revision holds no recipients of
 * its own and reads the root's instead. That makes a drifted
 * version_root_id an access-control bug, not a cosmetic one, so it gets a
 * command that can be pointed at a real install rather than only a test
 * asserting it in memory.
 */
class CheckFileVersionsCommand extends Command
{
    protected $signature = 'versioning:check {--repair : Fix what can be fixed rather than only reporting it}';

    protected $description = 'Check that file version chains and their inherited sharing are consistent';

    public function handle(FileVersions $versions): int
    {
        $repair = (bool) $this->option('repair');
        $problems = 0;

        // Invariant 1: previous_file_id IS NULL <=> version_root_id IS NULL.
        $mismatched = File::withTrashed()
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w->whereNull('previous_file_id')->whereNotNull('version_root_id'))
                ->orWhere(fn (Builder $w) => $w->whereNotNull('previous_file_id')->whereNull('version_root_id')))
            ->get();

        foreach ($mismatched as $file) {
            $problems++;
            $this->warn("File #{$file->id} has previous_file_id={$file->previous_file_id} but version_root_id={$file->version_root_id}.");

            if ($repair) {
                $versions->recomputeRoots($file->id);
            }
        }

        // Invariant 1b: the stamped root must actually be the head of the chain.
        $stamped = File::withTrashed()->whereNotNull('version_root_id')->get();

        foreach ($stamped as $file) {
            $head = $this->headOf($file);

            if ($head === $file->version_root_id) {
                continue;
            }

            $problems++;
            $this->warn("File #{$file->id} is stamped with root #{$file->version_root_id} but its chain heads at #{$head}.");

            if ($repair) {
                $versions->recomputeRoots($file->id);
            }
        }

        // Invariant 2: a revision owns no assignments of its own. Not
        // repaired automatically — the rows may be the only record of an
        // audience, and merging them into the root would widen it silently.
        $strays = FileAssignment::query()
            ->whereIn('file_id', File::withTrashed()->whereNotNull('version_root_id')->select('id'))
            ->get();

        foreach ($strays->groupBy('file_id') as $fileId => $rows) {
            $problems++;
            $this->warn("File #{$fileId} is a revision but owns {$rows->count()} assignment row(s). Revisions inherit the original's recipients; these are ignored by every visibility query and should be reviewed by hand.");
        }

        if ($problems === 0) {
            $this->info('File version chains are consistent.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error("{$problems} problem(s) found.".($repair ? ' Repairable ones were fixed.' : ' Re-run with --repair to fix what can be fixed automatically.'));

        return self::FAILURE;
    }

    /**
     * Walk back to the head of $file's chain, guarded by MAX_CHAIN so a
     * corrupted cycle reports rather than hangs — which is the case this
     * command exists to catch.
     */
    private function headOf(File $file): ?int
    {
        $cursor = $file;
        $steps = 0;

        while ($cursor->previous_file_id !== null && ++$steps <= FileVersions::MAX_CHAIN) {
            $next = File::withTrashed()->find($cursor->previous_file_id);

            if ($next === null) {
                break;
            }

            $cursor = $next;
        }

        return $cursor->is($file) ? null : $cursor->id;
    }
}
