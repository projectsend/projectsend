<?php

declare(strict_types=1);

namespace App\Modules\Platform\Capabilities;

class CapabilityRegistry
{
    public function __construct(
        private readonly Edition $edition,
    ) {}

    public function edition(): Edition
    {
        return $this->edition;
    }

    public function has(Capability $capability): bool
    {
        return $capability->availableIn($this->edition);
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
