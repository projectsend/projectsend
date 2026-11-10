<?php

declare(strict_types=1);

namespace App\Modules\Files\Folders;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\Folder;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Recreates a dropped folder's subfolders as ProjectSend folders (#1840).
 *
 * The browser reports each file's path inside the folder that was dropped
 * or picked: "Project_A/Drawings/Mechanical/Drawing_001.pdf". Everything
 * but the last part is a folder. Each level is found among the folders this
 * person can already see under the one above, by name, or created there.
 * Uploading the same folder twice therefore fills the same tree rather
 * than making a second copy beside it.
 *
 * Creating a level is creating a folder, so it is held to exactly the rules
 * of the New folder button (FolderCreationRules), level by level. A level
 * that already exists only has to be visible: the upload's own check
 * (Folder::uploadableBy on the deepest folder) still decides whether the
 * file may go there.
 *
 * The browser uploads several files at once, and files from one folder
 * all need the same levels. A lock per starting folder makes the second
 * file wait for the first to finish creating them, and then find them.
 */
final class UploadFolderPath
{
    public function __construct(
        private readonly FolderService $folders,
        private readonly FolderCreationRules $rules,
        private readonly StaffLibraryScope $staffScope,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * The folder a file at `$relativePath` belongs in, starting from
     * `$base` (null for the top level). Returns `$base` itself when the
     * path names no folder, as for a single file picked on its own.
     */
    public function resolve(User $user, ?Folder $base, ?string $relativePath): ?Folder
    {
        $segments = $this->folderSegments($relativePath);

        if ($segments === []) {
            return $base;
        }

        $lock = Cache::lock('upload-folder-path:'.($base->id ?? 'root'), 30);

        // Busy for this long means something else is stuck, not a queue:
        // making a few folders takes milliseconds. Kept short because the
        // caller already holds the per-person upload lock, which expires
        // after ten. A refusal the uploader can retry, not an error page.
        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            abort(429, __('Another upload is creating these folders. Try again in a moment.'));
        }

        try {
            $parent = $base;

            foreach ($segments as $name) {
                $existing = $this->visibleChild($user, $parent, $name);

                if ($existing !== null) {
                    $parent = $existing;

                    continue;
                }

                $this->rules->authorize($user, $parent);

                $folder = $this->folders->create($name, $parent);
                $this->activity->log(Action::FolderCreated, subject: $folder);
                $parent = $folder;
            }

            return $parent;
        } finally {
            $lock->release();
        }
    }

    /**
     * The folder names in a path, outermost first, without the file name.
     *
     * Read strictly, because it arrives from the browser: no "..", nothing
     * a folder name could not be, and no deeper than a folder can nest.
     *
     * @return list<string>
     */
    public function folderSegments(?string $relativePath): array
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return [];
        }

        $parts = array_values(array_filter(
            explode('/', str_replace('\\', '/', $relativePath)),
            fn (string $part): bool => $part !== '' && $part !== '.',
        ));

        // The last part is the file itself.
        array_pop($parts);

        $segments = [];

        foreach ($parts as $part) {
            $name = trim($part);

            if ($name === '..' || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1 || mb_strlen($name) > 255) {
                throw ValidationException::withMessages([
                    'relative_path' => __('The folder path of this file is not valid.'),
                ]);
            }

            if ($name !== '') {
                $segments[] = $name;
            }
        }

        if (count($segments) >= Folder::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'relative_path' => __('Folders cannot be nested more than :max levels deep.', ['max' => (string) Folder::MAX_DEPTH]),
            ]);
        }

        return $segments;
    }

    /**
     * A folder of this name directly inside `$parent`, among the ones this
     * person can see. A folder they cannot see is never reused, even with
     * the same name: that would file their upload somewhere they have no
     * business putting it, and tell them it exists. They get their own.
     */
    private function visibleChild(User $user, ?Folder $parent, string $name): ?Folder
    {
        $query = $user->isClient()
            ? Folder::query()->visibleToClient($user)
            : $this->staffScope->folders($user);

        // Qualified: the staff scope can join other tables.
        return $query
            ->where($query->qualifyColumn('parent_id'), $parent?->id)
            ->where($query->qualifyColumn('name'), $name)
            ->orderBy($query->qualifyColumn('id'))
            ->first();
    }
}
