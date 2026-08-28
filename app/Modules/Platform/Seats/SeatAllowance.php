<?php

declare(strict_types=1);

namespace App\Modules\Platform\Seats;

use App\Models\User;
use App\Modules\Identity\UserType;
use Illuminate\Validation\ValidationException;

/**
 * How many accounts this installation may hold, and how many it holds.
 *
 * Unlimited unless an operator says otherwise, which is every self-hosted
 * install. A managed one is sold a number of staff seats and a number of
 * clients, and this is the only process that can count against it — the
 * platform knows what it sold, the installation knows what exists.
 *
 * ### One definition, two consumers
 *
 * The number this refuses on is the number to display. A control plane
 * showing "2 of 3 seats used" from its own query, next to an application
 * refusing the fourth from a different one, will disagree eventually —
 * over an inactive account, or a deleted one — and the disagreement looks
 * like a billing fault rather than a counting one. So `staffUsed()` and
 * `clientUsed()` are public and are what `guard*()` reads.
 *
 * That second consumer stopped being hypothetical on 2026-08-27: the
 * hosted fleet console shows these numbers per tenant, read from
 * `projectsend:status --json`. So the rules below are load-bearing on a
 * screen support staff read, and changing one changes what they are told
 * before it changes what a customer hits.
 *
 * ### What counts
 *
 * A soft-deleted account does not. Its address stays reserved until
 * erasure (see AvailableEmailRule), so freeing a seat and re-adding the
 * same person can still be refused — that is the address rule, not this
 * one.
 *
 * An **inactive** staff account does count. Deactivating is one click from
 * reactivating, so excluding it would make deactivation a way around the
 * cap rather than a way to revoke access. The consequence is worth stating
 * because somebody has to explain it: deactivating is the safe removal and
 * does not free a seat; deleting frees it and asks what happens to the
 * files.
 *
 * A client **awaiting approval** does not count. Self-registration is open
 * to strangers, so counting a pending request would let anyone exhaust a
 * paid limit from the outside — turning a pricing tier into an
 * availability control. Approving one is the moment it becomes a client
 * the installation has taken on, and that is where the guard sits.
 *
 * ### A cap is only a cap if every door asks
 *
 * There is no single `User::create()` these funnel through, so this is
 * asked in several places and has a test per door. That is
 * DownloadAllowance's shape, for DownloadAllowance's reason: the failure
 * mode is one of them quietly not asking, and it is invisible from
 * everywhere except the door that forgot.
 */
class SeatAllowance
{
    /**
     * Staff accounts allowed, or null when unlimited.
     */
    public function staffLimit(): ?int
    {
        return $this->limit('max_staff_users');
    }

    /**
     * Clients allowed, or null when unlimited.
     */
    public function clientLimit(): ?int
    {
        return $this->limit('max_clients');
    }

    public function staffUsed(): int
    {
        return User::query()->where('type', UserType::Staff)->count();
    }

    public function clientUsed(): int
    {
        return User::query()
            ->where('type', UserType::Client)
            ->where('account_requested', false)
            ->count();
    }

    /**
     * The staff seat position, for a screen rather than a guard.
     *
     * Null on a self-hosted install: there is no limit, so there is
     * nothing for a screen to say about one.
     *
     * @return array{limit: int, used: int, full: bool, message: string|null}|null
     */
    public function staffState(): ?array
    {
        return $this->state($this->staffLimit(), $this->staffUsed(), fn (): string => $this->staffFullMessage());
    }

    /**
     * @return array{limit: int, used: int, full: bool, message: string|null}|null
     */
    public function clientState(): ?array
    {
        return $this->state($this->clientLimit(), $this->clientUsed(), fn (): string => $this->clientFullMessage());
    }

    /**
     * @throws ValidationException when one more staff account would exceed
     *                             what this installation may hold.
     */
    public function guardStaff(string $field = 'email'): void
    {
        $limit = $this->staffLimit();

        if ($limit === null || $this->staffUsed() < $limit) {
            return;
        }

        throw ValidationException::withMessages([$field => $this->staffFullMessage()]);
    }

    /**
     * @throws ValidationException when one more client would exceed what
     *                             this installation may hold.
     */
    public function guardClient(string $field = 'email'): void
    {
        $limit = $this->clientLimit();

        if ($limit === null || $this->clientUsed() < $limit) {
            return;
        }

        throw ValidationException::withMessages([$field => $this->clientFullMessage()]);
    }

    /**
     * Why a screen is closed, in the words the guard would have used.
     *
     * A door that turns somebody away and a guard that refuses them are
     * the same rule met at two moments, so they say the same sentence. Two
     * wordings of one limit is how a person ends up believing there are
     * two limits.
     */
    public function staffFullMessage(): string
    {
        return __('Staff accounts on this installation are limited to :count. Remove one, or ask for a larger plan.', [
            'count' => (string) $this->staffLimit(),
        ]);
    }

    public function clientFullMessage(): string
    {
        return __('Clients on this installation are limited to :count. Remove one, or ask for a larger plan.', [
            'count' => (string) $this->clientLimit(),
        ]);
    }

    /**
     * `full` is derived here rather than in each caller, and from the same
     * comparison `guard*()` refuses on. A screen that works out for itself
     * whether there is room can disagree with the guard about the edge --
     * `used > limit` after an operator lowers a limit is the obvious one --
     * and then the button is offered for a form that cannot be submitted,
     * which is the whole fault this is here to prevent.
     *
     * The message travels with the state so a screen never has to write
     * its own version of the refusal.
     *
     * @param  callable(): string  $message
     * @return array{limit: int, used: int, full: bool, message: string|null}|null
     */
    private function state(?int $limit, int $used, callable $message): ?array
    {
        if ($limit === null) {
            return null;
        }

        $full = $used >= $limit;

        return [
            'limit' => $limit,
            'used' => $used,
            'full' => $full,
            'message' => $full ? $message() : null,
        ];
    }

    /**
     * Absent, empty and non-numeric all mean unlimited. An operator who
     * mistypes the variable gets the self-hosted behaviour rather than an
     * installation that refuses every account it is asked to create.
     */
    private function limit(string $key): ?int
    {
        $raw = config("projectsend.platform.$key");

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return max(0, (int) $raw);
    }
}
