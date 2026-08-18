<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Support\VacatedSlug;
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
 * Deleting a row hands its slug back. A soft-deleted row is unreachable —
 * every public lookup goes through Eloquent, so the soft-delete scope has
 * already excluded it — and nothing in the application can restore one, so
 * a slug it kept holding would be reserved for a page that can never return.
 * Holding one is what burned the name of any deleted folder for good: the
 * unique index still saw the row, so the name could never be used again and
 * the screen could not say why, because the row it collided with is one the
 * interface will not show.
 *
 * The database is what forces the vacating to be a rewrite rather than a
 * softer lookup. Teaching the collision checks to ignore trashed rows is not
 * enough on its own — two rows would then both hold `report`, and the unique
 * index rejects that however the application feels about it. So the slug
 * moves into a namespace nothing else can occupy: `report__deleted-42`.
 * Underscores are the whole trick. Str::slug() turns them into hyphens and
 * Rules::slug() refuses them outright, so neither a derived slug nor a
 * hand-typed one can ever land on a vacated slug, whatever the row is called.
 *
 * The collision checks still count trashed rows anyway. It costs nothing, and
 * it keeps them honest about what the index will actually accept if a row is
 * ever soft-deleted by something that bypasses model events.
 *
 * Requires SoftDeletes: the collision check reaches for withTrashed(), and
 * vacating is only meaningful for a row that outlives its own delete.
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

        static::deleted(function (self $model): void {
            static::vacateSlug($model);
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
     * Hands a soft-deleted row's slug back to the names still in use.
     *
     * Written through the query builder on purpose: this is bookkeeping, not
     * an edit somebody made, so it must not move updated_at or fire a second
     * round of model events on a row that is already on its way out.
     */
    protected static function vacateSlug(self $model): void
    {
        // A row that is really gone took its slug with it. `deleted` fires
        // for forceDelete() too, and there is nothing left to rewrite.
        if ($model->isForceDeleting()) {
            return;
        }

        if (blank($model->slug)) {
            return;
        }

        $vacated = VacatedSlug::for($model->slug, $model->getKey());

        if ($vacated === $model->slug) {
            return;
        }

        static::query()->withTrashed()->whereKey($model->getKey())->toBase()->update(['slug' => $vacated]);

        // Keep the in-memory row telling the truth for whatever the caller
        // does with it after the delete returns.
        $model->slug = $vacated;
        $model->syncOriginalAttribute('slug');
    }

    /**
     * Stands in when the name slugs to nothing at all — a name of only
     * punctuation or of characters Str::slug() drops entirely.
     */
    abstract protected static function slugFallback(): string;
}
