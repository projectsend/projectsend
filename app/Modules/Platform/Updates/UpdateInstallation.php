<?php

declare(strict_types=1);

namespace App\Modules\Platform\Updates;

use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\Permissions\EnsureSystemRoles;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Everything that has to happen after new code lands, and that the
 * application can do to itself.
 *
 * One definition, three callers: the two container entrypoints run it on
 * every boot, and update.sh runs it after unpacking a release on a server
 * somebody administers by hand. Before this existed the sequence was
 * written out in five places — both entrypoints, INSTALL.md, UPDATE.md and
 * a code block in the dashboard — and they had already drifted: only the
 * documents mentioned the queue, only the container relinked storage, and
 * none of them agreed on which caches to clear.
 *
 * The order is the design. Each constraint below cost something to find:
 *
 *   - migrate first, because everything after it assumes tables exist;
 *   - queue:restart last, because it writes its signal *into the cache*,
 *     so anything that clears the cache afterwards deletes the signal and
 *     leaves a worker running the old code forever;
 *   - the caches are cleared one command at a time rather than through
 *     optimize:clear, which also runs cache:clear — and Laravel's Redis
 *     cache store implements that as FLUSHDB. On the default config the
 *     cache sits on its own database and that is harmless; on a managed
 *     Redis offering one database, or any install that pointed cache and
 *     sessions at the same one, an update would sign every user out and
 *     delete the queue. Nothing here needs the data cache dropped:
 *     Settings::set() already forgets its own key, and cached values whose
 *     shape changes get a new key. A human who suspects a stale value has
 *     `php artisan cache:clear`, which UPDATE.md points at.
 *
 * What it deliberately does not do is maintenance mode. `php artisan down`
 * writes storage/framework/maintenance.php, and in the official image
 * storage/ is the persisted volume — a container that died between `down`
 * and `up` would come back down, and stay down through every recreation.
 * update.sh owns maintenance mode, where a trap can guarantee the site
 * comes back. This is why the command is safe to run on every boot.
 */
class UpdateInstallation
{
    public function __construct(
        private readonly Application $app,
        private readonly EnsureSystemRoles $roles,
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     migrated: bool,
     *     cleared: list<string>,
     *     rewarmed: list<string>,
     *     warnings: list<string>,
     *     ok: bool,
     * }
     */
    public function run(?OutputStyle $output = null): array
    {
        $running = (string) config('projectsend.version');

        $result = [
            'from' => '',
            'to' => $running,
            'migrated' => false,
            'cleared' => [],
            'rewarmed' => [],
            'warnings' => [],
            'ok' => false,
        ];

        // Asked before migrating, because afterwards there is no way to tell
        // a fresh installation from an existing one — and the difference
        // decides whether this run is an update worth logging or the first
        // boot of a brand new install.
        $existingInstall = $this->hasRunMigrationsBefore();

        // Caught rather than left to surface as a stack trace: the most
        // likely failure here is a database that is unreachable or refusing
        // the credentials, and forty frames of Laravel internals is a worse
        // answer to that than one sentence naming it.
        try {
            $migrated = $this->artisan('migrate', ['--force' => true], $output) === 0;
        } catch (Throwable $exception) {
            $result['warnings'][] = 'The database migration failed: '.$exception->getMessage();
            $result['warnings'][] = 'Nothing else was changed.';

            return $result;
        }

        if (! $migrated) {
            $result['warnings'][] = 'The database migration failed. Nothing else was changed.';

            return $result;
        }

        $result['migrated'] = true;
        $result['from'] = $this->previouslyApplied();

        $this->roles->ensure();

        // Not parity with the old entrypoint line — a fix. The release zip
        // ships no public/storage symlink (the build refuses symlinks
        // outright, they do not survive zipping), so an installation that
        // followed UPDATE.md's "unpack beside it and swap the directories"
        // advice loses the link entirely, and nothing in the documented
        // sequence ever put it back.
        $this->artisan('storage:link', ['--force' => true], $output);

        // Read before anything is cleared: this is the only moment the
        // question "was this installation using the optional caches?" can
        // still be answered.
        $warm = $this->warmCaches();

        foreach (['config:clear', 'clear-compiled', 'event:clear', 'route:clear', 'view:clear'] as $command) {
            if ($this->artisan($command, [], $output) === 0) {
                $result['cleared'][] = $command;
            }
        }

        if ($warm['config']) {
            $result['warnings'][] = 'A cached configuration was found and cleared. Do not run config:cache on this'
                .' application — it stops TRUSTED_PROXIES from being read at all. See INSTALL.md.';
        }

        foreach ($this->cachesToRewarm($warm) as $command) {
            // A route table that will not compile is a slower site; a
            // failed update is a broken one. Never fatal.
            if ($this->artisan($command, [], $output) === 0) {
                $result['rewarmed'][] = $command;

                continue;
            }

            $result['warnings'][] = "{$command} failed, so that cache is not in place. The site runs without it.";
        }

        $this->settings->set(Setting::AppliedVersion, $running);
        $this->settings->set(Setting::AppliedVersionAt, now()->toIso8601String());

        // Everything that happens only when this run actually moved the
        // installation somewhere new. Both entrypoints run this command on
        // every boot, so "somewhere new" is the narrow case, not the
        // common one.
        if ($existingInstall && $result['from'] !== $running) {
            $warning = $this->recordTheUpdate($result['from'], $running);

            if ($warning !== null) {
                $result['warnings'][] = $warning;
            }

            // Forwards only. Going back is a version change worth recording
            // above, but somebody who has just restored an older release is
            // dealing with a problem, and "thank you for keeping this
            // current" is the wrong thing to greet them with. An unknown
            // previous version counts as forwards: it is the first update
            // of an installation older than this feature.
            if ($result['from'] === '' || version_compare($running, $result['from'], '>')) {
                $this->raiseTheWelcome($result['from'], $running);
            }
        }

        // Last, and after every cache operation above — see the class
        // docblock. A worker only learns to exit by reading this signal.
        $this->artisan('queue:restart', [], $output);

        $result['ok'] = true;

        return $result;
    }

    /**
     * Leave the marker the administrator's welcome page consumes: an
     * update landed, and this is the ground it covered.
     *
     * Both versions rather than just the new one, because an installation
     * that skipped releases — anybody who updates twice a year — should be
     * shown every release in the gap and not only the newest.
     *
     * Swallows failure for the reason the activity log below does: an
     * update that worked must not report failure because of a greeting.
     */
    private function raiseTheWelcome(string $from, string $to): void
    {
        try {
            $this->settings->set(Setting::UpdateWelcomeFrom, $from);
            $this->settings->set(Setting::UpdateWelcomeTo, $to);
        } catch (Throwable) {
            // Nothing worth telling anyone: the update itself is complete,
            // and the only casualty is a page nobody has seen yet.
        }
    }

    /**
     * Put the update in the activity log — the one place an administrator
     * looks to answer "what changed on this installation, and when".
     *
     * Only reached for a genuine version change (see the caller). The
     * container entrypoint runs this on every boot, so logging
     * unconditionally would bury the log under an entry per restart; and a
     * fresh installation is an install, not an update, which SetupCompleted
     * already covers.
     *
     * "Fresh" is decided by whether migrations had ever run before this
     * one, rather than by whether a version was recorded: the first update
     * of any installation older than this command finds no recorded
     * version, and that update is exactly the one worth logging.
     */
    private function recordTheUpdate(string $from, string $to): ?string
    {
        try {
            $this->activity->logSystem(Action::ApplicationUpdated, [
                // The previous version is unknown exactly once per
                // installation: the first update after adopting this command.
                // A dash rather than a sentence, because context values are
                // substituted into the template verbatim and never
                // translated — an English phrase here would sit inside an
                // otherwise Japanese or Polish row forever.
                'from' => $from !== '' ? $from : '—',
                'to' => $to,
            ]);
        } catch (Throwable $exception) {
            // An update that worked must not report failure because its own
            // paperwork did.
            return 'The update could not be written to the activity log: '.$exception->getMessage();
        }

        return null;
    }

    /**
     * Whether this database has been migrated before — i.e. whether there
     * was an installation here at all before this run.
     */
    protected function hasRunMigrationsBefore(): bool
    {
        try {
            return Schema::hasTable('migrations') && DB::table('migrations')->exists();
        } catch (Throwable) {
            // No database yet is not an existing installation.
            return false;
        }
    }

    /**
     * What the previous run of this recorded, or '' when there was none.
     *
     * Swallows failures on purpose: by this point the schema is current,
     * but a cache store that is momentarily unreachable must not fail a
     * container boot over a line of reporting.
     */
    private function previouslyApplied(): string
    {
        try {
            $applied = $this->settings->get(Setting::AppliedVersion);

            return is_string($applied) ? $applied : '';
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Which optional caches this installation had in place.
     *
     * Asked through the framework's own path accessors rather than the
     * filenames: `routes-v7.php` is an internal that changes with major
     * versions. And by file_exists() rather than $app->routesAreCached(),
     * which memoises at bootstrap and would still answer true after
     * route:clear ran in this same process.
     *
     * @return array{route: bool, event: bool, config: bool}
     */
    protected function warmCaches(): array
    {
        return [
            'route' => file_exists($this->app->getCachedRoutesPath()),
            'event' => file_exists($this->app->getCachedEventsPath()),
            'config' => file_exists($this->app->getCachedConfigPath()),
        ];
    }

    /**
     * Views have no honest signal of their own — storage/framework/views
     * fills up from ordinary traffic, cached deliberately or not. So the
     * route and event caches stand in for the set: their presence means
     * this installation followed INSTALL.md's "Making it faster", which
     * lists all three together. A container caches none of them and so
     * rebuilds nothing, which keeps boot as fast as it is today.
     *
     * config:cache is never rebuilt, at any time, for any installation.
     *
     * @param  array{route: bool, event: bool, config: bool}  $warm
     * @return list<string>
     */
    private function cachesToRewarm(array $warm): array
    {
        if (! $warm['route'] && ! $warm['event']) {
            return [];
        }

        return ['route:cache', 'event:cache', 'view:cache'];
    }

    /**
     * Protected so a test can watch the sequence without running it. The
     * ordering constraints in run() are invisible in its result and
     * catastrophic when wrong, and an ordered list of calls is the only
     * thing that can assert them.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function artisan(string $command, array $parameters = [], ?OutputStyle $output = null): int
    {
        return Artisan::call($command, $parameters, $output);
    }
}
