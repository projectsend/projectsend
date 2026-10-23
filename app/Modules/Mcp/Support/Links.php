<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Files\Sharing\CreateShareLink;
use App\Modules\Platform\Localization\LocalDay;
use App\Modules\Platform\Localization\TimezoneRegistry;
use Carbon\CarbonInterface;

/**
 * Minting a private link the way the web does — with the two options
 * gated by the person's permissions rather than refused.
 *
 * `ShareLinksController::store` passes an expiry only when the person
 * may set one and a cap only when they may limit downloads; an option
 * they cannot use is dropped, not an error. Here the drop is said out
 * loud as a warning, because an assistant that asked for an expiry and
 * silently got none would tell the person the link expires.
 */
final class Links
{
    /**
     * The two options as this account may use them: what it may not set
     * is dropped and said, as the web drops it.
     *
     * On a free account with a plan to move to, the reason is the plan,
     * not a role, and what the free plan lacks is offered (decision 17): the warning names what a
     * portal of their own adds, and the third value tells the tool to put
     * the sign-up link in the answer. On staff it is the role, and the
     * warning says only that.
     *
     * @param  list<string>  $warnings  appended to, never replaced
     * @return array{0: string|null, 1: int|null, 2: bool} expiry, cap, whether the plan dropped one
     */
    public static function allowedOptions(Account $account, ?string $expiresAtDate, ?int $maxDownloads, array &$warnings): array
    {
        $dropped = [];

        if ($expiresAtDate !== null && ! $account->can('set_file_expiration_date')) {
            $dropped[] = 'expiry';
            $expiresAtDate = null;
        }

        if ($maxDownloads !== null && ! $account->can('limit_downloads')) {
            $dropped[] = 'cap';
            $maxDownloads = null;
        }

        if ($dropped === []) {
            return [$expiresAtDate, $maxDownloads, false];
        }

        $plan = $account->upgradePlan();

        if ($plan !== null) {
            $what = count($dropped) === 2 ? 'The expiry and the download cap were' : ($dropped[0] === 'expiry' ? 'The expiry was' : 'The download cap was');

            $warnings[] = "{$what} ignored: links on the free plan stay until they are stopped. Expiry dates and download caps come with a portal of their own ({$plan['name']}, {$plan['price']}); mention it once, with upgrade_url, only if the person needs a link that stops by itself. revoke_link stops this one whenever they want.";

            return [$expiresAtDate, $maxDownloads, true];
        }

        foreach ($dropped as $option) {
            $warnings[] = $option === 'expiry'
                ? 'The expiry was ignored: this account cannot set expiry dates on links.'
                : 'The download cap was ignored: this account cannot cap downloads on links.';
        }

        return [$expiresAtDate, $maxDownloads, false];
    }

    /**
     * @param  list<string>  $warnings  appended to, never replaced
     */
    public static function mint(File $file, Account $account, ?string $expiresAtDate, ?int $maxDownloads, array &$warnings): ShareLink
    {
        [$expiresAtDate, $maxDownloads] = self::allowedOptions($account, $expiresAtDate, $maxDownloads, $warnings);

        $expiresAt = null;

        if ($expiresAtDate !== null) {
            $expiresAt = self::endOfDay($expiresAtDate, $account);

            if ($expiresAt === null || $expiresAt->isPast()) {
                throw new ToolFailure(ToolError::validationFailed(['expires_at' => ['The expiry must be a day in the future.']]));
            }
        }

        return app(CreateShareLink::class)->for(
            file: $file,
            creator: $account->user,
            expiresAt: $expiresAt,
            maxDownloads: $maxDownloads,
        );
    }

    /** The newest link that works and carries neither expiry nor cap. */
    public static function plainActive(File $file): ?ShareLink
    {
        foreach (Files::links($file) as $link) {
            if ($link->isActive() && $link->getAttribute('expires_at') === null && $link->getAttribute('max_downloads') === null) {
                return $link;
            }
        }

        return null;
    }

    /**
     * The end of that day where the person is, the way the web resolves
     * a link's expiry.
     */
    public static function endOfDay(string $date, Account $account): ?CarbonInterface
    {
        try {
            return LocalDay::end($date, app(TimezoneRegistry::class)->resolve($account->user));
        } catch (\Throwable) {
            return null;
        }
    }
}
