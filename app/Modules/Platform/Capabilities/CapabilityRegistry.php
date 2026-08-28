<?php

declare(strict_types=1);

namespace App\Modules\Platform\Capabilities;

/**
 * What this installation may do.
 *
 * An edition grants a set of capabilities; an operator may take some of
 * them away. Those are different questions and the asymmetry between them
 * is the whole design:
 *
 * **Subtraction only.** `PROJECTSEND_CAPABILITIES_DISABLED` can remove a
 * key the edition grants. Nothing can add one. An environment variable
 * that could grant a capability would put the proprietary screens of the
 * hosted edition one line of `.env` away on every self-hosted install,
 * which is not a gate at all — so the list is read, intersected with what
 * the edition already allows, and can only ever make the answer smaller.
 *
 * **Why it exists.** A plan is not an edition. There are no billing tiers
 * in this application to key off, and inventing one here would be a claim
 * the rest of the codebase cannot back up — the same objection
 * config/api.php makes about installation-level rate limits. This is not
 * that: it is the operator telling the installation a fact about itself,
 * exactly as PROJECTSEND_PLATFORM_MAX_STAFF_USERS does for seats. The
 * platform knows what it sold; the installation is told, and enforces.
 *
 * **Unknown keys are ignored, not fatal.** A variable outlives the plan
 * that wrote it and the release that named the key. An instance that
 * refuses to boot because it was told to disable something that no longer
 * exists would be a self-inflicted outage on upgrade day.
 */
class CapabilityRegistry
{
    /**
     * @var list<string>
     */
    private readonly array $disabled;

    /**
     * @param  list<string>|string|null  $disabled  keys this installation
     *                                             has been told it may not
     *                                             use; a comma-separated
     *                                             string is what the
     *                                             environment supplies
     */
    public function __construct(
        private readonly Edition $edition,
        array|string|null $disabled = [],
    ) {
        // Parsed here rather than read from config(), so the registry stays
        // a value object that can be constructed with nothing but its two
        // facts -- which is what lets it be unit-tested without booting an
        // application, and what stops the edition and the subtraction being
        // read from two different places at two different times.
        $this->disabled = is_array($disabled)
            ? $disabled
            : array_values(array_filter(
                array_map(trim(...), explode(',', (string) $disabled)),
                fn (string $key): bool => $key !== '',
            ));
    }

    public function edition(): Edition
    {
        return $this->edition;
    }

    public function has(Capability $capability): bool
    {
        return $capability->availableIn($this->edition)
            && ! in_array($capability->value, $this->disabled, true);
    }

    /**
     * @return list<Capability>
     */
    public function enabled(): array
    {
        return array_values(array_filter(
            Capability::cases(),
            fn (Capability $capability): bool => $this->has($capability),
        ));
    }

    /**
     * Capability keys enabled for this edition, for Inertia shared props and
     * API responses.
     *
     * @return list<string>
     */
    public function enabledKeys(): array
    {
        return array_map(
            fn (Capability $capability): string => $capability->value,
            $this->enabled(),
        );
    }
}
