<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A staff-facing view of everything one client can reach: their own
 * uploads plus everything assigned to them, directly, via a group, or
 * via a shared folder subtree. File::scopeVisibleToClient() is the
 * single source of truth for that union — reused as-is, not
 * reimplemented. Reachable from the clients list.
 */
class ClientFilesController extends Controller
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
    ) {}

    public function index(Request $request, User $client): Response
    {
        $viewer = $request->user();
        assert($viewer !== null);
        abort_unless($client->isClient(), 404);

        // A client-scoped staff member may only browse this for clients
        // assigned to them — the same boundary StaffLibraryScope enforces
        // everywhere else in the library.
        abort_unless(! $viewer->isClientScoped() || $this->scope->canAssignClient($viewer, $client), 404);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'owner' => ['nullable', Rule::in(['uploaded', 'shared'])],
        ]);
        $search = trim($validated['search'] ?? '');
        $owner = $validated['owner'] ?? null;

        $files = File::query()->visibleToClient($client)
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$search}%")->orWhere('original_name', 'like', "%{$search}%")))
            ->when($owner === 'uploaded', fn (Builder $q) => $q->where('uploaded_by', $client->id))
            ->when($owner === 'shared', fn (Builder $q) => $q->where('uploaded_by', '!=', $client->id))
            ->with('uploader', 'categories')
            ->withCount('downloads')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (File $file): array => [
                'id' => $file->id,
                'name' => $file->name,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'created_at' => $file->created_at?->toIso8601String(),
                'uploaded_by_client' => $file->uploaded_by === $client->id,
                'uploader' => $file->uploader?->name,
                'downloads_count' => $file->downloads_count,
                'can_download' => Gate::forUser($viewer)->allows('view', $file),
                'categories' => $file->categories->map(fn (Category $category): array => [
                    'id' => $category->id, 'name' => $category->name, 'color' => $category->color,
                ])->values()->all(),
            ]);

        return Inertia::render('clients/files', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
            ],
            'files' => $files->items(),
            'pagination' => Pagination::meta($files),
            'search' => $search,
            'owner' => $owner,
        ]);
    }
}
