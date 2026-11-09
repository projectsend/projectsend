<?php

declare(strict_types=1);

namespace App\Modules\Groups\PublicListing;

use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ZipDownload;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Database\Eloquent\Builder;

/**
 * The files a visitor can take from one public page, as of now.
 *
 * A visitor's zip has no account to check access against, so it is
 * authorised by the page it came from instead: the public index, a public
 * group, or a public folder. The same three queries the pages list
 * (standalonePublic, publiclyVisibleForGroup, publiclyVisibleForFolder),
 * so a zip can never hold a file its page would not have shown. Each
 * already leaves out what is expired, withdrawn or not yet scanned.
 *
 * Asked three times for one zip: when it is requested, when it is built,
 * and every time it is downloaded. A folder made private in between, or a
 * file taken out of it, stops the zip at whichever step comes next.
 */
final class PublicFileScope
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * The page a request names, by its slug, or null when there is no such
     * public page. The index needs the public listing switched on, as its
     * page does; a group or folder page works without it.
     *
     * @return array{type: string, id: int|null}|null
     */
    public function fromPage(string $type, ?string $slug): ?array
    {
        return match ($type) {
            ZipDownload::SCOPE_INDEX => $this->listingEnabled() ? ['type' => $type, 'id' => null] : null,
            ZipDownload::SCOPE_GROUP => ($group = Group::query()->where('slug', (string) $slug)->where('public', true)->first()) !== null
                ? ['type' => $type, 'id' => $group->id] : null,
            ZipDownload::SCOPE_FOLDER => ($folder = Folder::query()->where('slug', (string) $slug)->where('public', true)->first()) !== null
                ? ['type' => $type, 'id' => $folder->id] : null,
            default => null,
        };
    }

    /**
     * The files that page offers now, or null when the page itself is gone:
     * the group or folder made private or deleted, or the listing switched
     * off.
     *
     * @return Builder<File>|null
     */
    public function files(string $type, ?int $id): ?Builder
    {
        switch ($type) {
            case ZipDownload::SCOPE_INDEX:
                return $this->listingEnabled() ? File::query()->standalonePublic() : null;

            case ZipDownload::SCOPE_GROUP:
                $group = Group::query()->whereKey($id)->where('public', true)->first();

                return $group === null ? null : File::query()->publiclyVisibleForGroup($group);

            case ZipDownload::SCOPE_FOLDER:
                $folder = Folder::query()->whereKey($id)->where('public', true)->first();

                return $folder === null ? null : File::query()->publiclyVisibleForFolder($folder);
        }

        return null;
    }

    private function listingEnabled(): bool
    {
        return $this->settings->get(Setting::PublicListingEnabled) === true;
    }
}
