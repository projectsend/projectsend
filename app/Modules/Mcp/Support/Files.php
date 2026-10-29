<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which files an account can see, and what it may do to one — the
 * app's own rules, asked the way the web asks them.
 *
 * For a client the answer is File's `visibleToClient` scope: what
 * was shared with them, what is in a folder they can see, and what they
 * uploaded. It is the same scope the "my files" page runs, so the
 * assistant and the browser never disagree about what exists. For staff
 * it is StaffLibraryScope, as the library page runs it: the whole
 * library, or only their own uploads and their clients' files for a
 * client-scoped staff member.
 *
 * `not_found` never distinguishes "does not exist" from "is not yours":
 * a free account must not be able to enumerate other people's files by
 * id, exactly as the API refuses to.
 */
final class Files
{
    /**
     * @return Builder<File>
     */
    public static function visibleTo(Account $account): Builder
    {
        return $account->isClient()
            ? File::query()->visibleToClient($account->user)
            : app(StaffLibraryScope::class)->files($account->user);
    }

    public static function find(Account $account, int $fileId): ?File
    {
        return self::visibleTo($account)->whereKey($fileId)->first();
    }

    /** What the staff library page asks for before it lists anything. */
    public static function requireLibraryAccess(Account $account): void
    {
        if ($account->isClient()) {
            return;
        }

        if ($account->can('upload') || $account->can('edit_files') || $account->can('edit_others_files')) {
            return;
        }

        throw new ToolFailure(ToolError::permissionMissing('upload', Web::files($account)));
    }

    public static function isOwnedBy(File $file, Account $account): bool
    {
        return (int) $file->getAttribute('uploaded_by') === $account->id();
    }

    /**
     * FilePolicy's `update`, in its own terms: a client edits
     * only what they uploaded; staff edit their own with `edit_files`
     * and everybody else's with `edit_others_files`.
     */
    public static function requireUpdate(File $file, Account $account): void
    {
        $own = self::isOwnedBy($file, $account);

        if ($account->isClient()) {
            if ($own && $account->can('edit_files')) {
                return;
            }

            throw new ToolFailure(ToolError::notFound('file'));
        }

        $permission = $own ? 'edit_files' : 'edit_others_files';

        if (! $account->can($permission)) {
            throw new ToolFailure(ToolError::permissionMissing($permission, Web::file($account, (int) $file->getAttribute('id'))));
        }
    }

    /** FilePolicy's `delete`, same shape. */
    public static function requireDelete(File $file, Account $account): void
    {
        $own = self::isOwnedBy($file, $account);

        if ($account->isClient()) {
            if ($own && $account->can('delete_files')) {
                return;
            }

            throw new ToolFailure(ToolError::notFound('file'));
        }

        $permission = $own ? 'delete_files' : 'delete_others_files';

        if (! $account->can($permission)) {
            throw new ToolFailure(ToolError::permissionMissing($permission, Web::file($account, (int) $file->getAttribute('id'))));
        }
    }

    /**
     * A new version of another file is shared through that one. The
     * error names the root so the assistant calls again with it.
     *
     * @param  array<string, mixed>  $sameArguments
     */
    public static function requireSharingRoot(File $file, string $tool, array $sameArguments = []): void
    {
        $rootId = $file->getAttribute('version_root_id');

        if ($rootId === null) {
            return;
        }

        $root = File::query()->whereKey((int) $rootId)->first();
        $rootName = (string) ($root ?? $file)->getAttribute('name');

        throw new ToolFailure(ToolError::fileIsRevision($tool, (int) $rootId, $rootName, $sameArguments));
    }

    /**
     * The file's share links this account may be shown, newest first.
     *
     * @return list<ShareLink>
     */
    public static function links(File $file, Account $account): array
    {
        return array_values(array_filter(
            $file->shareLinks()->orderByDesc('id')->get()->all(),
            fn (ShareLink $link): bool => self::mayHandle($account, $file, $link),
        ));
    }

    /**
     * Whether this account may see, reuse or revoke the link. Staff: any
     * link on a file they can see. A client: only a link they made, on a
     * file they uploaded, the rule ClientShareLinks applies on the web. A
     * staff member's link is that person's decision about who may reach
     * the file, even on the client's own upload (GHSA-g9g5-qxp7-f7v2).
     */
    public static function mayHandle(Account $account, File $file, ShareLink $link): bool
    {
        return ! $account->isClient()
            || ((int) $file->getAttribute('uploaded_by') === $account->id()
                && (int) $link->getAttribute('created_by') === $account->id());
    }

    /**
     * How often the file was downloaded, through File's own relation
     * over the activity log — the only place downloads are counted.
     */
    public static function downloadsCount(File $file): int
    {
        $counted = $file->getAttribute('downloads_count');

        return $counted !== null ? (int) $counted : $file->downloads()->count();
    }
}
