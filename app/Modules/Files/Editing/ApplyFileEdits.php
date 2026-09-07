<?php

declare(strict_types=1);

namespace App\Modules\Files\Editing;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Comments\CommentingRules;
use App\Modules\Comments\CommentScope;
use App\Modules\Files\Models\File;

/**
 * The one place that decides which fields an editor may actually write.
 *
 * Three surfaces edit a file — the staff editor, `/api/v1/files/{file}`,
 * and now a client's own uploads in the portal — and they had grown two
 * copies of the same eight permission checks with a third about to be
 * written. The checks are not hard; the problem is that they are *easy*,
 * so a new field gets added to one caller and the drift is invisible until
 * somebody finds the surface where the gate is missing.
 *
 * The split is deliberate: **callers normalise, this gates.** A caller
 * turns its own request shape into `$changes` — form semantics versus the
 * API's `sometimes`, a date string versus an instant — and this decides
 * what the actor is allowed to write, writes it, and records what happened.
 *
 * `$changes` uses array_key_exists semantics throughout: a key that is
 * absent is left alone, a key present with `null` is written as null. That
 * is the API's existing contract, and the web forms post every field they
 * own, so it is also the forms'.
 *
 * Two things deliberately do NOT live here, because they are the caller's
 * and getting them wrong is how a boundary breaks:
 *
 * - **Whether this actor may edit this file at all.** That is
 *   `Gate::authorize('update', $file)` and FilePolicy. Nothing below
 *   re-checks it.
 * - **Whether a destination folder is reachable.** Staff ask
 *   StaffLibraryScope; a client asks `Folder::uploadableBy()`. Those are
 *   different questions with the same shape, and the staff one answers
 *   `true` for any client — see FilePolicy::update()'s note.
 */
class ApplyFileEdits
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly CommentingRules $commenting,
    ) {}

    /**
     * @param  array<string, mixed>  $changes  only the fields the caller
     *                                         wants written; absent keys
     *                                         are left as they are
     */
    public function apply(User $actor, File $file, array $changes): void
    {
        $attributes = [];

        // Covered by the permission to edit the file at all, which the
        // policy has already settled by the time anything reaches here.
        foreach (['name', 'description', 'folder_id'] as $field) {
            if (array_key_exists($field, $changes)) {
                $attributes[$field] = $changes[$field];
            }
        }

        // Only meaningful while the comment scope is `selected`, and only
        // offered by a form then — but a request reaching here directly
        // must not be able to set a flag the UI is currently hiding.
        if (array_key_exists('commentable', $changes) && $this->commenting->scope() === CommentScope::SelectedFiles) {
            $attributes['commentable'] = $changes['commentable'];
        }

        // From here down, every field has a permission of its own, and the
        // rule for all of them is the same: lacking it leaves the field
        // exactly as it was rather than failing the request. An editor who
        // may rename a file but not publish it saves a rename, and the
        // public state does not move. The web and the API have always
        // behaved this way; it is why the portal can reuse both forms.
        if (array_key_exists('expires_at', $changes) && $actor->can('set_file_expiration_date')) {
            $attributes['expires_at'] = $changes['expires_at'];
        }

        if (array_key_exists('download_limit', $changes) && $actor->can('limit_downloads')) {
            $attributes['download_limit'] = $changes['download_limit'];
        }

        if (array_key_exists('download_limit_scope', $changes) && $actor->can('limit_downloads')) {
            $attributes['download_limit_scope'] = $changes['download_limit_scope'];
        }

        $wasPublic = $file->public;

        if (array_key_exists('public', $changes) && $actor->can('upload_public')) {
            $attributes['public'] = $changes['public'];

            // A caller that offers the slug passes what was submitted; one
            // that does not simply omits the key and gets a derived slug.
            // The client portal is the second kind on purpose — an
            // installation-wide unique slug chosen by a client is a name to
            // squat and an existence oracle to probe, for no benefit over a
            // slug made from the name they already chose.
            //
            // Omitting the slug on an update keeps the current one: it must
            // not silently change just because the name did.
            $submitted = is_string($changes['slug'] ?? null) ? trim($changes['slug']) : '';

            $attributes['slug'] = $submitted !== ''
                ? $submitted
                : ($file->slug ?: File::uniqueSlugFrom(
                    is_string($changes['name'] ?? null) ? $changes['name'] : $file->name,
                    $file->id,
                ));
        }

        $file->update($attributes);

        // After the write, not inside it: categories are a relation, not a
        // column. Gated by their own key, so an editor who may rename but
        // not categorise leaves them untouched.
        if (array_key_exists('categories', $changes) && $actor->can('set_file_categories')) {
            $file->categories()->sync($changes['categories']);
        }

        $this->activity->log(Action::FileUpdated, subject: $file);

        // Publishing and unpublishing are their own entries. A file
        // becoming reachable without a login is not a detail of "file
        // updated", and it is the line an audit is most likely to be read
        // for.
        if (! $wasPublic && $file->public) {
            $this->activity->log(Action::FileMadePublic, subject: $file, context: ['slug' => $file->slug]);
        } elseif ($wasPublic && ! $file->public) {
            $this->activity->log(Action::FileMadePrivate, subject: $file);
        }
    }
}
