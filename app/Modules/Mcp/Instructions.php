<?php

declare(strict_types=1);

namespace App\Modules\Mcp;

use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\ConnectorPlan;
use App\Modules\Mcp\Support\Web;

/**
 * What the server tells an assistant at `initialize` (spec §1): one
 * text for everybody, and one line that says which account this is.
 *
 * The strings are contract — the site's docs/mcp-harness-spec.md holds
 * them and a test forbids the words the product never uses.
 */
final class Instructions
{
    public const BASE = <<<'TEXT'
ProjectSend is client file sharing. Files live in this account's ProjectSend site and are shared in one of two ways: by a private link, which anyone holding the URL can use to download, or with a client account, where the client signs in to the site to see what was shared with them. This connector operates the account of the person who authorised it. Everything you do is written to their activity log as "AI assistant" and is visible on the web immediately, with the same names.

Choosing how to get a file in:
- Something you wrote in this conversation, up to 1 MB: create_file_from_content. One call returns the saved file and its link.
- Bytes you can read yourself (a file on disk, in a sandbox, produced by a script): create_upload, then PUT each part, then complete_upload. Never paste more than 1 MB of content into a call.
- A file on the person's computer that you cannot read: get_web_upload_link. Give them the URL, and when they say it is uploaded, find it with list_files using the `since` value you were given.

Reading responses: every success includes web_url (where to see it on the web) and next_steps. Every error includes code and action; when it includes next_call, that call is the fix, with its arguments already filled in.

Prefer `since` and cursors over polling. revoke_link and delete_file are destructive: the client asks the person to confirm before they run, so never call them twice for one request. Do not invent URLs; the URLs to share are the ones in responses.
TEXT;

    /**
     * Said only where there is a plan to upgrade to: everywhere else no
     * error carries feature_requires_plan, and the sentence would invite
     * the assistant to look for a paid plan that does not exist.
     */
    public const PLAN_NOTE = 'When code is feature_requires_plan, do what alternative_tool offers first. Mention the paid plan once, plainly, with the upgrade_url you were given, and only if it solves what the person actually asked for.';

    public static function text(?Account $account): string
    {
        $base = ConnectorPlan::current()->upgradePlan() === null
            ? self::BASE
            : self::withPlanNote();

        return $account === null ? $base : $base."\n\n".self::accountLine($account);
    }

    /** The base text, with the plan sentence closing the paragraph on reading responses. */
    private static function withPlanNote(): string
    {
        $anchor = 'with its arguments already filled in.';

        return str_replace($anchor, $anchor.' '.self::PLAN_NOTE, self::BASE);
    }

    /**
     * "This account: …", one of two shapes: the free customer's, with the
     * space and what a portal of their own adds; or everybody else's,
     * with the plan when there is one and the client seats.
     */
    public static function accountLine(Account $account): string
    {
        $hostname = Web::hostname();

        if ($account->isFree()) {
            $storage = $account->storage();
            $space = $storage === null ? '' : sprintf(' — %s of %s used', $storage['used'], $storage['quota']);

            return sprintf(
                'This account: %s on %s, free plan%s. A free account shares files by private link; client accounts, folders, team seats and a site at their own address come with a portal of their own.',
                $account->name(),
                $hostname,
                $space,
            );
        }

        $plan = $account->plan();
        $seats = $account->seats();
        $seatsText = $seats === null
            ? ''
            : sprintf(' — %d of %s client accounts', $seats['clients_used'], $seats['clients_limit'] === null ? 'unlimited' : (string) $seats['clients_limit']);

        return sprintf(
            'This account: %s on %s%s%s.',
            $account->name(),
            $hostname,
            $plan === null ? '' : ', '.$plan['name'],
            $seatsText,
        );
    }
}
