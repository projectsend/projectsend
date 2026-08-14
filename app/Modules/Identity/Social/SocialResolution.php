<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use App\Models\User;

/**
 * What a provider identity turned out to mean.
 *
 * A value rather than an exception, because "we will not sign you in" is
 * an ordinary outcome here — most refusals are configuration, not error —
 * and because the controller needs to know *which* of these happened to
 * decide what to write to the activity log.
 */
final readonly class SocialResolution
{
    private function __construct(
        public ?User $user,
        public ?string $refusal,
        public bool $linked,
        public bool $provisioned,
    ) {}

    /** An account this identity was already bound to. */
    public static function existing(User $user): self
    {
        return new self($user, null, false, false);
    }

    /** An account this identity has just been bound to. */
    public static function linked(User $user): self
    {
        return new self($user, null, true, false);
    }

    /** An account that did not exist until now. */
    public static function provisioned(User $user): self
    {
        return new self($user, null, true, true);
    }

    public static function refuse(string $reason): self
    {
        return new self(null, $reason, false, false);
    }
}
