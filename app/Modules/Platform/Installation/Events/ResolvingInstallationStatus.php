<?php

declare(strict_types=1);

namespace App\Modules\Platform\Installation\Events;

/**
 * "What else is worth knowing about this installation?" — asked once,
 * by `projectsend:status`, of whatever packages happen to be installed.
 *
 * Core cannot answer for them. A managed installation's storage backend
 * and the version of the package providing it live in
 * projectsend/cloud-modules, which this repository is public and must
 * not reference; a control plane still has to be able to observe them,
 * and observing is exactly what that command is for.
 *
 * The distinction this exists to preserve: a platform writing eight
 * environment variables knows what it *asked for*. Only the installation
 * knows what actually loaded. Those came apart once — a bucket was
 * provisioned and a token minted while the container ignored both,
 * because its image predated the module that reads them, and the
 * configuration sitting beside the files looked perfectly correct.
 *
 * Listened to by *string* class name from a package, same as every
 * other hook here — see docs/extension-points-architecture.md.
 */
final class ResolvingInstallationStatus
{
    /**
     * What listeners have reported, keyed by name.
     *
     * Scalars and null only: this is serialised to JSON for a reader
     * that is not this application, and a shape it has to walk is a
     * shape it has to be taught. Null is a real answer — "asked, and
     * the thing is not here" — and it must survive to the document
     * rather than being dropped, for the reason the whole file's null
     * handling exists: absent and "nothing to report" are different
     * facts, and a reader that cannot tell them apart guesses.
     *
     * @var array<string, string|int|bool|null>
     */
    public array $facts = [];

    public function report(string $key, string|int|bool|null $value): void
    {
        $this->facts[$key] = $value;
    }
}
