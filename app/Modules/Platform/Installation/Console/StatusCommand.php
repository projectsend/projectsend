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
use App\Modules\Platform\Scheduling\ScheduledTaskRun;
use App\Modules\Platform\Scheduling\TaskRunStatus;
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
 * **That "never pruned" is now somebody's safety argument.** The hosted
 * platform warns, pauses and finally removes a free instance nobody has
 * signed in to, and this field is what it counts from. Retention or
 * pruning added to `activity_log` would not break anything here — it
 * would quietly make old installations look dormant, and the thing that
 * acts on that reading deletes them. Anyone adding it needs to give this
 * field another source first, not merely check that the tests still
 * pass.
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
 *
 * ### `capabilities` is compared, not displayed
 *
 * The control plane reads this list against the plan it wrote for the
 * tenant — "this instance is on the free plan and still grants branding"
 * is a comparison, not a glance. So the *keys* and their order are a
 * contract: renaming one, or reordering the enum they come from, breaks
 * that comparison while every test here keeps passing. A key that changes
 * meaning needs a new key, not an edit.
 *
 * ### `usage` and `health.scheduler` are charted, so their keys are a promise
 *
 * The hosted platform's customer dashboard plots these over time. That
 * makes the key names a contract in the same way `capabilities` is one,
 * and it fails in a nastier way: a renamed capability key breaks a
 * comparison that somebody is watching, while a renamed `usage` key
 * produces a chart that is silently *empty* rather than an error. Nobody
 * gets paged for a flat line.
 *
 * So: add keys freely, and never rename or repurpose one. A key whose
 * meaning changes needs a new key, not a new value — "downloads" that
 * quietly starts excluding staff is worse than "downloads" disappearing,
 * because the second is noticed.
 *
 * `usage` is a **rolling window, deliberately, and has no lifetime
 * totals**. Not a presentation choice: `activity_log` is never pruned
 * (see above), so a lifetime count over it gets slower every day of the
 * installation's life, while a windowed one stays flat forever. The
 * window is stated in the document as `window_days` rather than assumed
 * by the reader, so changing it is visible to whoever is plotting it.
 *
 * Every count here rides one of the two composite indexes added for it —
 * see the migration adding them, which also records why they have to
 * ship as a pair.
 *
 * ### The scheduler is the one thing nothing else can see
 *
 * `health.queues` catches a dead worker. Nothing catches a dead
 * *scheduler*, and its symptom is not a stalled feature: expired files
 * stop being purged, so content that was supposed to become unreachable
 * stays reachable, and orphans and stale uploads accumulate against a
 * quota nobody is watching. The installation looks completely healthy
 * while it happens, to its operator and to its administrator alike.
 *
 * ### A version is a decision, a commit is a fact
 *
 * `build` says which commit this installation was built from. A version
 * string is chosen by somebody and stamped; two images can carry the same
 * one and different code — an image built from the tag, and one built
 * from the branch that tag sits on. A fleet spent a day reporting "2.2.0"
 * from images that were not the released 2.2.0, and nothing inside any of
 * them could have said so.
 *
 * Null on a source checkout, all four fields, because `config/build.php`
 * is written by build-release.sh and a checkout is not a build. That is
 * the honest answer rather than a missing one: "I was not built" and "I
 * will not say" are different, and only the first is true here.
 */
class StatusCommand extends Command
{
    /**
     * The rolling window every `usage` count is measured over.
     *
     * Emitted in the document as `window_days` rather than left for the
     * reader to know, because a number that is charted and a number that
     * is assumed diverge exactly once and silently.
     */
    private const USAGE_WINDOW_DAYS = 30;

    /**
     * The actions `usage.actions` counts, and the whole of it.
     *
     * An allowlist rather than a `group by action`, for two reasons that
     * happen to agree. Privacy: this document leaves the installation, and
     * cases land in Action most weeks — an open group-by would start
     * shipping new action names outward with nobody having decided that
     * they should go, and some of them (`account.erased`,
     * `two_factor.reset`, `password.updated`) are somebody's compliance
     * event rather than a business metric. Cost: five keyed counts measure
     * ~30x cheaper than one `group by action` over the same window,
     * because each rides (action, created_at) while the group-by starts
     * from created_at and reads rows.
     *
     * These five answer "is my library growing, are people being added, is
     * anything being shared" and nothing else. Uploads and downloads are
     * their own fields; none of these names a person.
     *
     * @var list<Action>
     */
    private const USAGE_ACTIONS = [
        Action::UserCreated,
        Action::ClientSelfRegistered,
        Action::FileAssigned,
        Action::ShareLinkCreated,
        Action::GroupCreated,
    ];

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
                'last_staff_login_at' => $this->lastLoginAt(UserType::Staff),
                // The staff timestamp says the administrator still shows
                // up. This one says their customers do, which is a
                // different question and the more interesting half: an
                // installation whose only visitor is the person paying
                // for it is one nobody is getting value from.
                'last_client_login_at' => $this->lastLoginAt(UserType::Client),
            ],
            'storage' => $this->storage(),
            'usage' => $this->usage(),
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
            'build' => [
                // `channel` is 'release' or 'dev'. An internal build names
                // itself after its commit and can never be published, so a
                // fleet reading 'dev' is looking at something deliberate
                // rather than at a mistake.
                'commit' => $this->buildFact('commit'),
                'ref' => $this->buildFact('ref'),
                'channel' => $this->buildFact('channel'),
                'built_at' => $this->buildFact('built_at'),
            ],
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
        $this->line('Build:        '.($status['build']['ref'] ?? 'not a build')
            .($status['build']['channel'] === 'dev' ? ' (dev)' : ''));
        $this->line('Health:       '.$status['health']['pending_migrations'].' migrations pending, '
            .$status['health']['failed_jobs'].' failed jobs, '
            .array_sum(array_filter($status['health']['queues'], 'is_int')).' queued');
        $this->line('Scheduler:    '.($status['health']['scheduler']['last_run_at'] ?? 'never run')
            .' ('.$status['health']['scheduler']['failing'].' failing)');
        $this->line('Last '.self::USAGE_WINDOW_DAYS.'d:     '
            .array_sum($status['usage']['downloads']).' downloads, '
            .$status['usage']['uploads'].' uploads');

        return self::SUCCESS;
    }

    private function buildFact(string $key): ?string
    {
        $value = config("build.$key");

        return is_string($value) && $value !== '' ? $value : null;
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
     * @return array{pending_migrations: int, failed_jobs: int, failed_jobs_latest_at: string|null, queues: array<string, int|null>, scheduler: array{last_run_at: string|null, failing: int}}
     */
    private function health(): array
    {
        return [
            'pending_migrations' => $this->pendingMigrations(),
            'failed_jobs' => $this->failedJobs(),
            'failed_jobs_latest_at' => $this->latestFailureAt(),
            // The two this application actually runs workers for. A depth
            // is not a fault on its own -- a busy installation has one --
            // but a depth that only ever grows is a worker that died, and
            // nothing outside the container can see the difference.
            'queues' => [
                'default' => $this->queueDepth('default'),
                'zips' => $this->queueDepth('zips'),
            ],
            'scheduler' => $this->scheduler(),
        ];
    }

    /**
     * Whether the scheduler is running, and whether what it runs works.
     *
     * One row per known command, upserted on every run, so this is a
     * dozen rows however old the installation is.
     *
     * `last_run_at` is null when nothing has ever run — a brand new
     * installation, or one whose scheduler has never been wired up at
     * all — and those are different from "ran, a long time ago", which
     * is a timestamp. The reader decides what counts as too old; every
     * task in routes/console.php is daily, so anything past about a day
     * means nobody is running it. Deliberately not judged here: a
     * threshold belongs to whoever is watching, and baking one in would
     * make the answer wrong for anyone whose schedule is not ours.
     *
     * The failure *message* is deliberately not reported. This document
     * leaves the installation, and a task's error text is the one field
     * here that can carry a filesystem path, a hostname or an exception
     * from somebody's storage backend. A count says "go and look",
     * which is all a watcher needs and all it is owed.
     *
     * `failing` counts commands whose *most recent* run failed, not
     * failures over time — the row is upserted, so a task that failed
     * last night and succeeded this morning is not failing. A task that
     * has never run is not counted here either; it is absent from the
     * table, which is what `last_run_at` is for.
     *
     * @return array{last_run_at: string|null, failing: int}
     */
    private function scheduler(): array
    {
        $lastRun = ScheduledTaskRun::query()->max('ran_at');

        return [
            'last_run_at' => $lastRun === null ? null : Carbon::parse($lastRun)->toIso8601String(),
            'failing' => ScheduledTaskRun::query()
                ->where('status', TaskRunStatus::Failed)
                ->count(),
        ];
    }

    /**
     * What has been happening here lately.
     *
     * Every figure is a count over the same rolling window and there are
     * no lifetime totals — see the class docblock for why that is a
     * correctness decision rather than a presentational one.
     *
     * Downloads are split the way the installation's own dashboard
     * splits them (DashboardController::transferSeries), on purpose: the
     * administrator and whatever is reading this document have to be able
     * to agree about a number they can both see. Staff downloads are
     * reported rather than dropped so a reader can choose, but they are
     * their own key precisely because they are not audience traffic — an
     * administrator opening their own upload to check it is not somebody
     * receiving a file.
     *
     * @return array{window_days: int, downloads: array{staff: int, clients: int, anonymous: int}, uploads: int, actions: object}
     */
    private function usage(): array
    {
        $since = now()->subDays(self::USAGE_WINDOW_DAYS);

        $downloads = [
            Action::FileDownloaded->value,
            Action::ShareLinkDownloaded->value,
            Action::PublicFileDownloaded->value,
        ];

        return [
            'window_days' => self::USAGE_WINDOW_DAYS,
            'downloads' => [
                'staff' => $this->countActionsByActor($downloads, $since, UserType::Staff->value),
                'clients' => $this->countActionsByActor($downloads, $since, UserType::Client->value),
                // Null actor_type is the anonymous case: a share link or
                // the public listing, served to somebody with no account
                // at all. It is the traffic an administrator has no other
                // way to see.
                'anonymous' => $this->countActionsByActor($downloads, $since, null),
            ],
            'uploads' => $this->countActions([Action::FileUploaded->value], $since),
            // Cast for the reason `modules` is: an empty PHP array
            // encodes as a list, and a reader unmarshalling a map breaks
            // on the day it happens to be empty rather than on the day it
            // is written. It cannot be empty today, but the allowlist is
            // meant to be edited.
            'actions' => (object) $this->usageActions($since),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function usageActions(Carbon $since): array
    {
        $counts = [];

        // One keyed count each rather than a single grouped query: this
        // is both the cheaper shape (each rides (action, created_at);
        // a group-by starts from created_at and reads rows) and the one
        // that can only ever emit keys somebody chose. See USAGE_ACTIONS.
        foreach (self::USAGE_ACTIONS as $action) {
            $counts[$action->value] = $this->countActions([$action->value], $since);
        }

        return $counts;
    }

    /**
     * How many of these actions happened in the window, by anyone.
     *
     * @param  list<string>  $actions
     */
    private function countActions(array $actions, Carbon $since): int
    {
        return ActivityLog::query()
            ->whereIn('action', $actions)
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * The same count, narrowed to one kind of actor.
     *
     * Separate from countActions() rather than an optional argument on
     * it, because the argument would have to carry three states — staff,
     * client, and *nobody at all* — and null already means the third.
     * An optional `?string $actorType = null` reads as "no filter" at
     * every call site and would have silently reported the installation's
     * whole download total in the anonymous column.
     *
     * @param  list<string>  $actions
     * @param  string|null  $actorType  null is the anonymous case: a share
     *                                  link or the public listing, served
     *                                  to somebody with no account
     */
    private function countActionsByActor(array $actions, Carbon $since, ?string $actorType): int
    {
        $query = ActivityLog::query()
            ->whereIn('action', $actions)
            ->where('created_at', '>=', $since);

        return ($actorType === null
            ? $query->whereNull('actor_type')
            : $query->where('actor_type', $actorType)
        )->count();
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
     * When the most recent job failed, or null if none has.
     *
     * `failed_jobs` on its own cannot answer whether anything is wrong
     * *now*, and reading it as though it could is a category error rather
     * than a threshold that needs tuning. It is a history: the table is
     * swept daily by projectsend:purge-failed-jobs, so the count spans a
     * retention window — one whose length the installation chooses on the
     * Scheduler Monitoring screen, and which can be set to 0 for "keep
     * forever" by somebody who treats a failed job as evidence rather
     * than as debris.
     *
     * So the same number means different things on two identical
     * installations, and on a keep-forever one it grows without bound
     * until any fixed threshold trips. A fleet comparing tenants on the
     * count alone is comparing their retention settings.
     *
     * This is the field that answers the question actually being asked —
     * "has anything failed lately" — because a timestamp is independent
     * of how long the rows are kept. A count of 27 whose newest entry is
     * three weeks old is an installation that has been healthy for three
     * weeks and has not been swept yet.
     *
     * The exception text stays out, for the reason the scheduler's
     * message does: it carries paths, hostnames and stack traces, and
     * this document leaves the installation.
     */
    private function latestFailureAt(): ?string
    {
        $table = config('queue.failed.table');

        if (! is_string($table) || $table === '') {
            return null;
        }

        $latest = DB::table($table)->max('failed_at');

        return $latest === null ? null : Carbon::parse($latest)->toIso8601String();
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
     * The most recent interactive sign-in by this kind of account, or
     * null if there has never been one.
     */
    private function lastLoginAt(UserType $type): ?string
    {
        $latest = ActivityLog::query()
            ->where('action', Action::Login->value)
            ->where('actor_type', $type->value)
            ->max('created_at');

        // Answered out of (action, actor_type, created_at) without
        // reading a row: the two equalities are that index's prefix and
        // the MAX is the last entry under them. Before that index existed
        // this was a scan of every login the installation had ever
        // recorded, with a primary-key lookup per row to check the actor.
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
