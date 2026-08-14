<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use App\Modules\Files\Models\File;

/**
 * Which files accept comments at all (Setting::CommentsScope).
 *
 * The `*_files` suffixes exist to keep this vocabulary apart from
 * CommentVisibility's, which also has a "public" and a "with access" idea
 * and means something entirely different — this enum is about *files*,
 * that one is about *comments*.
 *
 * `SelectedFiles` is the only value that consults the per-file
 * `files.commentable` flag; under every other value that column is
 * ignored, which is why the file editor only offers the toggle when this
 * setting is `selected`.
 */
enum CommentScope: string
{
    case None = 'none';
    case AllFiles = 'all';
    case PublicFiles = 'public_files';
    case SharedFiles = 'shared_files';
    case SelectedFiles = 'selected';

    public function allows(File $file): bool
    {
        return match ($this) {
            self::None => false,
            self::AllFiles => true,
            self::PublicFiles => $file->isEffectivelyPublic(),
            self::SharedFiles => ! $file->isEffectivelyPublic(),
            self::SelectedFiles => (bool) $file->commentable,
        };
    }

    /**
     * English label — also the translation key.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'No files — commenting is off',
            self::AllFiles => 'All files',
            self::PublicFiles => 'Public files only',
            self::SharedFiles => 'Shared files only',
            self::SelectedFiles => 'Only files marked as commentable',
        };
    }
}
