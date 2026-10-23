<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use RuntimeException;

/** A repeat that cannot be answered with the first call's result. */
final class IdempotencyConflict extends RuntimeException {}
