<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Concerns\HasUniqueSlug;

/**
 * The slug a deleted row holds, so the name it used to have goes back into
 * circulation.
 *
 * Lives here rather than on HasUniqueSlug because the migration that vacates
 * slugs deleted before the behaviour existed needs the identical format, and
 * a trait constant cannot be reached through the trait's own name. Two copies
 * of this format that drift apart would leave rows the application no longer
 * recognises as vacated, so there is one.
 *
 * @see HasUniqueSlug
 */
final class VacatedSlug
{
    /**
     * Underscores are the whole trick: Str::slug() turns them into hyphens and
     * Rules::slug() refuses them outright, so no derived slug and no
     * hand-typed one can ever collide with a vacated one.
     */
    public const MARKER = '__deleted-';

    /**
     * Column width. Slugs are ASCII by construction, but the truncation is
     * done in characters so a hand-written row cannot cut a byte in half.
     */
    private const MAX_LENGTH = 255;

    /**
     * @param  int|string  $key  the row's own id, so two deleted rows that
     *                           shared a name do not collide with each other
     */
    public static function for(string $slug, int|string $key): string
    {
        if (self::isVacated($slug)) {
            return $slug;
        }

        $suffix = self::MARKER.$key;

        return mb_substr($slug, 0, self::MAX_LENGTH - mb_strlen($suffix)).$suffix;
    }

    public static function isVacated(string $slug): bool
    {
        return str_contains($slug, self::MARKER);
    }
}
