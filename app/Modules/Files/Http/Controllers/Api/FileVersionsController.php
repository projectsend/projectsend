<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Files\Http\Resources\Api\FileResource;
use App\Modules\Files\Models\File;
use App\Modules\Files\Versions\FileVersions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Marking a file as a new version of an earlier one.
 *
 * A nightly-revision uploader is the canonical caller: without this it can
 * create the file but never say what it replaces — a capability the
 * application has and the API cannot reach, which is exactly the drift
 * our API review habit exists to prevent.
 *
 * Every rule, including the ownership rule that stops a client inheriting a
 * stranger's recipients, lives in FileVersions and is shared verbatim with
 * the file editor and the upload flow. Three callers, one answer.
 */
class FileVersionsController extends Controller
{
    public function __construct(
        private readonly FileVersions $versions,
    ) {}

    /**
     * Mark this file as a new version of another.
     *
     * The file named by `previous_file_id` must be one you may reach, and
     * must not already have been revised. **A revision is shared with the
     * same people as the original**: any recipients this file currently has
     * are moved onto the original, and afterwards its own assignment
     * endpoints refuse writes and point at the original instead.
     */
    public function store(Request $request, File $file): FileResource
    {
        Gate::authorize('setVersion', $file);

        $validated = $request->validate([
            'previous_file_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        assert($user !== null);

        // Resolved through the same candidate rule the pickers use, so an
        // id outside this caller's reach is a 404 rather than a 403 that
        // confirms the file exists.
        $previous = $this->versions->resolveCandidate($file, $user, (int) $validated['previous_file_id']);

        abort_if($previous === null, 404);

        $this->versions->link($file, $previous, $user);

        return new FileResource($file->fresh() ?? $file);
    }

    /**
     * Remove this file's version link, making it stand on its own again.
     *
     * It stops inheriting the original's recipients, so it keeps a copy of
     * them: unlinking never takes access away from someone who already has
     * it.
     */
    public function destroy(Request $request, File $file): FileResource
    {
        Gate::authorize('setVersion', $file);

        $user = $request->user();
        assert($user !== null);

        $this->versions->unlink($file, $user);

        return new FileResource($file->fresh() ?? $file);
    }
}
