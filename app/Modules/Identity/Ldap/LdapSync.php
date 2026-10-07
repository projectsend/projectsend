<?php

declare(strict_types=1);

namespace App\Modules\Identity\Ldap;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\ClientProvisioning;
use App\Modules\Identity\AccountLookup;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\UserType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bringing local accounts in line with the directory, in bulk.
 *
 * The same rules as one person signing in, applied to everybody the
 * directory lists (see LdapDirectory::entries()):
 *
 *  - somebody with no account gets a client account, but only while
 *    auto-provisioning is on: that setting is the administrator's answer
 *    to "may the directory create accounts", and a sync asks it too;
 *  - a client whose account came from the directory gets its name and DN
 *    refreshed;
 *  - a staff account, or a client with a local password, is never touched.
 *    The directory is client-only, and an address alone does not make an
 *    account the directory's to manage;
 *  - an address held by a deleted account is left alone, as LdapProvisioner
 *    leaves it, unless the administrator asked for deleted clients to be
 *    restored. Even then, only a client an administrator deleted comes
 *    back: somebody who deleted their own account asked to leave, and
 *    still being listed in the directory does not overrule that.
 *
 * Directory clients that no longer appear are counted, and deactivated
 * only when the administrator asked for that — and never on a listing that
 * came back empty, which is a broken filter far more often than an empty
 * directory. A run nobody reviewed (the daily one, or the command line)
 * goes further: if it would deactivate more than DEACTIVATE_LIMIT_SHARE of
 * the directory's clients, it stops before changing anything.
 *
 * A preview is also a plan. Sync now carries out the plan the
 * administrator saw rather than reading the directory again, so a run
 * cannot do what its preview did not show; anybody whose account changed
 * in between is skipped as `changed`.
 *
 * A real run remembers whom it deactivated, so reactivateLast() can undo
 * that, and cancel() stops it between two entries.
 *
 * Which deleted clients come back can also be chosen one by one: a preview
 * lists every client that could be restored (`restorable`, even with the
 * option off), and a planned run given $restore restores exactly those
 * addresses, whatever the option says.
 *
 * @phpstan-type Report array{
 *     status: string, dry_run: bool, started_at: int, finished_at: int|null,
 *     found: int, created: int, updated: int, unchanged: int, restored: int,
 *     skipped: array<string, int>, missing: int, deactivated: int, errors: int,
 *     error: string|null, next: int, people: list<Person>, failures: list<array{email: string, error: string}>,
 *     plan: string|null, deactivated_ids: list<int>, reactivated: int
 * }
 * @phpstan-type Person array{
 *     name: string, email: string, action: string,
 *     reason?: string, previous_name?: string, moved?: bool, error?: string, restorable?: bool
 * }
 * @phpstan-type Plan array{
 *     people: list<array{dn: string, email: string, name: string, action: string}>,
 *     deactivate: list<int>
 * }
 *
 * A preview (dry run) also lists the people behind the counts, so an
 * administrator can see who a sync would touch before running it. A real
 * run keeps only the counts: it is remembered for the settings screen,
 * and a directory's worth of names has no business sitting in the cache.
 */
class LdapSync
{
    private const LAST_RUN_KEY = 'identity.ldap.sync';

    private const CANCEL_KEY = 'identity.ldap.sync.cancel';

    private const PLAN_KEY = 'identity.ldap.sync.plan.';

    /** Long enough to outlast the preview it came from and a chunked run. */
    private const PLAN_TTL_SECONDS = 3600;

    /** Failures kept from a real run for the settings screen. */
    private const FAILURES_KEPT = 20;

    /**
     * The share of directory clients an unreviewed run may deactivate.
     * More than this at once is far more often a narrowed base DN or filter
     * than people leaving. The settings screen warns at the same share.
     */
    public const DEACTIVATE_LIMIT_SHARE = 0.2;

    /**
     * What the current preview would do, kept as the plan Sync now carries out.
     *
     * @var Plan
     */
    private array $planned = ['people' => [], 'deactivate' => []];

    /**
     * The deleted clients chosen to be restored, keyed by lower-cased
     * address, or null to follow the restore option.
     *
     * @var array<string, true>|null
     */
    private ?array $restoreSelection = null;

    public function __construct(
        private readonly LdapDirectory $directory,
        private readonly ClientProvisioning $clients,
        private readonly AccountLookup $accounts,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Sync, or with $dryRun report what a sync would do and write nothing.
     *
     * With $plan, carry out what that preview found instead of reading the
     * directory, restoring the deleted clients in $restore (addresses) if
     * that is given. $offset, $carried and $deadline let SyncLdapUsersJob split
     * one run over several queue jobs: a run that reaches its deadline stops
     * between entries and reports `status: running` with the entry to resume
     * at.
     *
     * @param  Report|null  $carried
     * @param  list<string>|null  $restore
     * @return Report
     */
    public function run(bool $dryRun = false, int $offset = 0, ?array $carried = null, ?float $deadline = null, ?string $plan = null, ?array $restore = null): array
    {
        $report = $carried ?? $this->emptyReport($dryRun);
        $report['plan'] = $plan;
        $settings = LdapSettings::current();
        $this->planned = ['people' => [], 'deactivate' => []];
        $this->restoreSelection = $plan === null || $restore === null
            ? null
            : array_fill_keys(array_map(fn (string $email): string => mb_strtolower(trim($email), 'UTF-8'), $restore), true);

        if (! $settings->usable()) {
            return $this->finish($report, 'failed', __('LDAP is not switched on or not fully configured.'));
        }

        $steps = $plan === null ? null : $this->plan($plan);

        if ($plan !== null && $steps === null) {
            return $this->finish($report, 'failed', __('The preview this sync was based on has expired. Preview again, then sync.'));
        }

        if ($steps !== null) {
            $identities = array_map(fn (array $step): LdapIdentity => new LdapIdentity($step['dn'], $step['email'], $step['name']), $steps['people']);
        } else {
            try {
                $identities = $this->directory->entries();
            } catch (Throwable $e) {
                return $this->finish($report, 'failed', __('The directory could not be read: :reason', ['reason' => $e->getMessage()]));
            }
        }

        $report['found'] = count($identities);

        if (! $dryRun && $steps === null && $offset === 0 && ($stop = $this->safetyStop($identities, $settings)) !== null) {
            $this->activity->logSystem(Action::LdapSyncStopped, $stop);

            return $this->finish($report, 'stopped', __('Stopped before changing anything: this sync would deactivate :count of the :total client accounts that came from the directory. Check the base DN and the additional filter, then preview and sync from the settings screen.', $stop));
        }

        for ($i = $offset; $i < count($identities); $i++) {
            if (! $dryRun && Cache::pull(self::CANCEL_KEY)) {
                return $this->finish($report, 'cancelled');
            }

            // At least one entry per chunk, so a run always moves forward.
            if ($deadline !== null && $i > $offset && microtime(true) >= $deadline) {
                $report['next'] = $i;

                return $this->remember([...$report, 'status' => 'running']);
            }

            try {
                $this->syncOne($identities[$i], $settings, $dryRun, $report, $steps['people'][$i]['action'] ?? null);
            } catch (Throwable $e) {
                $report['errors']++;
                $this->listPerson($report, $identities[$i], 'error', ['error' => $e->getMessage()]);

                // A real run keeps no list of people, but the ones it could
                // not handle are what an administrator needs to see next.
                if (! $dryRun && count($report['failures']) < self::FAILURES_KEPT) {
                    $report['failures'][] = ['email' => $identities[$i]->email, 'error' => $e->getMessage()];
                }
            }
        }

        if (! $dryRun && Cache::pull(self::CANCEL_KEY)) {
            return $this->finish($report, 'cancelled');
        }

        if ($steps !== null) {
            $this->deactivatePlanned($steps['deactivate'], $report);
        } else {
            $this->handleMissing($identities, $settings, $dryRun, $report);
        }

        return $this->finish($report, 'finished');
    }

    /**
     * The last run, or the one in progress, for the settings screen.
     *
     * @return Report|null
     */
    public function last(): ?array
    {
        /** @var Report|null $report */
        $report = Cache::get(self::LAST_RUN_KEY);

        return $report;
    }

    /**
     * Record that a background run has been asked for, so the screen shows
     * it as running before the first job is picked up.
     */
    public function markQueued(): void
    {
        Cache::forget(self::CANCEL_KEY);
        $this->remember([...$this->emptyReport(false), 'status' => 'running']);
    }

    /**
     * Record that a background run died, so the screen does not show it as
     * running forever.
     */
    public function fail(string $error): void
    {
        $this->remember([...($this->last() ?? $this->emptyReport(false)), 'status' => 'failed', 'error' => $error, 'finished_at' => now()->getTimestamp()]);
    }

    /**
     * Stop the running sync after the entry it is on.
     *
     * The flag is what the job acts on. The report is marked straight away
     * too, so the screen stops showing a run as going when no worker is
     * there to pick the flag up; a job that does start later finds the
     * flag first and stops.
     */
    public function cancel(): bool
    {
        $last = $this->last();

        if ($last === null || $last['status'] !== 'running') {
            return false;
        }

        Cache::put(self::CANCEL_KEY, true, now()->addDay());
        $this->remember([...$last, 'status' => 'cancelled', 'finished_at' => now()->getTimestamp()]);

        return true;
    }

    /**
     * Undo the deactivations of the last real run: the accounts it switched
     * off that are still off come back on. Returns how many.
     */
    public function reactivateLast(?User $actor = null): int
    {
        $last = $this->last();
        $ids = $last['deactivated_ids'] ?? [];

        if ($last === null || $last['status'] === 'running' || $ids === []) {
            return 0;
        }

        $clients = User::query()->whereKey($ids)->where('active', false)->get(['id', 'name']);

        foreach ($clients as $client) {
            User::query()->whereKey($client->id)->update(['active' => true]);
            $this->activity->log(Action::LdapClientReactivated, $actor, context: ['name' => $client->name, 'id' => $client->id]);
        }

        $this->remember([...$last, 'deactivated_ids' => [], 'reactivated' => $last['reactivated'] + $clients->count()]);

        return $clients->count();
    }

    /**
     * @param  Report  $report
     */
    private function syncOne(LdapIdentity $identity, LdapSettings $settings, bool $dryRun, array &$report, ?string $planned): void
    {
        $account = $this->accounts->byEmail($identity->email, withTrashed: true);

        if ($account === null) {
            if (! $settings->auto_provision) {
                $this->skip($report, $identity, 'provisioning_off');

                return;
            }

            if ($this->changedSincePreview($planned, 'create', $identity, $report)) {
                return;
            }

            if (! $dryRun) {
                $this->clients->provision(
                    name: $identity->name,
                    email: $identity->email,
                    // Never used and never learned: the account signs in
                    // against the directory, as LdapProvisioner's do.
                    password: Str::password(64),
                    action: Action::LdapClientImported,
                    source: AuthSource::Ldap,
                    ldapDn: $identity->dn,
                    autoApprove: $settings->auto_approve,
                    notify: false,
                );
            }

            $report['created']++;
            $this->listPerson($report, $identity, 'create');

            return;
        }

        if ($account->trashed()) {
            $this->syncDeleted($account, $identity, $settings, $dryRun, $report, $planned);

            return;
        }

        $reason = match (true) {
            ! $account->isClient() => 'staff',
            $account->auth_source !== AuthSource::Ldap => 'local',
            default => null,
        };

        if ($reason !== null) {
            $this->skip($report, $identity, $reason);

            return;
        }

        if ($account->name === $identity->name && $account->ldap_dn === $identity->dn) {
            $report['unchanged']++;
            $this->listPerson($report, $identity, 'unchanged');

            return;
        }

        if ($this->changedSincePreview($planned, 'update', $identity, $report)) {
            return;
        }

        $detail = [];

        if ($account->name !== $identity->name) {
            $detail['previous_name'] = $account->name;
        }

        if ($account->ldap_dn !== $identity->dn) {
            $detail['moved'] = true;
        }

        $this->listPerson($report, $identity, 'update', $detail);

        if (! $dryRun) {
            $account->forceFill([
                'name' => $identity->name,
                'ldap_dn' => $identity->dn,
                'ldap_synced_at' => now(),
            ])->save();
        }

        $report['updated']++;
    }

    /**
     * A directory entry whose address belongs to a deleted account.
     *
     * Only a client an administrator deleted can come back. It does when the
     * administrator chose it in the preview, or, without a choice, when the
     * restore option is on. The account comes back as it was, with its
     * pending erasure cancelled; the files an administrator deleted or
     * reassigned at the time stay that way.
     *
     * @param  Report  $report
     */
    private function syncDeleted(User $account, LdapIdentity $identity, LdapSettings $settings, bool $dryRun, array &$report, ?string $planned): void
    {
        if (! $account->isClient()) {
            $this->skip($report, $identity, 'deleted');

            return;
        }

        if ($this->deletedByOwner($account)) {
            $this->skip($report, $identity, 'deleted_by_owner');

            return;
        }

        // Restorable: part of the plan whether or not it is ticked yet.
        if ($dryRun) {
            $this->planned['people'][] = ['dn' => $identity->dn, 'email' => $identity->email, 'name' => $identity->name, 'action' => 'restore'];
        }

        $wanted = $this->restoreSelection === null
            ? $settings->sync_restores_deleted
            : isset($this->restoreSelection[mb_strtolower(trim($identity->email), 'UTF-8')]);

        if (! $wanted) {
            $this->skip($report, $identity, 'deleted', ['restorable' => true]);

            return;
        }

        if ($this->changedSincePreview($planned, 'restore', $identity, $report)) {
            return;
        }

        if (! $dryRun) {
            $account->restore();
            $account->forceFill(['erase_after' => null])->save();

            if ($account->auth_source === AuthSource::Ldap) {
                $account->forceFill(['name' => $identity->name, 'ldap_dn' => $identity->dn, 'ldap_synced_at' => now()])->save();
            }

            $this->activity->logSystem(Action::LdapClientRestored, ['name' => $account->name, 'id' => $account->id]);
        }

        $report['restored']++;
        $this->listPerson($report, $identity, 'restore');
    }

    /**
     * Whether a planned run is about to do something its preview did not
     * show, because the account changed in between. Skipped if so.
     *
     * @param  Report  $report
     */
    private function changedSincePreview(?string $planned, string $action, LdapIdentity $identity, array &$report): bool
    {
        if ($report['plan'] === null || $planned === $action) {
            return false;
        }

        $this->skip($report, $identity, 'changed');

        return true;
    }

    /**
     * Self-deletion logs the person as the actor of their own deletion
     * (ProfileController::destroy); an administrator's delete logs the
     * administrator.
     */
    private function deletedByOwner(User $account): bool
    {
        return ActivityLog::query()
            ->where('action', Action::UserDeleted)
            ->where('actor_id', $account->id)
            ->exists();
    }

    /**
     * Why an unreviewed run must not go ahead, or null if it may.
     *
     * @param  list<LdapIdentity>  $identities
     * @return array{count: int, total: int}|null
     */
    private function safetyStop(array $identities, LdapSettings $settings): ?array
    {
        if (! $settings->sync_deactivates_missing || $identities === []) {
            return null;
        }

        $missing = $this->missingClients($identities)->count();
        $total = $this->directoryClients()->count();

        return $missing > 1 && $missing > $total * self::DEACTIVATE_LIMIT_SHARE ? ['count' => $missing, 'total' => $total] : null;
    }

    /**
     * @param  list<LdapIdentity>  $identities
     * @param  Report  $report
     */
    private function handleMissing(array $identities, LdapSettings $settings, bool $dryRun, array &$report): void
    {
        $missing = $this->missingClients($identities);

        $report['missing'] = $missing->count();
        $deactivates = $settings->sync_deactivates_missing && $identities !== [];

        foreach ($missing as $client) {
            if ($report['dry_run']) {
                $report['people'][] = ['name' => $client->name, 'email' => $client->email, 'action' => $deactivates ? 'deactivate' : 'keep'];
            }

            if (! $deactivates) {
                continue;
            }

            if ($dryRun) {
                $this->planned['deactivate'][] = $client->id;
                $report['deactivated']++;
            } else {
                $this->deactivate($client, $report);
            }
        }
    }

    /**
     * Deactivate whom the preview said would be, if they are still active
     * directory clients.
     *
     * @param  list<int>  $ids
     * @param  Report  $report
     */
    private function deactivatePlanned(array $ids, array &$report): void
    {
        $report['missing'] = count($ids);

        foreach ($this->directoryClients()->whereKey($ids)->get(['id', 'name']) as $client) {
            $this->deactivate($client, $report);
        }
    }

    /**
     * @param  Report  $report
     */
    private function deactivate(User $client, array &$report): void
    {
        User::query()->whereKey($client->id)->update(['active' => false]);
        $this->activity->logSystem(Action::LdapClientDeactivated, ['name' => $client->name, 'id' => $client->id]);
        $report['deactivated']++;
        $report['deactivated_ids'][] = $client->id;
    }

    /**
     * Active clients that came from the directory.
     *
     * @return Builder<User>
     */
    private function directoryClients(): Builder
    {
        return User::query()
            ->where('type', UserType::Client)
            ->where('auth_source', AuthSource::Ldap)
            ->where('active', true);
    }

    /**
     * Active directory clients the listing no longer has.
     *
     * @param  list<LdapIdentity>  $identities
     * @return Collection<int, User>
     */
    private function missingClients(array $identities): Collection
    {
        $listed = [];

        foreach ($identities as $identity) {
            $listed[mb_strtolower(trim($identity->email), 'UTF-8')] = true;
        }

        return $this->directoryClients()
            ->get(['id', 'name', 'email'])
            ->reject(fn (User $client): bool => isset($listed[mb_strtolower(trim($client->email), 'UTF-8')]))
            ->values();
    }

    /**
     * @return Plan|null
     */
    private function plan(string $key): ?array
    {
        /** @var Plan|null $plan */
        $plan = Cache::get(self::PLAN_KEY.$key);

        return $plan;
    }

    /**
     * @param  Report  $report
     * @param  array{restorable?: bool}  $detail
     */
    private function skip(array &$report, LdapIdentity $identity, string $reason, array $detail = []): void
    {
        $report['skipped'][$reason] = ($report['skipped'][$reason] ?? 0) + 1;
        $this->listPerson($report, $identity, 'skip', ['reason' => $reason, ...$detail]);
    }

    /**
     * @param  Report  $report
     * @param  array{reason?: string, previous_name?: string, moved?: bool, error?: string, restorable?: bool}  $detail
     */
    private function listPerson(array &$report, LdapIdentity $identity, string $action, array $detail = []): void
    {
        if (! $report['dry_run']) {
            return;
        }

        $report['people'][] = ['name' => $identity->name, 'email' => $identity->email, 'action' => $action, ...$detail];

        // Restorable clients join the plan in syncDeleted(), ticked or not.
        if (in_array($action, ['create', 'update'], true)) {
            $this->planned['people'][] = ['dn' => $identity->dn, 'email' => $identity->email, 'name' => $identity->name, 'action' => $action];
        }
    }

    /**
     * @param  Report  $report
     * @return Report
     */
    private function finish(array $report, string $status, ?string $error = null): array
    {
        $report = [...$report, 'status' => $status, 'error' => $error, 'finished_at' => now()->getTimestamp()];

        if ($report['dry_run']) {
            // A preview is shown once, on the page that asked for it, and
            // must not replace the record of the last real run. What it
            // found is kept as the plan Sync now carries out.
            if ($status === 'finished') {
                $report['plan'] = (string) Str::uuid();
                Cache::put(self::PLAN_KEY.$report['plan'], $this->planned, self::PLAN_TTL_SECONDS);
            }

            return $report;
        }

        if ($status === 'finished') {
            $this->activity->logSystem(Action::LdapSyncRun, [
                'created' => $report['created'],
                'updated' => $report['updated'],
                'deactivated' => $report['deactivated'],
            ]);
        }

        return $this->remember($report);
    }

    /**
     * @param  Report  $report
     * @return Report
     */
    private function remember(array $report): array
    {
        Cache::put(self::LAST_RUN_KEY, $report, now()->addDays(30));

        return $report;
    }

    /**
     * @return Report
     */
    private function emptyReport(bool $dryRun): array
    {
        return [
            'status' => 'running',
            'dry_run' => $dryRun,
            'started_at' => now()->getTimestamp(),
            'finished_at' => null,
            'found' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'restored' => 0,
            'skipped' => [],
            'missing' => 0,
            'deactivated' => 0,
            'errors' => 0,
            'error' => null,
            'next' => 0,
            'people' => [],
            'failures' => [],
            'plan' => null,
            'deactivated_ids' => [],
            'reactivated' => 0,
        ];
    }
}
