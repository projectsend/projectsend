<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use App\Models\User;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Platform\Seats\SeatAllowance;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * The account behind this request: who, with which credential, on what
 * plan, with how much room.
 *
 * Built once per request from the user the guard resolved and the token
 * that resolved them, and handed to every tool. The three questions
 * every tool asks first — is this a free account, what may the token do,
 * does the person hold the permission — are answered here so they are
 * answered the same way by every tool.
 */
final class Account
{
    /** From this share of the quota on, the connector says the space is nearly gone. */
    public const NEARLY_FULL_PERCENT = 80;

    /** @var array{used_bytes: int, used: string, quota_bytes: int, quota: string, remaining_bytes: int, remaining: string, percent: int}|null */
    private ?array $storageCache = null;

    private bool $storageResolved = false;

    private function __construct(
        public readonly User $user,
        public readonly Scope $scope,
        public readonly Connector $connector,
    ) {}

    /**
     * From the guard's user and their current token. Null when nobody is
     * authenticated, which the route's middleware makes impossible and a
     * tool still refuses to assume.
     */
    public static function current(): ?self
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        // Sanctum types this narrower than it is: on a session request it
        // is a TransientToken, which carries no abilities and no name.
        /** @var object|null $token */
        $token = $user->currentAccessToken();
        $personal = $token instanceof PersonalAccessToken ? $token : null;
        $abilities = $personal !== null && is_array($personal->abilities) ? array_values($personal->abilities) : [];
        $tokenName = $personal?->name;

        return new self($user, Scope::fromAbilities($abilities), Connector::fromTokenName($tokenName));
    }

    /**
     * For tests and for the server's own bookkeeping: an account built
     * from known parts rather than from the guard.
     */
    public static function of(User $user, Scope $scope, Connector $connector): self
    {
        return new self($user, $scope, $connector);
    }

    public function id(): int
    {
        return (int) $this->user->id;
    }

    public function name(): string
    {
        return (string) $this->user->name;
    }

    public function email(): string
    {
        return (string) $this->user->email;
    }

    /**
     * A client account. Where clients may connect (ConnectorPlan) that is
     * a customer of the plan clients are on; anywhere else a client has no
     * business holding a token, and every tool treats one as "own files
     * only" regardless.
     */
    public function isClient(): bool
    {
        return $this->user->isClient();
    }

    public function isStaff(): bool
    {
        return ! $this->isClient();
    }

    /**
     * A customer of a free plan: a client, where client accounts are a
     * plan of their own. Never true on a self-hosted installation.
     */
    public function isFree(): bool
    {
        return $this->isClient() && ConnectorPlan::current()->clientsMayConnect();
    }

    /** The person's live permission, through the app's gate. */
    public function can(string $permission): bool
    {
        return $this->user->can($permission);
    }

    /**
     * The plan this account is on, as the person would name it, or null
     * where the installation has no plans.
     *
     * @return array{code: string, name: string, price: string|null}|null
     */
    public function plan(): ?array
    {
        if ($this->isFree()) {
            return ['code' => 'free', 'name' => 'Free', 'price' => 'Free'];
        }

        return $this->isClient() ? null : ConnectorPlan::current()->plan();
    }

    /**
     * The plan a free account would move to for what it lacks, or null
     * when there is no such offer.
     *
     * @return array{code: string, name: string, price: string}|null
     */
    public function upgradePlan(): ?array
    {
        return $this->isFree() ? ConnectorPlan::current()->upgradePlan() : null;
    }

    /**
     * The usage block (spec §2.1), or null when there is nothing to run
     * out of: a staff account, no quota, or an app that could not answer.
     *
     * @return array{used_bytes: int, used: string, quota_bytes: int, quota: string, remaining_bytes: int, remaining: string, percent: int}|null
     */
    public function storage(): ?array
    {
        if ($this->storageResolved) {
            return $this->storageCache;
        }

        $this->storageResolved = true;

        if (! $this->isClient()) {
            return $this->storageCache = null;
        }

        $usage = app(ClientStorageUsage::class);

        try {
            $quota = (int) $usage->quotaBytes($this->user);

            if ($quota <= 0) {
                return $this->storageCache = null;
            }

            $used = (int) $usage->usedBytes($this->user);
            $remaining = max(0, $quota - $used);

            return $this->storageCache = [
                'used_bytes' => $used,
                'used' => Bytes::human($used),
                'quota_bytes' => $quota,
                'quota' => Bytes::human($quota),
                'remaining_bytes' => $remaining,
                'remaining' => Bytes::human($remaining),
                'percent' => Bytes::percent($used, $quota),
            ];
        } catch (Throwable) {
            return $this->storageCache = null;
        }
    }

    /**
     * The same block, read again after a write changed it.
     *
     * @return array{used_bytes: int, used: string, quota_bytes: int, quota: string, remaining_bytes: int, remaining: string, percent: int}|null
     */
    public function refreshedStorage(): ?array
    {
        $this->storageResolved = false;

        return $this->storage();
    }

    /**
     * The one warning the harness carries about space, in the person's
     * own numbers and without a link (spec §7).
     */
    public function storageWarning(): ?string
    {
        $storage = $this->storage();

        if ($storage === null || $storage['used_bytes'] * 100 < $storage['quota_bytes'] * self::NEARLY_FULL_PERCENT) {
            return null;
        }

        return sprintf(
            $this->upgradePlan() === null
                ? 'This account has used %s of its %s (%d%%). Freeing space is the way forward; see get_usage.'
                : 'This account has used %s of its %s (%d%%). Freeing space or a portal of their own are the two ways forward; see get_usage.',
            $storage['used'],
            $storage['quota'],
            $storage['percent'],
        );
    }

    /**
     * Client and staff seats, for staff accounts, from the app's own
     * allowance. Null on a client account or when the app has no cap.
     *
     * @return array{clients_used: int, clients_limit: int|null, staff_used: int, staff_limit: int|null}|null
     */
    public function seats(): ?array
    {
        if ($this->isClient()) {
            return null;
        }

        $seats = app(SeatAllowance::class);

        try {
            return [
                'clients_used' => (int) $seats->clientUsed(),
                'clients_limit' => $seats->clientLimit() === null ? null : (int) $seats->clientLimit(),
                'staff_used' => (int) $seats->staffUsed(),
                'staff_limit' => $seats->staffLimit() === null ? null : (int) $seats->staffLimit(),
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
