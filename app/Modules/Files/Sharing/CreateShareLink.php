<?php

declare(strict_types=1);

namespace App\Modules\Files\Sharing;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Minting a public link for a file, in one place.
 *
 * Extracted from ShareLinksController rather than invented: the
 * controller is an HTTP handler behind `staff` middleware, and a link now
 * needs creating from outside a request as well. Two copies of "make a
 * token, write the row, log it" would drift, and the half most likely to
 * drift is the token.
 *
 * **The token is the whole authorization.** There is nothing behind
 * /s/{token} — no session, no second factor — so its only defence is
 * being unguessable. Str::random(32) is about 190 bits, which is more
 * than a UUID's 122; anything minted here gets that and never a chosen
 * value. A caller that wants a chosen token is a person typing one into a
 * form, and that path stays in the controller where its minimum length
 * can be argued about in a validation rule.
 *
 * Expiry and download caps are the caller's to decide and are passed in
 * already resolved, because "the end of the 12th" depends on whose zone
 * you are in and this class has no viewer.
 */
class CreateShareLink
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function for(
        File $file,
        User $creator,
        ?CarbonInterface $expiresAt = null,
        ?int $maxDownloads = null,
        ?string $token = null,
    ): ShareLink {
        $link = ShareLink::query()->create([
            'shareable_type' => $file->getMorphClass(),
            'shareable_id' => $file->id,
            'token' => $token ?? Str::random(32),
            'created_by' => $creator->id,
            'expires_at' => $expiresAt,
            'max_downloads' => $maxDownloads,
        ]);

        $this->activity->log(Action::ShareLinkCreated, subject: $file);

        return $link;
    }
}
