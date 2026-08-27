<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Api\Support\PollingQuery;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Comments\CommentingRules;
use App\Modules\Comments\CommentScope;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Access\ViewableFileScope;
use App\Modules\Files\DownloadLimitScope;
use App\Modules\Files\Http\Resources\Api\FileResource;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Storage\ResolvingUploadDisk;
use App\Modules\Files\Uploads\StoreUploadedFile;
use App\Modules\Files\Uploads\UploadExtensionPolicy;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\Rules;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Read access to the file library.
 *
 * The listing is built from ViewableFileScope, never from File::query().
 * That class is FilePolicy::view() expressed as SQL, so a client-scoped
 * staff member's token returns exactly the files they see in the UI and
 * the token ability check stays an *additional* gate rather than the only
 * one. Reimplementing the visibility rules here would create a second
 * definition of "may see", and the two would drift.
 */
class FilesController extends Controller
{
    public function __construct(
        private readonly ViewableFileScope $viewable,
        private readonly PollingQuery $polling,
        private readonly Settings $settings,
        private readonly StoreUploadedFile $storeFile,
        private readonly UploadExtensionPolicy $extensionPolicy,
        private readonly ClientStorageUsage $storageUsage,
        private readonly ActivityLogger $activity,
        private readonly CommentingRules $commenting,
        private readonly StaffLibraryScope $scope,
    ) {}

    /**
     * Eager loads for the version counterparts, constrained to what this
     * token's owner may see.
     *
     * Constrained here rather than in FileResource so the resource stays a
     * pure allowlist with no visibility logic of its own — one definition of
     * who may be told about a counterpart, in ViewableFileScope, exactly as
     * on the web. Two extra queries for a whole page, not two per row.
     *
     * @return array<string, Closure(Relation<*, *, *>): mixed>
     */
    private function versionRelations(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        // clone: the same builder is compiled into two separate subqueries,
        // and a Builder is not reusable once bound.
        $visible = $this->viewable->for($user)->select('files.id');

        return [
            'previousVersion' => fn (Relation $query) => $query->whereIn('files.id', (clone $visible)->getQuery()),
            'nextVersion' => fn (Relation $query) => $query->whereIn('files.id', (clone $visible)->getQuery()),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        assert($user !== null);

        $filters = $request->validate($this->polling->rules() + [
            'folder_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'uploaded_by' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'public' => ['nullable', 'boolean'],
            'expired' => ['nullable', 'boolean'],
        ]);

        $query = $this->viewable->for($user)
            ->with(['folder', 'uploader', 'categories'] + $this->versionRelations($user));

        if (array_key_exists('folder_id', $filters) && $filters['folder_id'] !== null) {
            $query->where('files.folder_id', $filters['folder_id']);
        }

        if (array_key_exists('uploaded_by', $filters) && $filters['uploaded_by'] !== null) {
            $query->where('files.uploaded_by', $filters['uploaded_by']);
        }

        if (array_key_exists('category_id', $filters) && $filters['category_id'] !== null) {
            $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey($filters['category_id']));
        }

        if (($filters['search'] ?? null) !== null) {
            $search = $filters['search'];
            $query->where(fn (Builder $inner) => $inner
                ->where('files.name', 'like', "%{$search}%")
                ->orWhere('files.description', 'like', "%{$search}%")
                ->orWhere('files.original_name', 'like', "%{$search}%"));
        }

        if ($request->has('public') && ($filters['public'] ?? null) !== null) {
            $query->where('files.public', $request->boolean('public'));
        }

        // Expiry is a filter, not a default: staff see expired files in the
        // UI too (that is how they notice and act on them). Dropping them
        // is the client branch's rule, applied inside the visibility scopes
        // where it belongs — which is also why a client-scoped caller does
        // not get their clients' expired files back here whatever this
        // filter says: their library is built on that same branch. See
        // File::isExpired.
        if ($request->has('expired') && ($filters['expired'] ?? null) !== null) {
            $request->boolean('expired') ? $query->expired() : $query->notExpired();
        }

        return FileResource::collection($this->polling->paginate($request, $query, 'files'));
    }

    public function show(Request $request, File $file): FileResource
    {
        Gate::authorize('view', $file);

        $file->load(['folder', 'uploader', 'categories', 'assignments.assignable'] + $this->versionRelations($request->user()));

        return new FileResource($file);
    }

    /**
     * Upload a file in a single request.
     *
     * Send the file as multipart form data. The maximum accepted size is
     * this installation's configured upload limit; larger or unreliable
     * uploads should use the resumable `/uploads` endpoints instead.
     *
     * The stored content type is detected from the uploaded bytes, not from
     * the declared `Content-Type`.
     */
    public function store(Request $request): JsonResponse
    {
        // NOTE: docblocks on the methods in this namespace are published as
        // the API reference (Scramble reads them), so implementation notes
        // belong here rather than above.
        //
        // This is deliberately not a copy of the web FilesController::store():
        // that one exists to seed fixtures for the test suite and hard-caps
        // at 100 MB in validation instead of reading Setting::MaxFileSizeMb.
        // The checks below mirror the chunked flow's, which are the real ones.
        $user = $request->user();
        assert($user !== null);

        $validated = $request->validate([
            'file' => ['required', 'file'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'folder_id' => Rules::folderId(),
        ]);

        /** @var UploadedFile $upload */
        $upload = $validated['file'];
        $size = (int) $upload->getSize();

        $maxMb = (int) $this->settings->get(Setting::MaxFileSizeMb);

        if ($maxMb > 0 && $size > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => __('This file exceeds the maximum allowed size of :max MB.', ['max' => (string) $maxMb]),
            ]);
        }

        $folder = isset($validated['folder_id'])
            ? Folder::query()->whereKey($validated['folder_id'])->first()
            : null;

        abort_unless(Folder::uploadableBy($user, $folder), 403);

        // Inert for a staff token — the quota is a client-portal concept —
        // but the check belongs here rather than being added later when
        // client tokens land and this path silently becomes a way around it.
        if ($user->isClient()) {
            $quotaBytes = $this->storageUsage->quotaBytes($user);

            if ($quotaBytes > 0 && $this->storageUsage->usedBytes($user) + $size > $quotaBytes) {
                throw ValidationException::withMessages([
                    'file' => __('This upload would exceed your storage quota of :quota MB.', [
                        'quota' => (string) $this->storageUsage->quotaMb($user),
                    ]),
                ]);
            }
        }

        if (! $this->extensionPolicy->isAllowed($user, $upload->getClientOriginalName())) {
            throw ValidationException::withMessages([
                'file' => __('This file type is not allowed for upload.'),
            ]);
        }

        $diskEvent = new ResolvingUploadDisk($user);
        Event::dispatch($diskEvent);
        $disk = $diskEvent->disk;

        $path = $upload->storeAs(
            now()->format('Y/m'),
            Str::uuid()->toString().'.'.strtolower($upload->getClientOriginalExtension()),
            $disk,
        );

        abort_unless(is_string($path), 500);

        $file = $this->storeFile->create(
            uploader: $user,
            originalName: $upload->getClientOriginalName(),
            path: $path,
            // From the bytes, never from the request. A caller controls the
            // Content-Type it declares, and the mime type decides how this
            // file is later served and previewed.
            mimeType: $upload->getMimeType() ?? 'application/octet-stream',
            size: $size,
            checksum: hash_file('sha256', $upload->getRealPath()) ?: '',
            name: $validated['name'] ?? null,
            description: $validated['description'] ?? null,
            folderId: $validated['folder_id'] ?? null,
            disk: $disk,
        );

        return (new FileResource($file->load(['folder', 'uploader', 'categories'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a file's metadata.
     *
     * Only the fields present in the request are changed; omitting one
     * leaves it as it was.
     *
     * Some fields need a permission of their own — `expires_at` needs
     * `set_file_expiration_date`, `public` needs `upload_public`, and
     * `categories` needs `set_file_categories`. Sending one of those
     * without the matching permission leaves that field untouched rather
     * than failing the whole request, which mirrors the web interface.
     *
     * `commentable` only has an effect while the installation's comment
     * setting is "only files marked as commentable"; under any other
     * setting it is ignored, again rather than failing.
     */
    public function update(Request $request, File $file): FileResource
    {
        Gate::authorize('update', $file);

        $user = $request->user();
        assert($user !== null);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'folder_id' => ['sometimes', ...Rules::folderId()],
            'public' => ['sometimes', 'boolean'],
            'commentable' => ['sometimes', 'boolean'],
            'slug' => Rules::slug('files', $file->id),
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'download_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'download_limit_scope' => ['sometimes', Rule::enum(DownloadLimitScope::class)],
        ]);

        // Reparenting through update() must respect the same library scope as
        // the web move()/bulkUpdate() paths: the destination folder must be
        // one this user can see. Only enforced when folder_id actually
        // changes, so re-saving a file that already sits in an out-of-scope
        // folder (reachable via a direct client share) still works. The
        // integer rule admits numeric strings, so cast before the strict
        // change comparison.
        if (array_key_exists('folder_id', $validated) && $validated['folder_id'] !== null) {
            $validated['folder_id'] = (int) $validated['folder_id'];

            if ($validated['folder_id'] !== $file->folder_id) {
                $this->scope->folders($user)->findOrFail($validated['folder_id']);
            }
        }

        $attributes = array_intersect_key($validated, array_flip(['name', 'description', 'folder_id']));

        if (array_key_exists('expires_at', $validated) && $user->can('set_file_expiration_date')) {
            $attributes['expires_at'] = $validated['expires_at'];
        }

        if (array_key_exists('download_limit', $validated) && $user->can('limit_downloads')) {
            $attributes['download_limit'] = $validated['download_limit'];
        }

        if (array_key_exists('download_limit_scope', $validated) && $user->can('limit_downloads')) {
            $attributes['download_limit_scope'] = $validated['download_limit_scope'];
        }

        if (array_key_exists('commentable', $validated) && $this->commenting->scope() === CommentScope::SelectedFiles) {
            $attributes['commentable'] = $validated['commentable'];
        }

        $wasPublic = $file->public;

        if (array_key_exists('public', $validated) && $user->can('upload_public')) {
            $attributes['public'] = $validated['public'];
            $attributes['slug'] = ($validated['slug'] ?? '') ?: ($file->slug ?: File::uniqueSlugFrom($validated['name'] ?? $file->name, $file->id));
        }

        $file->update($attributes);

        if (array_key_exists('categories', $validated) && $user->can('set_file_categories')) {
            $file->categories()->sync($validated['categories']);
        }

        $this->activity->log(Action::FileUpdated, subject: $file);

        if (! $wasPublic && $file->public) {
            $this->activity->log(Action::FileMadePublic, subject: $file, context: ['slug' => $file->slug]);
        } elseif ($wasPublic && ! $file->public) {
            $this->activity->log(Action::FileMadePrivate, subject: $file);
        }

        return new FileResource($file->fresh()?->load(['folder', 'uploader', 'categories']) ?? $file);
    }

    public function destroy(File $file): JsonResponse
    {
        Gate::authorize('delete', $file);

        $name = $file->name;
        $file->delete();

        $this->activity->log(Action::FileDeleted, context: ['name' => $name]);

        return response()->json(status: 204);
    }
}
