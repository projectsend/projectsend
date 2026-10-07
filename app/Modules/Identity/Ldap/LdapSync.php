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
 * directory.
 *
 * @phpstan-type Report array{
 *     status: string, dry_run: bool, started_at: int, finished_at: int|null,
 *     found: int, created: int, updated: int, unchanged: int, restored: int,
 *     skipped: array<string, int>, missing: int, deactivated: int, errors: int,
 *     error: string|null, next: int, people: list<Person>, failures: list<array{email: string, error: string}>
 * }
 * @phpstan-type Person array{
 *     name: string, email: string, action: string,
 *     reason?: string, previous_name?: string, moved?: bool, error?: string
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

    /** Failures kept from a real run for the settings screen. */
    private const FAILURES_KEPT = 20;

    public function __construct(
        private readonly LdapDirectory $directory,
        private readonly ClientProvisioning $clients,
        private readonly AccountLookup $accounts,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Sync, or with $dryRun report what a sync would do and write nothing.
     *
     * $offset, $carried and $deadline let SyncLdapUsersJob split one run
     * over several queue jobs: a run that reaches its deadline stops between
     * entries and reports `status: running` with the entry to resume at.
     *
     * @param  Report|null  $carried
     * @return Report
     */
    public function run(bool $dryRun = false, int $offset = 0, ?array $carried = null, ?float $deadline = null): array
    {
        $report = $carried ?? $this->emptyReport($dryRun);
        $settings = LdapSettings::current();

        if (! $settings->usable()) {
            return $this->finish($report, 'failed', __('LDAP is not switched on or not fully configured.'));
        }

        try {
            $identities = $this->directory->entries();
        } catch (Throwable $e) {
            return $this->finish($report, 'failed', __('The directory could not be read: :reason', ['reason' => $e->getMessage()]));
        }

        $report['found'] = count($identities);

        for ($i = $offset; $i < count($identities); $i++) {
            // At least one entry per chunk, so a run always moves forward.
            if ($deadline !== null && $i > $offset && microtime(true) >= $deadline) {
                $report['next'] = $i;

                return $this->remember([...$report, 'status' => 'running']);
            }

            try {
                $this->syncOne($identities[$i], $settings, $dryRun, $report);
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

        $this->handleMissing($identities, $settings, $dryRun, $report);

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
     * @param  Report  $report
     */
    private function syncOne(LdapIdentity $identity, LdapSettings $settings, bool $dryRun, array &$report): void
    {
        $account = $this->accounts->byEmail($identity->email, withTrashed: true);

        if ($account === null) {
            if (! $settings->auto_provision) {
                $this->skip($report, $identity, 'provisioning_off');

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
            $this->syncDeleted($account, $identity, $settings, $dryRun, $report);

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
     * Restored only when the administrator asked for it, only for a client,
     * and only if an administrator did the deleting. The account comes back
     * as it was, with its pending erasure cancelled; the files an
     * administrator deleted or reassigned at the time stay that way.
     *
     * @param  Report  $report
     */
    private function syncDeleted(User $account, LdapIdentity $identity, LdapSettings $settings, bool $dryRun, array &$report): void
    {
        $reason = match (true) {
            ! $settings->sync_restores_deleted, ! $account->isClient() => 'deleted',
            $this->deletedByOwner($account) => 'deleted_by_owner',
            default => null,
        };

        if ($reason !== null) {
            $this->skip($report, $identity, $reason);

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
     * @param  list<LdapIdentity>  $identities
     * @param  Report  $report
     */
    private function handleMissing(array $identities, LdapSettings $settings, bool $dryRun, array &$report): void
    {
        $listed = [];

        foreach ($identities as $identity) {
            $listed[mb_strtolower(trim($identity->email), 'UTF-8')] = true;
        }

        $missing = User::query()
            ->where('type', UserType::Client)
            ->where('auth_source', AuthSource::Ldap)
            ->where('active', true)
            ->get(['id', 'name', 'email'])
            ->reject(fn (User $client): bool => isset($listed[mb_strtolower(trim($client->email), 'UTF-8')]));

        $report['missing'] = $missing->count();
        $deactivates = $settings->sync_deactivates_missing && $identities !== [];

        foreach ($missing as $client) {
            if ($report['dry_run']) {
                $report['people'][] = ['name' => $client->name, 'email' => $client->email, 'action' => $deactivates ? 'deactivate' : 'keep'];
            }

            if (! $deactivates) {
                continue;
            }

            if (! $dryRun) {
                User::query()->whereKey($client->id)->update(['active' => false]);
                $this->activity->logSystem(Action::LdapClientDeactivated, ['name' => $client->name, 'id' => $client->id]);
            }

            $report['deactivated']++;
        }
    }

    /**
     * @param  Report  $report
     */
    private function skip(array &$report, LdapIdentity $identity, string $reason): void
    {
        $report['skipped'][$reason] = ($report['skipped'][$reason] ?? 0) + 1;
        $this->listPerson($report, $identity, 'skip', ['reason' => $reason]);
    }

    /**
     * @param  Report  $report
     * @param  array{reason?: string, previous_name?: string, moved?: bool, error?: string}  $detail
     */
    private function listPerson(array &$report, LdapIdentity $identity, string $action, array $detail = []): void
    {
        if ($report['dry_run']) {
            $report['people'][] = ['name' => $identity->name, 'email' => $identity->email, 'action' => $action, ...$detail];
        }
    }

    /**
     * @param  Report  $report
     * @return Report
     */
    private function finish(array $report, string $status, ?string $error = null): array
    {
        $report = [...$report, 'status' => $status, 'error' => $error, 'finished_at' => now()->getTimestamp()];

        if (! $report['dry_run'] && $status === 'finished') {
            $this->activity->logSystem(Action::LdapSyncRun, [
                'created' => $report['created'],
                'updated' => $report['updated'],
                'deactivated' => $report['deactivated'],
            ]);
        }

        // A preview is shown once, on the page that asked for it, and must
        // not replace the record of the last real run.
        return $report['dry_run'] ? $report : $this->remember($report);
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
        ];
    }
}
