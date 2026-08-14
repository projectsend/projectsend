<?php

declare(strict_types=1);

namespace App\Modules\Files\Versions;

/**
 * What FileVersions::link() would do to recipients, shown before it does it.
 *
 * Linking moves the subject's own assignment rows onto the original, because
 * a revision holds none of its own — so a link can widen the original's
 * audience. That is the right outcome (nobody loses access the way dropping
 * the rows would) but it is not something to do silently to a file the
 * staffer is not currently looking at.
 */
final readonly class LinkPreview
{
    /**
     * @param  list<string>  $clientNames
     * @param  list<string>  $groupNames
     */
    public function __construct(
        public array $clientNames = [],
        public array $groupNames = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->clientNames === [] && $this->groupNames === [];
    }

    /**
     * @return array{clients: list<string>, groups: list<string>, empty: bool}
     */
    public function toArray(): array
    {
        return [
            'clients' => $this->clientNames,
            'groups' => $this->groupNames,
            'empty' => $this->isEmpty(),
        ];
    }
}
