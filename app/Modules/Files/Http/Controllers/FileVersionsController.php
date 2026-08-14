<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Files\Access\ViewableFileScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Versions\FileVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Marking a file as a revision of an earlier one, and unmarking it.
 *
 * Its own controller rather than fields on FilesController::update(): that
 * method's validated array feeds $file->update() on a model with
 * $guarded = [], and putting a relationship that moves assignment rows and
 * sends notifications behind a mass-assignment path is exactly the accident
 * to avoid. Same reasoning that already gives move() its own route.
 *
 * Every rule lives in FileVersions, not here — the upload flow and the API
 * reach the same seam, and none of the three may answer differently.
 */
class FileVersionsController extends Controller
{
    public function __construct(
        private readonly FileVersions $versions,
        private readonly ViewableFileScope $viewable,
    ) {}

    /**
     * Files this user may pick as $file's original.
     *
     * A JSON feed rather than a prop on the edit page (FileDetailsController
     * is the precedent): a file library is unbounded, so serialising it into
     * every file edit page is not an option, and searching past any
     * truncation point needs a round trip anyway.
     */
    public function candidates(Request $request, File $file): JsonResponse
    {
        Gate::authorize('setVersion', $file);

        $user = $request->user();
        assert($user !== null);

        $search = trim((string) $request->query('search', ''));

        $candidates = $this->versions->candidates($file, $user, $search)
            ->map(fn (File $candidate): array => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'original_name' => $candidate->original_name,
                'created_at' => $candidate->created_at?->toIso8601String(),
            ])->values();

        return response()->json(['files' => $candidates]);
    }

    /**
     * What linking to a given original would do to recipients, so the
     * confirmation can say it before it happens.
     */
    public function preview(Request $request, File $file): JsonResponse
    {
        Gate::authorize('setVersion', $file);

        $validated = $request->validate([
            'previous_file_id' => ['required', 'integer'],
        ]);

        $previous = $this->resolvePrevious($request, (int) $validated['previous_file_id']);

        return response()->json($this->versions->previewLink($file, $previous)->toArray());
    }

    public function store(Request $request, File $file): RedirectResponse
    {
        Gate::authorize('setVersion', $file);

        $validated = $request->validate([
            'previous_file_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        assert($user !== null);

        $previous = $this->resolvePrevious($request, (int) $validated['previous_file_id']);

        $this->versions->link($file, $previous, $user);

        return back()->with('success', __('This file is now marked as a new version of ":name".', ['name' => $previous->name]));
    }

    public function destroy(Request $request, File $file): RedirectResponse
    {
        Gate::authorize('setVersion', $file);

        $user = $request->user();
        assert($user !== null);

        $this->versions->unlink($file, $user);

        return back()->with('success', __('Version link removed.'));
    }

    /**
     * 404 rather than 403 for a file outside the caller's reach: confirming
     * that an id exists is itself information a client-scoped staffer is
     * not entitled to. link() re-checks this through the policy, so this is
     * the friendly failure, not the boundary.
     */
    private function resolvePrevious(Request $request, int $id): File
    {
        $user = $request->user();
        assert($user !== null);

        return $this->viewable->for($user)->whereKey($id)->firstOrFail();
    }
}
