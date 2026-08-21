<?php

declare(strict_types=1);

namespace App\Modules\Platform\Installation;

/**
 * Whether this installation runs from a container image, from a container
 * the operator builds themselves, or from files on a server somebody
 * administers directly.
 *
 * It exists because the application tells administrators how to upgrade, and
 * the three answers have nothing in common. A published container is
 * replaced — `docker compose pull && docker compose up -d`, with the
 * entrypoint running the migrations on the way up. A container built from a
 * checkout has to be given new code and rebuilt. A manual install is a
 * sequence somebody performs by hand: back up, take the site down, unpack
 * the release over the directory, migrate, refresh the caches, bring it back
 * (INSTALL.md).
 *
 * Printing the wrong one of those is worse than printing nothing. For a
 * manual install the container command names a tool they do not have, for a
 * stack they are not running, at the exact moment they are trying to do the
 * right thing — that was the behaviour before this class existed, when the
 * command was a hardcoded string in two React components. For a stack built
 * from a checkout it is worse still, because the command runs: `pull` skips
 * services that have no image to pull and `up -d` then finds every container
 * already current, so the update reports success and changes nothing, and
 * the dashboard goes on offering the same release forever (#1661).
 *
 * Two signals, in order:
 *
 *   1. The published image sets PROJECTSEND_IMAGE. A positive marker set at
 *      build time is the only one a bind mount can neither forge nor hide.
 *   2. Failing that — images published before that variable existed — a
 *      working tree in the install directory. The image is built from an
 *      unpacked release artifact and has none; the Compose stack in the
 *      repository bind-mounts the repository itself.
 *
 * Being in a container at all is the presence of the file a container
 * runtime leaves in the root filesystem. It is a deliberately conservative
 * signal: something exotic enough to run neither Docker nor Podman is
 * reported as a manual install, which is the safer wrong answer of the
 * three — the manual instructions are steps a person follows and checks for
 * themselves, while the container commands are ones they would paste.
 */
class Installation
{
    public function kind(): InstallationKind
    {
        if (! $this->inContainer()) {
            return InstallationKind::Manual;
        }

        return $this->builtFromSource()
            ? InstallationKind::ContainerSource
            : InstallationKind::Container;
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

    /**
     * Protected for the same reason as inContainer(), and answered the same
     * way in tests.
     */
    protected function builtFromSource(): bool
    {
        // getenv() rather than env(): once the configuration is cached,
        // env() outside a config file returns null, and the answer would
        // silently flip on the installs most likely to have cached it.
        if (getenv('PROJECTSEND_IMAGE') === '1') {
            return false;
        }

        // A worktree checkout writes .git as a file rather than a
        // directory, so ask whether it exists, not what it is.
        return file_exists(base_path('.git'));
    }
}
