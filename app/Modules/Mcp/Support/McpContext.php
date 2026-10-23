<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

/**
 * The account of the request being served, for the one app hook that
 * needs it: the activity log asking who is acting.
 *
 * Set by the server when it boots for a request and read by the
 * listener on `ResolvingActivityOrigin`. A plain static because the MCP
 * transport handles one request per process invocation and the server
 * is instantiated per request; nothing here outlives the call.
 */
final class McpContext
{
    private static ?Account $account = null;

    public static function set(?Account $account): void
    {
        self::$account = $account;
    }

    public static function account(): ?Account
    {
        return self::$account;
    }

    public static function active(): bool
    {
        return self::$account !== null;
    }
}
