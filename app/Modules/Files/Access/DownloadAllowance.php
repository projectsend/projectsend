<?php

declare(strict_types=1);

namespace App\Modules\Files\Access;

use App\Models\User;
use App\Modules\Files\DownloadLimitScope;
use App\Modules\Files\Models\File;
use Illuminate\Database\Eloquent\Builder;

/**
 * Whether this person may still take a copy of this file.
 *
 * A download limit is not a property of the file the way expiry is — it
 * depends on who is asking. `PerUser` gives each person their own
 * allowance, and the uploader is exempt from their own limit entirely
 * (ProjectSend v1's rule, and the reason its editor said "Your own
 * downloads do not count toward the limit": an administrator checking
 * their own upload should not spend a client's allowance).
 *
 * That is why this is a service and not a scope. Expiry is welded onto
 * the end of File::scopeVisibleToClient() and friends, so an expired
 * file *disappears*. A spent limit must not: the file stays listed and
 * stops being downloadable, so the recipient can see it existed and ask
 * for more rather than watch it vanish. Adding this rule to a visibility
 * scope would also take the file out of the staff library, which is not
 * what a cap on downloads means.
 *
 * ### Called from every path, because there is no choke point
 *
 * Six routes put a file's bytes on the wire and they authorize four
 * different ways — FileDownloadController and FileThumbnailController
 * through the Gate, the zip pair through ViewableFileScope, and the
 * share link and public listing through neither, since both serve
 * visitors with no User at all. A rule written into FilePolicy::view()
 * would silently not apply to the last two. So it lives here and every
 * one of them asks.
 *
 * ### Counting
 *
 * Counts come from the activity log, via File::downloads() — the same
 * source every download count in the interface already uses. There is
 * deliberately no counter column on `files`: `PerUser` cannot be a
 * single number, so a column would serve only `Total` and would drift
 * from the log beside it.
 *
 * The cost of that is a race — two simultaneous downloads can both pass
 * before either is logged, overshooting a limit by roughly the
 * concurrency. It is bounded and small, v1 had the same one, and closing
 * it needs exactly the counter column above. ShareLink's own
 * `max_downloads` keeps its atomic conditional increment; that one is a
 * single number and can afford to be exact.
 */
class DownloadAllowance
{
    /**
     * Memoised for the life of the request: several listings ask, and
     * the answer cannot change while one page is being built.
     */
    private ?bool $usedAnywhere = null;

    /**
     * How many downloads this person has left, or null when the file is
     * uncapped for them.
     */
    public function remaining(File $file, ?User $viewer): ?int
    {
        if (! $this->applies($file, $viewer)) {
            return null;
        }

        return max(0, (int) $file->download_limit - $this->used($file, $viewer));
    }

    public function allows(File $file, ?User $viewer): bool
    {
        $remaining = $this->remaining($file, $viewer);

        return $remaining === null || $remaining > 0;
    }

    /**
     * How much of the allowance has been spent — the whole file's
     * downloads under Total, this person's own under PerUser.
     */
    public function used(File $file, ?User $viewer): int
    {
        if ($this->scopeFor($file, $viewer) === DownloadLimitScope::PerUser && $viewer !== null) {
            return $file->downloads()->where('actor_id', $viewer->id)->count();
        }

        return $file->downloads()->count();
    }

    /**
     * What a listing needs to show, decided here rather than in the
     * listing.
     *
     * Themes render this; they never work it out. The props contract in
     * docs/theming-files-checklist.md is explicit that a theme must not
     * re-derive a rule, and there are eight of them — the per-user
     * scope and the uploader exemption would be wrong in at least one
     * within a release.
     *
     * Reads the counts withCounts() attached, so a page of rows costs
     * the two subqueries that query already added and nothing further.
     * Enforcement never comes through here: `own_downloads_count` is
     * only true for the viewer it was loaded for, and a guard that read
     * a stale one would be a guard that let the wrong person through.
     *
     * @return array{limit: int|null, left: int|null, blocked: bool}
     */
    public function summaryFor(File $file, ?User $viewer): array
    {
        if (! $this->applies($file, $viewer)) {
            return ['limit' => null, 'left' => null, 'blocked' => false];
        }

        $perUser = $this->scopeFor($file, $viewer) === DownloadLimitScope::PerUser;
        $used = (int) ($perUser
            ? ($file->own_downloads_count ?? $file->downloads()->where('actor_id', $viewer?->id)->count())
            : ($file->downloads_count ?? $file->downloads()->count()));

        $left = max(0, (int) $file->download_limit - $used);

        return ['limit' => (int) $file->download_limit, 'left' => $left, 'blocked' => $left === 0];
    }

    /**
     * Whether any file in this installation has a limit at all.
     *
     * One indexed query, so a listing can decide whether to count
     * downloads per row. The overwhelming majority of installs never set
     * a limit on anything and should not pay two correlated subqueries
     * per file for a feature they do not use.
     */
    public function isUsedAnywhere(): bool
    {
        return $this->usedAnywhere ??= File::query()->whereNotNull('download_limit')->exists();
    }

    /**
     * Attach the counts a listing needs to show a spent limit.
     *
     * Both are added because the scope varies per row: a page can hold a
     * Total-limited file next to a PerUser-limited one, and picking the
     * right number in SQL would mean a correlated subquery that branches
     * on a column. Cheaper to select both and let the presenter choose —
     * and only when the install uses limits at all.
     *
     * @param  Builder<File>  $query
     * @return Builder<File>
     */
    public function withCounts(Builder $query, ?User $viewer): Builder
    {
        if (! $this->isUsedAnywhere()) {
            return $query;
        }

        return $this->withOwnCount($query->withCount('downloads'), $viewer);
    }

    /**
     * Just the viewer's own count, for a listing that already selects the
     * shared one for its own reasons — the staff library shows a
     * downloads column whether or not anything is limited, so it must
     * keep asking for that count unconditionally.
     *
     * @param  Builder<File>  $query
     * @return Builder<File>
     */
    public function withOwnCount(Builder $query, ?User $viewer): Builder
    {
        if ($viewer === null || ! $this->isUsedAnywhere()) {
            return $query;
        }

        return $query->withCount(['downloads as own_downloads_count' => fn (Builder $downloads) => $downloads
            ->where('actor_id', $viewer->id)]);
    }

    /**
     * The scope actually in force for this viewer.
     *
     * PerUser has no meaning for a visitor who is not signed in — there
     * is no "user" to count against. Rather than inventing one from an IP
     * address (v1 counted anonymous downloads per IP over 24 hours, which
     * gives a whole office behind one NAT a single shared allowance, and
     * stops working entirely when Setting::DownloadIpLogging is `none`),
     * an anonymous visitor falls back to the file's total.
     */
    private function scopeFor(File $file, ?User $viewer): DownloadLimitScope
    {
        $scope = $file->download_limit_scope ?? DownloadLimitScope::Total;

        return $viewer === null ? DownloadLimitScope::Total : $scope;
    }

    private function applies(File $file, ?User $viewer): bool
    {
        if (! $file->hasDownloadLimit()) {
            return false;
        }

        // The uploader's own downloads never count against their own
        // limit, and are never refused by it.
        return ! ($viewer !== null && $file->isOwnedBy($viewer));
    }
}
