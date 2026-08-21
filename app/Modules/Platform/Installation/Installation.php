<?php

declare(strict_types=1);

namespace App\Modules\Platform\Installation;

/**
 * Whether this installation runs from a container image or from files on a
 * server somebody administers directly.
 *
 * It exists because the application tells administrators how to upgrade, and
 * the two answers have nothing in common. A container is replaced —
 * `docker compose pull && docker compose up -d`, with the entrypoint running
 * the migrations on the way up. A manual install is a sequence somebody
 * performs by hand: back up, take the site down, unpack the release over the
 * directory, migrate, refresh the caches, bring it back (INSTALL.md).
 *
 * Printing the container command to someone who installed from a zip is
 * worse than printing nothing: it names a tool they do not have, for a stack
 * they are not running, at the exact moment they are trying to do the right
 * thing. That was the behaviour before this class existed — the command was
 * a hardcoded string in two React components, written when Docker was the
 * only supported path.
 *
 * The detection is the presence of the file a container runtime leaves in
 * the root filesystem. It is a deliberately conservative signal: something
 * exotic enough to run neither Docker nor Podman is reported as a manual
 * install, which is the safer wrong answer of the two — the manual
 * instructions are steps a person follows and check for themselves, while
 * the container command is one they would paste.
 */
class Installation
{
    public function kind(): InstallationKind
    {
        return $this->inContainer() ? InstallationKind::Container : InstallationKind::Manual;
    }

    /**
     * Protected so a test can answer for it: there is no way to be in a
     * container and not in one within a single test run.
     */
    protected function inContainer(): bool
    {
        // Docker writes the first; Podman writes the second.
        //
        // Suppressed, and it has to stay that way. Shared hosting sets
        // open_basedir to the webspace, and probing a path outside it is a
        // warning rather than a false — which the framework's error handler
        // turns into an exception, so the one call that asks which install
        // this is took the whole dashboard down with it (#1663). Under `@`
        // the warning is filtered and the probe answers false, which is the
        // right answer anyway: a host that restricts PHP to a vhost
        // directory is not the container image.
        return @file_exists('/.dockerenv') || @file_exists('/run/.containerenv');
    }
}
