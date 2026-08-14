<?php

declare(strict_types=1);

namespace App\Support;

use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * Builds the public-site URL of a file, folder or group.
 *
 * Every public route is nested under the installation-wide base segment
 * (Setting::PublicListingSlug), which is configurable — so the lookup, and
 * the choice of route name per subject, live here rather than being spelled
 * out at each call site.
 *
 * This deliberately does *not* decide whether a subject should have a public
 * URL at all. That test differs by subject and each difference is
 * intentional: a folder needs its own `public` flag (an effectively-public
 * one inherited from an ancestor has no page of its own), a file only needs
 * to be effectively public, and in a listing an expired file gets no link
 * because the public route 404s past expiry. Callers keep those checks.
 */
class PublicUrl
{
    public function __construct(private readonly Settings $settings) {}

    public function for(File|Folder|Group $subject): string
    {
        $route = match (true) {
            $subject instanceof File => 'public.file',
            $subject instanceof Folder => 'public.folder',
            $subject instanceof Group => 'public.show',
        };

        // The slug is passed explicitly rather than the model: these routes
        // bind on {folderSlug}/{groupSlug} rather than the model's route key,
        // so handing over the model would put its id in the URL.
        return route($route, [$this->settings->get(Setting::PublicListingSlug), $subject->slug]);
    }
}
