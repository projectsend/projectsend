<?php

declare(strict_types=1);

namespace App\Modules\Files\Sharing;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use Illuminate\Support\Collection;

/**
 * The public URLs a client may be shown for their own files.
 *
 * A client's portal lists two kinds of file side by side: what they
 * uploaded, and what somebody shared with them. Both can carry share
 * links, and only one kind of link is theirs to see — a link a staff
 * member minted for a file they were given is that staff member's
 * decision about who may reach it, and handing the recipient the URL
 * would turn "you may download this" into "you may pass this on to
 * anyone".
 *
 * So the rule is narrow and stated once: **a link this client created, on
 * a file this client uploaded.** Both halves, not either. Neither is
 * redundant — a client-created link on a file they no longer own would
 * outlive a reassignment, and a staff link on their own upload is still
 * not theirs to hand out.
 *
 * Inactive links are left out rather than shown greyed: the only thing a
 * client can do with this is copy it, and a URL that answers "this link
 * has expired" is worse than no URL at all.
 *
 * Resolved for a whole page at a time. One query for the listing, not one
 * per row.
 */
class ClientShareLinks
{
    /**
     * @param  Collection<int, File>  $files
     * @return array<int, string> file id => URL, for the files that have one
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

        $links = ShareLink::query()
            ->where('shareable_type', (new File)->getMorphClass())
            ->whereIn('shareable_id', $own)
            ->where('created_by', $client->id)
            // Oldest first, so a file that somehow carries two is
            // described by the one the client has already been given
            // rather than by whichever the database returned today.
            ->orderBy('id')
            ->get();

        $urls = [];

        foreach ($links as $link) {
            $fileId = (int) $link->shareable_id;

            if (isset($urls[$fileId]) || ! $link->isActive()) {
                continue;
            }

            $urls[$fileId] = route('share.show', $link->token);
        }

        return $urls;
    }
}
