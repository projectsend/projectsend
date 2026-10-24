<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use RuntimeException;

/**
 * A tool error thrown from deep inside a helper, so the tool's `handle`
 * can turn it into the response it is. Never escapes the tool.
 */
final class ToolFailure extends RuntimeException
{
    public function __construct(public readonly ToolError $error)
    {
        parent::__construct($error->code);
    }
}
