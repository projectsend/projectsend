<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\ClientStorageUsage;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Notifications\AdminClientUploadedNotification;
use App\Modules\Files\Uploads\LocalPartStore;
use App\Modules\Files\Uploads\PartTooLargeException;
use App\Modules\Files\Uploads\StoreUploadedFile;
use App\Modules\Files\Uploads\UploadExtensionPolicy;
use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\UserType;
use App\Modules\Notifications\Notifier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\Rules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The resumable upload contract (Uppy's aws-s3 multipart protocol
 * shape): create session, sign part URLs, receive parts, list parts
 * for resume, complete (assemble + File record), abort. On installs
 * without object storage the part URLs point back at this app; the
 * cloud edition hands out real presigned S3 URLs behind the same
 * contract.
 */
class ChunkedUploadsController extends Controller
{
    public function __construct(
        private readonly LocalPartStore $parts,
        private readonly Settings $settings,
        private readonly StoreUploadedFile $storeFile,
        private readonly UploadExtensionPolicy $extensionPolicy,
        private readonly ClientStorageUsage $storageUsage,
        private readonly Notifier $notifier,
        private readonly PermissionChecker $permissions,
        private readonly ActivityLogger $activity,
        private readonly FileVersions $versions,
    ) {}

    /**
     * Begin a resumable upload.
     *
     * Declare the filename and size; the response returns an `uploadId`
     * used by the remaining steps. The declared size is re-checked against
     * the assembled bytes when the upload completes.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'folder_id' => Rules::folderId(),
            'previous_file_id' => ['nullable', 'integer'],
        ]);

        $maxMb = (int) $this->settings->get(Setting::MaxFileSizeMb);

        if ($maxMb > 0 && (int) $validated['size'] > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages([
                'size' => __('This file exceeds the maximum allowed size of :max MB.', ['max' => (string) $maxMb]),
            ]);
        }

        $user = $request->user();
        assert($user !== null);

        $folder = isset($validated['folder_id']) ? Folder::query()->whereKey($validated['folder_id'])->first() : null;
        abort_unless(Folder::uploadableBy($user, $folder), 403);

        // One session per file, and a person uploads a handful at a time.
        // A cap is here because nothing else counts sessions: for anyone
        // without a quota to spend — staff, and clients on an installation
        // that sets no quotas — the number of sessions is the only thing
        // standing between a declared size and any multiple of it.
        $openSessions = UploadSession::query()->where('user_id', $user->id)->count();
        $maxOpen = max(1, (int) config('projectsend.uploads.max_open_sessions'));

        if ($openSessions >= $maxOpen) {
            throw ValidationException::withMessages([
                'filename' => __('Too many uploads are already in progress. Finish or cancel one and try again.'),
            ]);
        }

        // The declared size here is client-supplied and unverified until
        // complete()'s real assembled byte count — re-checked there too.
        if ($user->isClient()) {
            $quotaBytes = $this->storageUsage->quotaBytes($user);

            // Sessions already open count too, at the size they declared.
            // A quota measured against stored files alone is spent twice
            // over by opening the sessions one after another: each one is
            // told there is room, because the ones before it had not
            // finished and so had not become files. putPart() holds each
            // session to its declaration, so reserving the declarations
            // here is what puts bytes waiting on the temporary volume
            // under the same ceiling as bytes that landed.
            $pendingBytes = (int) UploadSession::query()->where('user_id', $user->id)->sum('size');

            if ($quotaBytes > 0 && $this->storageUsage->usedBytes($user) + $pendingBytes + (int) $validated['size'] > $quotaBytes) {
                throw ValidationException::withMessages([
                    'size' => __('This upload would exceed your storage quota of :quota MB.', [
                        // The resolved quota, not the column: a client who
                        // was never given one of their own carries 0 there
                        // and inherits the site default, so printing the
                        // column reads "your storage quota of 0 MB" at the
                        // moment somebody is asking what their limit is.
                        'quota' => (string) $this->storageUsage->quotaMb($user),
                    ]),
                ]);
            }
        }

        if (! $this->extensionPolicy->isAllowed($user, $validated['filename'])) {
            throw ValidationException::withMessages([
                'filename' => __('This file type is not allowed for upload.'),
            ]);
        }

        // Checked here, before a single byte is sent, so a rejected pick
        // costs nothing. FileVersions::link() re-checks it at complete()
        // through FilePolicy::setVersion — that is the boundary; this is
        // the courtesy.
        $previousFileId = null;
        if (($validated['previous_file_id'] ?? null) !== null) {
            $previousFileId = $this->resolvableOriginal($user, (int) $validated['previous_file_id'])?->id;

            if ($previousFileId === null) {
                throw ValidationException::withMessages([
                    'previous_file_id' => __('That file can\'t be used as the previous version.'),
                ]);
            }
        }

        $session = UploadSession::query()->create([
            'user_id' => $user->id,
            'folder_id' => $folder?->id,
            'previous_file_id' => $previousFileId,
            'original_name' => $validated['filename'],
            'size' => (int) $validated['size'],
            'mime_type' => $validated['type'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'uploadId' => $session->id,
            'key' => $session->id,
        ]);
    }

    /**
     * Get a short-lived signed URL for one part.
     */
    public function signPart(Request $request, UploadSession $session, int $part): JsonResponse
    {
        $this->authorizeSession($request, $session);

        abort_unless($part >= 1 && $part <= 10000, 422);

        return response()->json([
            'url' => $this->parts->signPartUrl($session, $part, $this->partRouteName($request)),
            'method' => 'PUT',
        ]);
    }

    /**
     * This controller is mounted twice — on the session-authenticated web
     * routes and on the token-authenticated API twins — and a signed URL
     * must name the route the caller can actually reach. A browser handed
     * an /api/v1 URL would have no bearer token; an API client handed the
     * web URL would have no session.
     */
    private function partRouteName(Request $request): string
    {
        return $request->is('api/*') ? 'api.uploads.parts.put' : 'uploads.parts.put';
    }

    /**
     * Upload one part.
     *
     * PUT the part's raw bytes to the signed URL returned by the sign
     * endpoint. Parts may be sent in any order, and the response's `ETag`
     * identifies the stored part.
     */
    public function putPart(Request $request, UploadSession $session, int $part): Response
    {
        // The signature is the grant here (presigned semantics), but
        // ownership of the session is still enforced below.
        $this->authorizeSession($request, $session);

        // signPart() bounds the part number; bound the part body too.
        abort_unless($part >= 1 && $part <= 10000, 422);

        $maxPartBytes = max(1, (int) config('projectsend.upload_part_size_mb')) * 1024 * 1024;
        // Allow a little slack over the advertised part size: the client
        // chooses its own chunking and only the last part is short.
        $limit = $maxPartBytes * 2;

        if ($request->header('Content-Length') !== null && (int) $request->header('Content-Length') > $limit) {
            abort(413);
        }

        // Bounding one request bounds one request, and nothing else. Ten
        // thousand part numbers at twice a 20 MB part is about 400 GB per
        // session, sessions were not counted against anything, and none of
        // it becomes a File row — so a client with a 1 MB quota could
        // declare a one-byte upload and fill the temporary volume, then do
        // it again. The session needs a ceiling of its own, and the room
        // for a part has to be claimed before the part is read: a body's
        // length is not known until it has arrived, and by then it is on
        // the disk this is protecting.
        //
        // The ceiling is the size the session declared, which store() has
        // already weighed against the file-size limit and the quota. So
        // what a part gets is whatever the session has left, and the write
        // is then capped at exactly that — an over-long body is cut off
        // mid-stream as it always was, just against a smaller number.
        $reserve = $this->reservePartRoom($session, $part, $limit);

        if ($reserve < 1) {
            // 413 rather than 422: this is about the size of what is being
            // sent, and a client's resume logic already understands it. The
            // session survives — the parts it holds are untouched, and it
            // can still be completed or aborted.
            abort(413);
        }

        $stream = $request->getContent(true);

        try {
            $etag = $this->parts->storePart($session, $part, $stream, $reserve);
        } catch (PartTooLargeException) {
            abort(413);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            // In the finally, because every way out of here needs it: the
            // refused part was deleted and weighs nothing, a client that
            // hung up left a short one, and a clean write leaves exactly
            // what it reserved. Without this a client's own retries would
            // slowly exhaust a session that has plenty of room.
            $session->settleStaged($reserve, $this->parts->partSize($session, $part));
        }

        return response('', 200, [
            'ETag' => '"'.$etag.'"',
            // Stated rather than left to Symfony, whose default for an
            // empty body is text/html. A CDN that rewrites HTML — as
            // Cloudflare's Email Obfuscation and Automatic HTTPS Rewrites
            // do — drops the origin's ETag from an HTML response, since a
            // rewritten body would no longer match it. Nothing on this side
            // would notice (LocalPartStore keeps its own record of every
            // part), but the client never learns the part landed, so the
            // upload stalls at 100% with no error anywhere.
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * Claim room for one part, returning how many bytes were claimed — 0
     * when the session has none left.
     *
     * Read-then-claim, under a lock held for the two statements and not
     * for the transfer. The protocol sends parts in parallel and how many
     * is the client's choice, so without it every part in flight reads the
     * same "room left" and they all claim it; and making the claim alone
     * atomic is no better, because then the honest parallel upload is the
     * one that gets refused. The lock is the same per-session shape
     * complete() already uses, and it is released before a byte is read.
     */
    private function reservePartRoom(UploadSession $session, int $part, int $limit): int
    {
        $lock = Cache::lock('upload-part:'.$session->id, 30);

        try {
            $lock->block(15);
        } catch (LockTimeoutException) {
            // Nothing is wrong with the upload — the queue for this one
            // session just did not clear. 503 with Retry-After is what the
            // client's own backoff is for.
            abort(503, headers: ['Retry-After' => '5']);
        }

        try {
            $existing = $this->parts->partSize($session, $part);

            $session->refresh();

            // Re-sending a part replaces it rather than adding to it, so
            // what it already holds is room this request may spend again.
            // That is an ordinary resume.
            $room = max(0, $session->size - ($session->staged_bytes - $existing));
            $reserve = min($limit, $room);

            if ($reserve > 0 && ! $session->reserveStaged($reserve, $existing)) {
                return 0;
            }

            return $reserve;
        } finally {
            $lock->release();
        }
    }

    /**
     * List the parts already received, so an interrupted upload can resume
     * rather than start again.
     */
    public function listParts(Request $request, UploadSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        return response()->json($this->parts->listParts($session));
    }

    public function complete(Request $request, UploadSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        $user = $request->user();
        assert($user !== null);

        // Serialise completion per session: two concurrent completes (an Uppy
        // retry, a double submit, a lost-connection resend) would otherwise
        // both assemble into the one target file and create two File rows.
        // The lock's TTL releases the claim if a completion dies mid-flight,
        // so a later retry still works.
        $lock = Cache::lock('upload-complete:'.$session->id, 120);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'parts' => __('This upload is already being finalised.'),
            ]);
        }

        try {
            return $this->finalise($session, $user);
        } finally {
            $lock->release();
        }
    }

    /**
     * Assemble a session's received parts into a stored File. Runs under
     * complete()'s per-session lock, so it is the single writer to the
     * session's target path and the only creator of its File row.
     */
    private function finalise(UploadSession $session, User $user): JsonResponse
    {
        $extension = strtolower(pathinfo($session->original_name, PATHINFO_EXTENSION));
        $targetPath = now()->format('Y/m').'/'.Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');

        try {
            $assembled = $this->parts->assemble($session, $targetPath);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['parts' => $exception->getMessage()]);
        }

        // store()'s size check ran against the client-declared, unverified
        // size, so a small declared size would otherwise let an upload of any
        // size through here. Re-check the real assembled byte count against
        // the same limit store() applies to everyone. No File row exists yet
        // at this point, so cleanup only needs to undo what assemble() wrote.
        $maxMb = (int) $this->settings->get(Setting::MaxFileSizeMb);

        if ($maxMb > 0 && $assembled['size'] > $maxMb * 1024 * 1024) {
            Storage::disk($assembled['disk'])->delete($assembled['path']);
            $session->delete();

            throw ValidationException::withMessages([
                'size' => __('This file exceeds the maximum allowed size of :max MB.', ['max' => (string) $maxMb]),
            ]);
        }

        // store()'s quota check used a client-declared, unverified size —
        // re-check against the real assembled byte count before this
        // becomes a File row. No File row exists yet at this point, so
        // cleanup only needs to undo what assemble() just wrote.
        if ($user->isClient()) {
            $quotaBytes = $this->storageUsage->quotaBytes($user);

            if ($quotaBytes > 0 && $this->storageUsage->usedBytes($user) + $assembled['size'] > $quotaBytes) {
                Storage::disk($assembled['disk'])->delete($assembled['path']);
                $session->delete();

                throw ValidationException::withMessages([
                    'size' => __('This upload would exceed your storage quota of :quota MB.', [
                        'quota' => (string) $this->storageUsage->quotaMb($user),
                    ]),
                ]);
            }
        }

        // Never trust the client-supplied `type` (store()'s $session->mime_type) for
        // what gets served back — FileThumbnailController::preview() serves files
        // inline using this value, so a spoofed "text/html" would execute script in
        // the previewer's browser. Detect the real mime type from the assembled bytes.
        $mimeType = Storage::disk($assembled['disk'])->mimeType($assembled['path']) ?: 'application/octet-stream';

        // Re-resolved rather than taken from the session. A chunked
        // upload is two requests, and store()'s rule only ever sees the
        // first: delete the folder while the bytes are in flight and the
        // recorded id names a folder whose own deletion already removed
        // every file in it. Filing into it would recreate exactly the
        // state Rules::folderId() exists to prevent.
        //
        // The root, rather than a refusal, because the two moments cost
        // different things. At store() nothing has been sent, so refusing
        // is free and honest. Here the bytes are already uploaded, and
        // throwing away somebody's finished transfer over a folder that
        // vanished underneath them is the harsher of the two surprises —
        // the file lands somewhere they can see it and move it.
        $folderId = $session->folder_id !== null && Folder::query()->whereKey($session->folder_id)->exists()
            ? $session->folder_id
            : null;

        $file = $this->storeFile->create(
            uploader: $user,
            originalName: $session->original_name,
            path: $assembled['path'],
            mimeType: $mimeType,
            size: $assembled['size'],
            checksum: $assembled['checksum'],
            description: $session->description,
            folderId: $folderId,
            disk: $assembled['disk'],
        );

        $previousFileId = $session->previous_file_id;

        $session->delete();

        // Linked after the File row exists, through the same seam the file
        // editor and the API use — so the ownership rule that stops a
        // client inheriting a stranger's recipients is enforced here too,
        // not just in the picker.
        //
        // A failure here does not fail the upload: the bytes are stored and
        // the row is real, so losing the file over a lost link would be the
        // wrong way round. The response says the link did not happen.
        //
        // Its own method so the declared ?string return type reaches the
        // response array — inline, the generator narrows the property to the
        // one literal string the failure branch happens to produce, and the
        // published spec then promises consumers a constant instead of a
        // nullable message.
        $versionError = $this->linkUploadedVersion($file, $user, $previousFileId);

        // The staff Dashboard has no equivalent of these — a staff
        // member uploading their own file isn't "someone to notify
        // about", only a client uploading is.
        if ($user->isClient()) {
            if ($this->settings->get(Setting::EmailNotificationsEnabled) === true) {
                $adminEmails = $this->settings->get(Setting::AdminNotificationEmails);
                foreach (is_array($adminEmails) ? $adminEmails : [] as $email) {
                    Notification::route('mail', $email)->notify(new AdminClientUploadedNotification($user->name, $file->name, $file->id));
                }
            }

            $this->notifier->send('client_uploaded', $this->staffWhoCanSeeUploads(), subject: $file, data: [
                'clientName' => $user->name,
                'itemName' => $file->name,
                'fileId' => $file->id,
            ]);
        }

        return response()->json([
            'location' => route('files.download', $file, false),
            'file_id' => $file->id,
            'version_error' => $versionError,
        ]);
    }

    /**
     * The file this user may legitimately name as an original, or null.
     *
     * Goes through FileVersions::candidates() rather than a plain lookup so
     * the same rule applies here as in the picker — and in particular so a
     * client gets their own uploads only. A client can see plenty of files
     * they do not own, and since a revision inherits the original's
     * recipients, resolving one of those would hand this upload a recipient
     * list belonging to someone else.
     */
    /**
     * Mark the freshly uploaded file as a revision, if one was declared.
     *
     * @return string|null why it did not happen, or null when it did (or
     *                     when nothing was declared)
     */
    private function linkUploadedVersion(File $file, User $user, ?int $previousFileId): ?string
    {
        if ($previousFileId === null) {
            return null;
        }

        $previous = $this->resolvableOriginal($user, $previousFileId);

        try {
            abort_if($previous === null, 404);
            $this->versions->link($file, $previous, $user);
        } catch (ValidationException $exception) {
            return $exception->getMessage();
        } catch (AuthorizationException|HttpException) {
            return __('That file can\'t be used as the previous version.');
        }

        return null;
    }

    private function resolvableOriginal(User $user, int $previousFileId): ?File
    {
        return $this->versions->resolveCandidate(null, $user, $previousFileId);
    }

    /**
     * Staff who could already open this file via the activity log's own
     * link-resolution rule (upload, edit_files, or edit_others_files) —
     * the same permission set, just evaluated per-candidate instead of
     * per-viewer.
     *
     * @return list<User>
     */
    private function staffWhoCanSeeUploads(): array
    {
        return array_values(User::query()->where('type', UserType::Staff)->get()
            ->filter(fn (User $staff): bool => $this->permissions->allows($staff, Permission::Upload)
                || $this->permissions->allows($staff, Permission::EditFiles)
                || $this->permissions->allows($staff, Permission::EditOthersFiles))
            ->all());
    }

    public function destroy(Request $request, UploadSession $session): RedirectResponse|Response
    {
        $this->authorizeSession($request, $session);

        $originalName = $session->original_name;

        $this->parts->abort($session);
        $session->delete();

        $this->activity->log(Action::UploadAborted, context: ['name' => $originalName]);

        return response()->noContent();
    }

    private function authorizeSession(Request $request, UploadSession $session): void
    {
        $user = $request->user();

        abort_unless($user !== null && $session->isOwnedBy($user), 404);
    }
}
