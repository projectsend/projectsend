<?php

declare(strict_types=1);

namespace App\Modules\Files\Versions;

use App\Models\User;
use App\Modules\Files\Access\ViewableFileScope;
use App\Modules\Files\Models\File;

/**
 * What a given viewer is allowed to be told about a file's version links.
 *
 * A presenter rather than an accessor on File, because the answer is not a
 * property of the file: it depends entirely on who is asking.
 *
 * THE RULE, and every surface derives its badge from it rather than
 * deciding for itself: a version link is disclosed only to a viewer who can
 * independently see BOTH files. Telling a client "a newer version exists"
 * when the newer version was never shared with them leaks the existence of
 * a file they were not granted — and there is nothing they could do about
 * it anyway.
 *
 * Inheritance (see FileVersions) makes both ends visible in the common
 * case, which is what makes the badge useful. It does not make the check
 * redundant: expiry, folder placement and the public flag all stay
 * per-file, so one end of a chain can be invisible while the other is not.
 */
class FileVersionLinks
{
    public function __construct(
        private readonly ViewableFileScope $viewable,
    ) {}

    /**
     * @param  callable(File): ?string|null  $urlFor  how this surface links
     *                                                to another file, if it
     *                                                can. The client portal
     *                                                passes null: it has no
     *                                                per-file page to link
     *                                                to, and guessing a
     *                                                filtered listing URL
     *                                                would be worse than a
     *                                                plain name.
     * @return array{previous: array{id: int, name: string, url: string|null}|null, next: array{id: int, name: string, url: string|null}|null}
     */
    public function for(File $file, ?User $viewer, ?callable $urlFor = null): array
    {
        return $this->forMany([$file], $viewer, $urlFor)[$file->id]
            ?? ['previous' => null, 'next' => null];
    }

    /**
     * The same answer for a whole page of rows, keyed by file id.
     *
     * Two queries regardless of row count, the same shape
     * VisibleCommentScope::countsFor() uses: the previous ids are already
     * on the loaded rows, one query finds every successor, and one more
     * asks the viewer's scope which of those candidates it may name.
     *
     * @param  iterable<File>  $files
     * @param  callable(File): ?string|null  $urlFor
     * @return array<int, array{previous: array{id: int, name: string, url: string|null}|null, next: array{id: int, name: string, url: string|null}|null}>
     */
    public function forMany(iterable $files, ?User $viewer, ?callable $urlFor = null): array
    {
        /** @var array<int, File> $rows */
        $rows = [];
        foreach ($files as $file) {
            $rows[$file->id] = $file;
        }

        if ($rows === []) {
            return [];
        }

        // Free: previous_file_id is a column on the rows already loaded.
        $previousIds = [];
        foreach ($rows as $file) {
            if ($file->previous_file_id !== null) {
                $previousIds[$file->previous_file_id] = true;
            }
        }

        // One query for every successor on the page at once. `slug` is not
        // optional in this select: the public surface builds a counterpart's
        // URL from it, and omitting it hands route() a null parameter rather
        // than failing anywhere near here.
        $successors = File::query()
            ->whereIn('previous_file_id', array_keys($rows))
            ->get(['id', 'name', 'slug', 'previous_file_id', 'public', 'expires_at', 'folder_id']);

        /** @var array<int, File> $candidates */
        $candidates = [];
        foreach ($successors as $successor) {
            $candidates[$successor->id] = $successor;
        }

        if ($previousIds !== []) {
            $previous = File::query()
                ->whereIn('id', array_keys($previousIds))
                ->get(['id', 'name', 'slug', 'previous_file_id', 'public', 'expires_at', 'folder_id']);

            foreach ($previous as $file) {
                $candidates[$file->id] = $file;
            }
        }

        $nameable = $this->nameable($candidates, $viewer);

        $result = [];

        foreach ($rows as $id => $file) {
            $next = null;
            foreach ($successors as $successor) {
                if ($successor->previous_file_id === $id) {
                    $next = $successor;
                    break;
                }
            }

            $result[$id] = [
                'previous' => $this->describe(
                    $file->previous_file_id === null ? null : ($candidates[$file->previous_file_id] ?? null),
                    $nameable,
                    $urlFor,
                ),
                'next' => $this->describe($next, $nameable, $urlFor),
            ];
        }

        return $result;
    }

    /**
     * Which of the candidate files this viewer may be told about.
     *
     * @param  array<int, File>  $candidates
     * @return list<int>
     */
    private function nameable(array $candidates, ?User $viewer): array
    {
        if ($candidates === []) {
            return [];
        }

        // A guest "sees both files" exactly when both are effectively
        // public and unexpired — the same predicate
        // PublicGroupsController::showFile 404s on, so the badge can never
        // point at a page that would refuse to load.
        if ($viewer === null) {
            $ids = [];
            foreach ($candidates as $candidate) {
                if ($candidate->isEffectivelyPublic() && ! $candidate->isExpired()) {
                    $ids[] = $candidate->id;
                }
            }

            return $ids;
        }

        // ViewableFileScope is FilePolicy::view() as a query, so this stays
        // in lockstep with what the download route would allow. One query.
        return array_values(array_map(intval(...), $this->viewable->for($viewer)
            ->whereIn('files.id', array_keys($candidates))
            ->pluck('files.id')
            ->all()));
    }

    /**
     * @param  list<int>  $nameable
     * @param  callable(File): ?string|null  $urlFor
     * @return array{id: int, name: string, url: string|null}|null
     */
    private function describe(?File $file, array $nameable, ?callable $urlFor): ?array
    {
        if ($file === null || ! in_array($file->id, $nameable, true)) {
            return null;
        }

        return [
            'id' => $file->id,
            'name' => $file->name,
            'url' => $urlFor === null ? null : $urlFor($file),
        ];
    }
}
