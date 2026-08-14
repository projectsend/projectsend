<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Derives a URL slug from the model's name on create, and keeps it unique.
 *
 * The public URL of a file, folder or group needs a slug, but plenty of call
 * sites (tests, seeders, the upload flow, FolderService, the v1 importer)
 * create one without going through a form that requires it — so fall back to
 * deriving one from the name rather than failing at the database.
 *
 * Soft-deleted rows still count as collisions: their slugs remain reachable
 * until the row is really gone, and reusing one would resurrect the wrong
 * URL.
 *
 * @phpstan-require-extends Model
 */
trait HasUniqueSlug
{
    /**
     * Booted by Eloquent alongside — not instead of — the model's own
     * booted(), which several of these models use for unrelated hooks.
     */
    protected static function bootHasUniqueSlug(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->slug)) {
                $model->slug = static::uniqueSlugFrom($model->name);
            }
        });
    }

    /**
     * @param  int|null  $ignoreId  the row being renamed, which must not
     *                              collide with the slug it already holds
     */
    public static function uniqueSlugFrom(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: static::slugFallback();
        $slug = $base;
        $suffix = 2;

        $collides = fn (string $candidate): bool => static::query()->withTrashed()
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();

        while ($collides($slug)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Stands in when the name slugs to nothing at all — a name of only
     * punctuation or of characters Str::slug() drops entirely.
     */
    abstract protected static function slugFallback(): string;
}
