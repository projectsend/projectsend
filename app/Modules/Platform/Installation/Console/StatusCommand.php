<?php

declare(strict_types=1);

namespace App\Modules\Platform\Installation\Console;

use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Identity\TwoFactor\TwoFactorEnforcement;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Installation\Events\ResolvingInstallationStatus;
use App\Modules\Platform\Seats\SeatAllowance;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * What this installation is, as a fact rather than a screen.
 *
 * Written for whatever watches a managed installation from outside the
 * container. Everything here is already visible to any signed-in
 * administrator — a version, an edition, which capabilities the edition
 * grants, how many accounts exist against how many are allowed. Nothing
 * is a secret and nothing is a credential.
 *
 * ### Why a command and not a shell one-liner
 *
 * A reconciler that observes tenants has to be able to say it never sends
 * instructions, only reads state. `docker exec … php -r '…'` is an
 * instruction with the caller's argv in it, however harmless the argv;
 * a named command is an observation, the same kind of thing as reading a
 * directory size. The distinction is the whole reason this exists rather
 * than a documented incantation.
 *
 * ### The counts are the enforcing code's own
 *
 * `used` comes from SeatAllowance, which is what refuses the account past
 * the limit. Two counts that merely agree will diverge eventually — over
 * an inactive account, or a soft-deleted one — and the divergence looks
 * like a billing fault rather than a counting one. So there is one
 * definition and this reads it.
 *
 * ### Is anybody there
 *
 * `activity.last_staff_login_at` answers the one question a platform
 * cannot answer from outside: whether a human still uses this
 * installation. It is a timestamp and nothing else — no name, no address,
 * no session. Only interactive sign-ins reach it, because that is all
 * Laravel's Login event fires for: an integration polling the API every
 * hour must not make a dormant installation look busy.
 *
 * Derived from the activity log rather than denormalised onto `users`. A
 * column would need a migration, a listener change and a backfill to save
 * one indexed MAX() over a table that is small on exactly the
 * installations anybody asks this about. The log is never pruned, and
 * erasure anonymises entries rather than deleting them (`actor_type`
 * survives on purpose — see AccountEraser), so the answer does not change
 * when the person who gave it is forgotten.
 *
 * ### Storage is the application's number, not the disk's
 *
 * `storage.bytes` is what this installation holds, summed from the rows
 * that record it. Measuring the directory instead was correct until
 * external storage went live, and silently stopped being: an upload that
 * resolves to a bucket leaves nothing on the volume to measure, so a
 * figure taken from the filesystem freezes while the account keeps
 * filling. `by_disk` is the same sum split by where the bytes went, which
 * is the only way to see what is still sitting on local disk from before
 * a cutover.
 *
 * Trashed files are excluded because they hold no bytes: File's `deleted`
 * hook removes them, so a soft-deleted row is a record of something that
 * is gone rather than something still costing anything.
 *
 * ### Health is what a container cannot show from outside
 *
 * A tenant's queue worker dying is invisible to anything watching the
 * container: it is still up, and zips quietly stop building while mail
 * stops going out. Same for migrations that failed after a deploy — the
 * application answers every request and is a schema behind. Neither is a
 * secret; both are already visible to anyone who can open the database,
 * which is anyone who can run this command.
 *
 * ### What core cannot answer
 *
 * `modules` is filled by whatever packages are installed, through
 * ResolvingInstallationStatus. A platform that provisioned a bucket knows
 * what it asked for; only the installation knows what loaded.
 */
class StatusCommand extends Command
{
    protected $signature = 'projectsend:status {--json : Emit machine-readable JSON on stdout}';

    protected $description = 'Report this installation\'s version, edition, capabilities and seat usage';

    public function handle(CapabilityRegistry $capabilities, SeatAllowance $seats, Settings $settings): int
    {
        $status = [
            'version' => (string) config('projectsend.version'),
            'edition' => $capabilities->edition()->value,
            'capabilities' => $capabilities->enabledKeys(),
            'seats' => [
                'staff' => [
                    'used' => $seats->staffUsed(),
                    // null is unlimited, and is emitted as null rather than
                    // as 0 or as an absent key: a reader that mistook one
                    // for the other would report an installation selling
                    // unlimited accounts as one that may hold none.
                    'limit' => $seats->staffLimit(),
                ],
                'clients' => [
                    'used' => $seats->clientUsed(),
                    'limit' => $seats->clientLimit(),
                ],
            ],
            'activity' => [
                // Null means "no staff account has ever signed in here",
                // and is emitted rather than left out for the same reason
                // an unlimited seat count is: a watcher has to be able to
                // tell that apart from "we got no answer". Collapsing the
                // two is how a broken probe reads as a dormant fleet.
                'last_staff_login_at' => $this->lastStaffLoginAt(),
            ],
            'storage' => $this->storage(),
            'health' => $this->health(),
            'settings' => [
                // Echoed back rather than assumed: an operator writes the
                // environment variable, and this is the installation
                // saying what it actually applied. Read the way
                // EnforceTwoFactor reads it, down to what an unreadable
                // value falls back to -- reporting a stricter answer than
                // the middleware enforces would be worse than reporting
                // none at all.
                'two_factor_enforcement' => $this->enforcement($settings),
            ],
            // Cast so an installation with no packages emits {} rather
            // than [] -- an empty PHP array encodes as a list, and a
            // reader unmarshalling a map breaks on the day it happens to
            // be empty rather than on the day it is written.
            'modules' => (object) $this->modules(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line("ProjectSend {$status['version']} ({$status['edition']})");
        $this->line('Capabilities: '.(implode(', ', $status['capabilities']) ?: 'none'));
        $this->line('Staff seats:  '.$this->seatLine($status['seats']['staff']));
        $this->line('Clients:      '.$this->seatLine($status['seats']['clients']));
        $this->line('Last staff login: '.($status['activity']['last_staff_login_at'] ?? 'never'));
        $this->line('Storage:      '.number_format($status['storage']['bytes']).' bytes in '.$status['storage']['files'].' files');
        $this->line('Health:       '.$status['health']['pending_migrations'].' migrations pending, '
            .$status['health']['failed_jobs'].' failed jobs, '
            .array_sum(array_filter($status['health']['queues'], 'is_int')).' queued');

        return self::SUCCESS;
    }

    private function enforcement(Settings $settings): string
    {
        $value = $settings->get(Setting::TwoFactorEnforcement);

        $enforcement = (is_string($value) ? TwoFactorEnforcement::tryFrom($value) : null)
            ?? TwoFactorEnforcement::None;

        return $enforcement->value;
    }

    /**
     * What this installation holds, from the rows that record it.
     *
     * @return array{bytes: int, files: int, by_disk: object}
     */
    private function storage(): array
    {
        $perDisk = File::query()
            ->groupBy('disk')
            ->selectRaw('disk, sum(size) as bytes, count(*) as files')
            ->get();

        return [
            'bytes' => (int) $perDisk->sum(fn (File $row): int => (int) $row->getAttribute('bytes')),
            'files' => (int) $perDisk->sum(fn (File $row): int => (int) $row->getAttribute('files')),
            // Keyed by disk name rather than a list, because the reader
            // wants one of them by name — "how much is still local" — and
            // not to walk a list looking for it.
            // Same reason as `modules`: an installation holding no files
            // at all must still answer with a map.
            'by_disk' => (object) $perDisk
                ->mapWithKeys(fn (File $row): array => [
                    (string) $row->getAttribute('disk') => [
                        'bytes' => (int) $row->getAttribute('bytes'),
                        'files' => (int) $row->getAttribute('files'),
                    ],
                ])->all(),
        ];
    }

    /**
     * @return array{pending_migrations: int, failed_jobs: int, queues: array<string, int|null>}
     */
    private function health(): array
    {
        return [
            'pending_migrations' => $this->pendingMigrations(),
            'failed_jobs' => $this->failedJobs(),
            // The two this application actually runs workers for. A depth
            // is not a fault on its own -- a busy installation has one --
            // but a depth that only ever grows is a worker that died, and
            // nothing outside the container can see the difference.
            'queues' => [
                'default' => $this->queueDepth('default'),
                'zips' => $this->queueDepth('zips'),
            ],
        ];
    }

    private function pendingMigrations(): int
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        // Every path, not just database/migrations: a package registers
        // its own, and a package migration left unrun is exactly the kind
        // of half-deploy this is here to report.
        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));

        return count(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
    }

    private function failedJobs(): int
    {
        $table = config('queue.failed.table');

        if (! is_string($table) || $table === '') {
            return 0;
        }

        return DB::table($table)->count();
    }

    /**
     * Null rather than a crash when the queue cannot be reached, and null
     * rather than zero: an unreachable Redis is not an empty queue, and a
     * reader watching for a worker that died would read the second as
     * everything being fine.
     *
     * This command is a probe, and a probe that dies on one unreachable
     * dependency tells the reader nothing about the facts it could still
     * have answered.
     */
    private function queueDepth(string $queue): ?int
    {
        try {
            return Queue::size($queue);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    private function modules(): array
    {
        $event = new ResolvingInstallationStatus;

        Event::dispatch($event);

        return $event->facts;
    }

    /**
     * The most recent interactive staff sign-in, or null if there has
     * never been one.
     */
    private function lastStaffLoginAt(): ?string
    {
        $latest = ActivityLog::query()
            ->where('action', Action::Login->value)
            ->where('actor_type', UserType::Staff->value)
            ->max('created_at');

        // `action` and `actor_type` carry an index each, so this narrows
        // on one of them rather than reading the log.
        return $latest === null ? null : Carbon::parse($latest)->toIso8601String();
    }

    /**
     * @param  array{used: int, limit: int|null}  $seat
     */
    private function seatLine(array $seat): string
    {
        return $seat['limit'] === null
            ? "{$seat['used']} of unlimited"
            : "{$seat['used']} of {$seat['limit']}";
    }
}
