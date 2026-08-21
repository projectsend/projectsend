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
        // Docker writes the first; Podman writes the second. The probes are
        // suppressed because under open_basedir (common on shared hosting)
        // file_exists() on a path outside the allowed list raises a warning
        // instead of returning false - and the framework escalates that
        // warning into an exception. An unreadable answer means "not a
        // container": a host restrictive enough for open_basedir is never
        // the Docker install.
        return @file_exists('/.dockerenv') || @file_exists('/run/.containerenv');
    }
}
