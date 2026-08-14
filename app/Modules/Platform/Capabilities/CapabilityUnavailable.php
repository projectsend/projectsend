<?php

declare(strict_types=1);

namespace App\Modules\Platform\Capabilities;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A request reached a route whose feature this edition does not have.
 *
 * Thrown rather than rendered, so the API's own error handler formats it:
 * EnsureCapability used to build a JSON body by hand, which meant a
 * capability refusal was the one API failure that was not an RFC 7807
 * document — a caller parsing errors once would fail to parse this one.
 * ProblemDetails maps it to a `capability_unavailable` type and keeps the
 * `capability` and `edition` fields, so the machine-readable part
 * survives.
 *
 * Web requests never see this: EnsureCapability still 404s there, because
 * an unavailable feature should be absent rather than teased.
 */
final class CapabilityUnavailable extends HttpException
{
    public function __construct(
        public readonly Capability $capability,
        public readonly Edition $edition,
    ) {
        parent::__construct(403, 'This installation does not include '.$capability->value.'.');
    }
}
