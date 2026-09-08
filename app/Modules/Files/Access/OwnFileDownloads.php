<?php

declare(strict_types=1);

namespace App\Modules\Files\Access;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How often a client's own file has been taken, and when it last was.
 *
 * The question somebody asks about a file they sent: did it arrive? On a
 * hosted free account the link is the whole of the sharing, so "3
 * downloads, last one on Tuesday" is the only evidence there is that it
 * worked.
 *
 * **Own files only, and that is a privacy rule rather than a scoping
 * convenience.** A download entry says somebody fetched the file, and on
 * a file shared with several clients, telling one of them the count tells
 * them about the others' activity. Nobody is entitled to that except the
 * person who put the file there. So a file shared *with* this client
 * carries no numbers at all — not zero, which would be a claim, but
 * nothing.
 *
 * Counts come from the activity log through File::downloads(), the same
 * source every other download count in the interface uses. There is
 * deliberately no counter column — see DownloadAllowance for the whole
 * argument, which applies unchanged here.
 *
 * One query for a page, whatever it holds.
 */
class OwnFileDownloads
{
    /**
     * @param  Collection<int, File>  $files
     * @return array<int, array{count: int, last_at: string|null}> keyed by
     *                                                            file id, only for files this client uploaded
     */
    public function forMany(Collection $files, User $client): array
    {
        $own = $files
            ->filter(fn (File $file): bool => $file->uploaded_by === $client->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($own === []) {
            return [];
        }

        // Every own file gets an entry, including the ones with nothing to
        // report: the difference between "nobody has downloaded this" and
        // "this is not yours to know" is exactly what the caller renders,
        // and a missing key would collapse the two.
        $stats = [];

        foreach ($own as $id) {
            $stats[$id] = ['count' => 0, 'last_at' => null];
        }

        $rows = ActivityLog::query()
            ->selectRaw('subject_id, count(*) as downloads, max(created_at) as last_at')
            ->where('subject_type', (new File)->getMorphClass())
            ->whereIn('subject_id', $own)
            ->whereIn('action', [
                Action::FileDownloaded->value,
                Action::ShareLinkDownloaded->value,
                Action::PublicFileDownloaded->value,
            ])
            ->groupBy('subject_id')
            ->get();

        foreach ($rows as $row) {
            $id = (int) $row->getAttribute('subject_id');
            $lastAt = $row->getAttribute('last_at');

            $stats[$id] = [
                'count' => (int) $row->getAttribute('downloads'),
                'last_at' => $lastAt === null ? null : Carbon::parse((string) $lastAt)->toIso8601String(),
            ];
        }

        return $stats;
    }
}
