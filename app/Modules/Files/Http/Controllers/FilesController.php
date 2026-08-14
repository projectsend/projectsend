<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Comments\CommentingRules;
use App\Modules\Comments\CommentScope;
use App\Modules\Files\Access\ShareTargets;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\DownloadLimitScope;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Files\Storage\ResolvingUploadDisk;
use App\Modules\Files\Uploads\StoreUploadedFile;
use App\Modules\Files\Uploads\UploadExtensionPolicy;
use App\Modules\Files\Versions\FileVersionLinks;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Platform\Localization\LocalDay;
use App\Modules\Platform\Localization\TimezoneRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\PublicUrl;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FilesController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly StaffLibraryScope $scope,
        private readonly PublicUrl $publicUrl,
        private readonly ShareTargets $shareTargets,
        private readonly CommentingRules $commenting,
        private readonly FileVersions $versions,
        private readonly FileVersionLinks $versionLinks,
        private readonly TimezoneRegistry $timezones,
    ) {}

    public function create(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        return Inertia::render('files/create', [
            'max_file_size_mb' => app(Settings::class)->get(Setting::MaxFileSizeMb),
            'part_size_mb' => (int) config('projectsend.upload_part_size_mb'),
            'allowed_extensions' => app(UploadExtensionPolicy::class)->hintFor($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Intake is a plain upload for now; the resumable
            // direct-to-storage flow replaces this without touching the
            // domain (brief §3, open question §12.1).
            'file' => ['required', 'file', 'max:102400'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        /** @var UploadedFile $upload */
        $upload = $validated['file'];

        $maxMb = (int) app(Settings::class)->get(Setting::MaxFileSizeMb);

        if ($maxMb > 0 && (int) $upload->getSize() > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => __('This file exceeds the maximum allowed size of :max MB.', ['max' => (string) $maxMb]),
            ]);
        }

        $user = $request->user();
        assert($user !== null);

        if (! app(UploadExtensionPolicy::class)->isAllowed($user, $upload->getClientOriginalName())) {
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

        $file = app(StoreUploadedFile::class)->create(
            uploader: $user,
            originalName: $upload->getClientOriginalName(),
            path: $path,
            mimeType: $upload->getMimeType() ?? 'application/octet-stream',
            size: (int) $upload->getSize(),
            checksum: hash_file('sha256', $upload->getRealPath()) ?: '',
            name: $validated['name'] ?? null,
            description: $validated['description'] ?? null,
            folderId: $validated['folder_id'] ?? null,
            disk: $disk,
        );

        return redirect()->route('files.edit', $file)->with('success', __('File uploaded.'));
    }

    public function edit(Request $request, File $file): Response
    {
        $viewer = $request->user();
        assert($viewer !== null);

        Gate::forUser($viewer)->authorize('view', $file);

        // A revision's recipients belong to the file it revises, so the
        // Sharing tab has to point at that file rather than offer controls
        // that would be ignored. Resolved here (not in the page) because
        // "can this staffer edit the original" is a policy question — the
        // original may be a colleague's upload, or outside a scoped
        // staffer's library, and linking to a page they'd get a 404 from is
        // worse than saying so.
        $sharingRoot = $file->isRevision()
            ? File::query()->find($file->sharingOwnerId())
            : null;

        return Inertia::render('files/edit', [
            'file' => [
                'id' => $file->id,
                'name' => $file->name,
                'description' => $file->description,
                'original_name' => $file->original_name,
                'size' => $file->size,
                'mime_type' => $file->mime_type,
                'uploader' => $file->uploader?->name,
                'folder_id' => $file->folder_id,
                'public' => $file->public,
                'commentable' => $file->commentable,
                'slug' => $file->slug,
                // The date picker's value, so it has to be the same
                // calendar date the editor typed — read back in their
                // zone, not the server's, or a file set to expire on the
                // 12th reopens showing the 11th.
                'expires_at' => $file->expires_at?->copy()->setTimezone($this->timezones->resolve($request->user()))->toDateString(),
                'expired' => $file->isExpired(),
                'download_limit' => $file->download_limit,
                'download_limit_scope' => ($file->download_limit_scope ?? DownloadLimitScope::Total)->value,
                // The file's total downloads, so the editor can see what
                // a total limit is being measured against. A per-user
                // limit is a different number for every person, which no
                // single figure on this screen can show.
                'downloads_used' => $file->downloads()->count(),
                'public_url' => $file->isEffectivelyPublic()
                    ? $this->publicUrl->for($file)
                    : null,
                'public_via_folder' => $file->folder?->publicSourceName(),
                'created_at' => $file->created_at?->toIso8601String(),
                'is_revision' => $file->isRevision(),
                'version' => $this->versionLinks->for(
                    $file,
                    $viewer,
                    fn (File $other): string => route('files.edit', $other, false),
                ),
            ],
            'sharing_root' => $sharingRoot === null ? null : [
                'id' => $sharingRoot->id,
                'name' => $sharingRoot->name,
                'url' => route('files.edit', $sharingRoot, false).'?tab=sharing',
            ],
            'can_update_root' => $sharingRoot !== null && Gate::forUser($viewer)->allows('update', $sharingRoot),
            'can_set_version' => Gate::forUser($viewer)->allows('setVersion', $file),
            'version_chain' => $this->versions->chain($file, $viewer)
                ->map(fn (File $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'url' => route('files.edit', $member, false),
                    'is_current' => $member->id === $file->id,
                ])->values(),
            'folder_options' => Folder::query()->orderBy('path')->orderBy('name')->get()
                ->map(fn (Folder $folder): array => ['id' => $folder->id, 'name' => $folder->name])->all(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color'])
                ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name, 'color' => $category->color])->all(),
            'assigned_category_ids' => $file->categories()->pluck('categories.id')->map(fn ($id): int => (int) $id)->all(),
            'can_set_categories' => $viewer->can('set_file_categories'),
            'can_update' => Gate::forUser($viewer)->allows('update', $file),
            'can_delete' => Gate::forUser($viewer)->allows('delete', $file),
            'can_manage_public' => $viewer->can('upload_public'),
            // The per-file switch only does anything while the comment
            // scope is `selected`; under every other value the page hides
            // it rather than offer a control with no current effect.
            'can_set_commentable' => $this->commenting->scope() === CommentScope::SelectedFiles,
            // The file's own page carries the conversation too, not just
            // the library's slide-over — it is where the activity log's
            // "View" link and a comment notification both land.
            'comments_enabled' => $this->commenting->enabled(),
            ...$this->shareTargets->forSubject($file, $viewer),
            'share_links' => $file->shareLinks()->orderByDesc('created_at')->get()
                ->map(fn (ShareLink $link): array => [
                    'id' => $link->id,
                    'url' => route('share.show', $link->token),
                    'expires_at' => $link->expires_at?->toIso8601String(),
                    'max_downloads' => $link->max_downloads,
                    'downloads_count' => $link->downloads_count,
                    'revoke_url' => route('share-links.destroy', $link, false),
                ])->values(),
            'share_link_store_url' => route('files.share-links.store', $file, false),
            'can_set_expiration' => $viewer->can('set_file_expiration_date'),
            'can_limit_downloads' => $viewer->can('limit_downloads'),
        ]);
    }

    public function update(Request $request, File $file): RedirectResponse
    {
        Gate::authorize('update', $file);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'public' => ['sometimes', 'boolean'],
            'commentable' => ['sometimes', 'boolean'],
            // The slug only matters (and is only shown) once a file is
            // public — otherwise fall back to one derived from the name.
            'slug' => Rules::slug('files', $file->id),
            'categories' => ['array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'expires_at' => ['nullable', 'date'],
            'download_limit' => ['nullable', 'integer', 'min:1'],
            'download_limit_scope' => ['nullable', Rule::enum(DownloadLimitScope::class)],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'folder_id' => $validated['folder_id'] ?? null,
        ];

        // Only meaningful while the comment scope is `selected`, and only
        // offered by the page then — but a request reaching here directly
        // must not be able to set a flag the UI is currently hiding, the
        // same shape as the upload_public gate below.
        if ($this->commenting->scope() === CommentScope::SelectedFiles) {
            $attributes['commentable'] = $validated['commentable'] ?? $file->commentable;
        }

        // Only a user who can set expiration dates may change this file's
        // own expiry — same "leave it alone if you lack the permission"
        // rule as the upload_public gate below.
        if ($request->user()?->can('set_file_expiration_date') === true) {
            $attributes['expires_at'] = $this->expiryInstant($validated['expires_at'] ?? null, $request->user());
        }

        // Same rule again for the download cap, behind its own
        // permission — the one that already gates a share link's
        // max_downloads, since both are the same question asked about
        // different objects.
        if ($request->user()?->can('limit_downloads') === true) {
            $attributes['download_limit'] = $validated['download_limit'] ?? null;
            $attributes['download_limit_scope'] = $validated['download_limit_scope'] ?? DownloadLimitScope::Total->value;
        }

        $wasPublic = $file->public;

        // Only a user who can manage public state may change it — a user
        // who can edit a file but lacks upload_public leaves its public
        // state exactly as it was, same rule as FoldersController::update.
        if ($request->user()?->can('upload_public') === true) {
            $attributes['public'] = $validated['public'] ?? $file->public;
            // Omitting the field on an update leaves the current slug
            // alone — it must not silently change just because the name
            // did.
            $attributes['slug'] = ($validated['slug'] ?? '') ?: ($file->slug ?: File::uniqueSlugFrom($validated['name'], $file->id));
        }

        $file->update($attributes);

        // Categories are gated by their own permission; leave them untouched
        // for a user who can edit the file but not set categories.
        if ($request->user()?->can('set_file_categories') === true) {
            $file->categories()->sync($validated['categories'] ?? []);
        }

        $this->activity->log(Action::FileUpdated, subject: $file);

        if (! $wasPublic && $file->public) {
            $this->activity->log(Action::FileMadePublic, subject: $file, context: ['slug' => $file->slug]);
        } elseif ($wasPublic && ! $file->public) {
            $this->activity->log(Action::FileMadePrivate, subject: $file);
        }

        return back()->with('success', __('File updated.'));
    }

    /**
     * Reparent a file into a folder (or the root) — the drag-and-drop
     * move. Unlike update() this touches only the folder, so it needs no
     * name/description payload.
     */
    public function move(Request $request, File $file): RedirectResponse
    {
        Gate::authorize('update', $file);

        $validated = $request->validate([
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        $folderId = $validated['folder_id'] ?? null;
        $user = $request->user();

        // The target folder must be one the mover can actually see.
        if ($folderId !== null && $user !== null) {
            $this->scope->folders($user)->findOrFail($folderId);
        }

        $file->update(['folder_id' => $folderId]);

        $this->activity->log(Action::FileUpdated, subject: $file);

        return back();
    }

    /**
     * WordPress-style "Bulk Edit": one shared set of changes applied to
     * every selected file. Every field defaults to "no change" via its own
     * *_action sentinel, so a field left untouched in the dialog truly
     * isn't touched here — critical for categories, where naively applying
     * an empty list would wipe every file's existing categories instead of
     * leaving them alone.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $validated = $request->validate([
            'file_ids' => ['required', 'array', 'min:1'],
            'file_ids.*' => ['integer', 'distinct'],

            'folder_action' => ['required', Rule::in(['no_change', 'move'])],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],

            'description_action' => ['required', Rule::in(['no_change', 'set'])],
            'description' => ['nullable', 'string', 'max:2000'],

            'expiration_action' => ['required', Rule::in(['no_change', 'set', 'clear'])],
            'expires_at' => ['nullable', 'date', 'required_if:expiration_action,set'],

            // `sometimes` rather than `required` like the fields above:
            // a browser still running the previous build would start
            // getting 422s on every bulk edit the moment this deployed.
            'download_limit_action' => ['sometimes', Rule::in(['no_change', 'set', 'clear'])],
            'download_limit' => ['nullable', 'integer', 'min:1', 'required_if:download_limit_action,set'],
            'download_limit_scope' => ['nullable', Rule::enum(DownloadLimitScope::class)],

            'add_category_ids' => ['array'],
            'add_category_ids.*' => ['integer', 'exists:categories,id'],
            'remove_category_ids' => ['array'],
            'remove_category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $touchesNothing = $validated['folder_action'] === 'no_change'
            && $validated['description_action'] === 'no_change'
            && $validated['expiration_action'] === 'no_change'
            && ($validated['download_limit_action'] ?? 'no_change') === 'no_change'
            && ($validated['add_category_ids'] ?? []) === []
            && ($validated['remove_category_ids'] ?? []) === [];
        abort_if($touchesNothing, 422, __('Change at least one field before applying a bulk edit.'));

        // The target folder must be one this user can actually see — same
        // rule move() already applies to a single file's target.
        $targetFolderId = null;
        if ($validated['folder_action'] === 'move') {
            $targetFolderId = $validated['folder_id'] ?? null;
            if ($targetFolderId !== null) {
                $this->scope->folders($user)->findOrFail($targetFolderId);
            }
        }

        // Silently drop anything this user isn't allowed to edit — same
        // convention as ZipDownloadsController::store's Gate::allows filter
        // — rather than 403ing the whole batch over one file.
        $files = File::query()->whereIn('id', $validated['file_ids'])->get()
            ->filter(fn (File $file): bool => Gate::forUser($user)->allows('update', $file));

        abort_if($files->isEmpty(), 422, __('None of the selected files could be edited.'));

        $canSetExpiration = $user->can('set_file_expiration_date');
        $canLimitDownloads = $user->can('limit_downloads');
        $canSetCategories = $user->can('set_file_categories');
        $addCategoryIds = $validated['add_category_ids'] ?? [];
        $removeCategoryIds = $validated['remove_category_ids'] ?? [];
        $updated = 0;

        DB::transaction(function () use ($files, $user, $validated, $targetFolderId, $canSetExpiration, $canLimitDownloads, $canSetCategories, $addCategoryIds, $removeCategoryIds, &$updated): void {
            foreach ($files as $file) {
                $attributes = [];

                if ($validated['folder_action'] === 'move') {
                    $attributes['folder_id'] = $targetFolderId;
                }

                if ($validated['description_action'] === 'set') {
                    $attributes['description'] = $validated['description'] ?? null;
                }

                // Same "leave it alone if you lack the permission" rule as
                // update()'s expires_at handling.
                if ($validated['expiration_action'] !== 'no_change' && $canSetExpiration) {
                    $attributes['expires_at'] = $validated['expiration_action'] === 'set'
                        ? $this->expiryInstant($validated['expires_at'], $user)
                        : null;
                }

                // Same rule again, behind its own permission.
                $limitAction = $validated['download_limit_action'] ?? 'no_change';

                if ($limitAction !== 'no_change' && $canLimitDownloads) {
                    $setting = $limitAction === 'set';
                    $attributes['download_limit'] = $setting ? (int) $validated['download_limit'] : null;
                    $attributes['download_limit_scope'] = $setting
                        ? ($validated['download_limit_scope'] ?? DownloadLimitScope::Total->value)
                        : DownloadLimitScope::Total->value;
                }

                if ($attributes !== []) {
                    $file->update($attributes);
                }

                // Add/remove, never sync() — bulk edit must never wipe
                // categories the admin didn't ask to touch.
                $categoriesTouched = false;
                if ($canSetCategories) {
                    if ($addCategoryIds !== []) {
                        $file->categories()->syncWithoutDetaching($addCategoryIds);
                        $categoriesTouched = true;
                    }
                    if ($removeCategoryIds !== []) {
                        $file->categories()->detach($removeCategoryIds);
                        $categoriesTouched = true;
                    }
                }

                if ($attributes !== [] || $categoriesTouched) {
                    $this->activity->log(Action::FileUpdated, subject: $file);
                    $updated++;
                }
            }
        });

        $requested = count($validated['file_ids']);
        $message = $updated < $requested
            ? __(':updated of :requested selected files were updated. The rest were skipped because you don\'t have permission to edit them.', ['updated' => $updated, 'requested' => $requested])
            : trans_choice(':count file updated.|:count files updated.', $updated, ['count' => $updated]);

        return back()->with('success', $message);
    }

    public function destroy(File $file): RedirectResponse
    {
        Gate::authorize('delete', $file);

        $name = $file->name;
        // Soft delete; the bytes stay on disk until a purge policy
        // lands with the retention work.
        $file->delete();

        $this->activity->log(Action::FileDeleted, context: ['name' => $name]);

        return redirect()->route('files.index')->with('success', __('File deleted.'));
    }

    /**
     * The instant a `<input type="date">` expiry actually falls on.
     *
     * The form posts a bare `YYYY-MM-DD`, which Eloquent would otherwise
     * store as midnight UTC — so "expires on the 12th" would cut the file
     * off partway through the 11th for anyone in the Americas, and give
     * anyone east of Greenwich most of a day they were not promised. It
     * means the end of the 12th where the person setting it lives.
     */
    private function expiryInstant(?string $date, ?User $setter): ?Carbon
    {
        return $date === null
            ? null
            : LocalDay::end($date, $this->timezones->resolve($setter));
    }
}
