<?php

declare(strict_types=1);

namespace App\Modules\Platform\Storage;

use App\Modules\Platform\Installation\Installation;
use App\Modules\Platform\Installation\InstallationKind;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;

/**
 * Where the uploads directory really lives when ProjectSend runs in a
 * container — and therefore what would survive losing the container, or
 * losing Docker itself.
 *
 * Containers are meant to be thrown away and rebuilt. That is only safe if
 * the files are somewhere else, and whether they are is invisible from the
 * screen: an installation whose uploads sit on the container's own
 * filesystem looks exactly like a correct one right up until the first
 * `docker compose up --build`, at which point every file a client ever
 * received is gone. This class is how the dashboard can say which of those
 * two installations it is looking at, instead of the operator finding out
 * afterwards.
 *
 * It reads /proc/self/mountinfo, which the app user can read, and resolves
 * the longest mount point that is a prefix of the uploads path. Three
 * outcomes, and they are genuinely different risks:
 *
 *   - the mount is the container root itself (an overlay filesystem)
 *     => Ephemeral. Recreating the container destroys the files.
 *   - the mount's source is under /docker/volumes/<name>/_data
 *     => DockerVolume. Survives upgrades and `docker compose down`, but
 *        lives inside Docker: `down -v`, `docker volume prune`, or losing
 *        the Docker installation takes it, and it is easy to leave out of
 *        a backup because it is nowhere the operator chose.
 *   - anything else (a bind mount from the host)
 *     => Durable. The files are on a path outside Docker's control.
 *
 * The database deliberately gets no equivalent check. It runs in its own
 * container, in its own mount namespace, so this process cannot see its
 * mounts — and the only way to change that is to hand the Docker socket to
 * PHP, which would turn any vulnerability in this application into root on
 * the host. DOCKER.md covers that half for a human instead.
 */
class StorageDurability
{
    public function __construct(
        private readonly ExternalStorageConfigApplier $externalStorage,
        private readonly Installation $installation,
    ) {}

    /**
     * Null when the question does not apply: not running in a container
     * (a plain server's disk layout is the operator's own business, and
     * nothing here would be news), or uploads no longer go to the local
     * disk at all because external object storage is switched on.
     *
     * @return array{level: string, volume: string|null, source: string|null}|null
     */
    public function inspect(): ?array
    {
        if (! $this->inContainer() || $this->externalStorage->isActive()) {
            return null;
        }

        $mount = $this->mountFor($this->existingAncestorOf(storage_path('app/files')));

        if ($mount === null) {
            return ['level' => StorageDurabilityLevel::Unknown->value, 'volume' => null, 'source' => null];
        }

        [$mountPoint, $source] = $mount;

        if ($mountPoint === '/') {
            return ['level' => StorageDurabilityLevel::Ephemeral->value, 'volume' => null, 'source' => null];
        }

        if (preg_match('#(?:^|/)docker/volumes/([^/]+)/_data#', $source, $matches) === 1) {
            return [
                'level' => StorageDurabilityLevel::DockerVolume->value,
                'volume' => $matches[1],
                'source' => null,
            ];
        }

        return [
            'level' => StorageDurabilityLevel::Durable->value,
            'volume' => null,
            // Shown as a hint, never as an authoritative host path: the
            // source is relative to the source filesystem's own root, so a
            // host /home/x/app bind-mounted from a separate /home partition
            // reads as "/x/app" here.
            'source' => $source,
        ];
    }

    /**
     * Protected rather than private so a test can say "pretend we are in a
     * container" without a container. Same for readMountInfo() below: those
     * two are the only things this class learns from outside itself.
     */
    protected function inContainer(): bool
    {
        // Delegated rather than duplicated: "are we in a container" is now
        // asked in two unrelated places (here, and to decide which upgrade
        // instructions to print), and two copies of that check would drift.
        return $this->installation->kind() === InstallationKind::Container;
    }

    /**
     * @return list<string>|null
     */
    protected function readMountInfo(): ?array
    {
        $lines = @file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $lines === false ? null : $lines;
    }

    /**
     * The path itself if it exists, otherwise its nearest existing parent —
     * the uploads directory is created on first write, and a brand-new
     * installation should still get an answer.
     */
    private function existingAncestorOf(string $path): string
    {
        while ($path !== '/' && ! is_dir($path)) {
            $path = dirname($path);
        }

        return $path;
    }

    /**
     * The mount whose mount point is the longest prefix of $path.
     *
     * @return array{0: string, 1: string}|null [mount point, source root]
     */
    private function mountFor(string $path): ?array
    {
        $lines = $this->readMountInfo();

        if ($lines === null) {
            return null;
        }

        $best = null;

        foreach ($lines as $line) {
            $fields = explode(' ', trim($line));

            // 3 = root of the mount within its source filesystem,
            // 4 = mount point. Both are octal-escaped for spaces and tabs.
            if (! isset($fields[3], $fields[4])) {
                continue;
            }

            $source = $this->unescape($fields[3]);
            $mountPoint = $this->unescape($fields[4]);

            $isPrefix = $mountPoint === '/'
                || $path === $mountPoint
                || str_starts_with($path, rtrim($mountPoint, '/').'/');

            if (! $isPrefix) {
                continue;
            }

            // >= rather than >: later entries win ties, and mountinfo is
            // ordered so that a mount stacked over an earlier one at the
            // same point comes later. Without this, a bind mount placed
            // over the container root would be reported as the root.
            if ($best === null || strlen($mountPoint) >= strlen($best[0])) {
                $best = [$mountPoint, $source];
            }
        }

        return $best;
    }

    private function unescape(string $value): string
    {
        return str_replace(['\040', '\011', '\012', '\134'], [' ', "\t", "\n", '\\'], $value);
    }
}
