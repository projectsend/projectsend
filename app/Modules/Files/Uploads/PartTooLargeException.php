<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use RuntimeException;

/**
 * A single upload part exceeded the allowed part size. Distinct from the
 * generic RuntimeException the part store throws for I/O problems, so the
 * controller can answer 413 rather than turning a client's misbehaviour
 * into a 500.
 */
class PartTooLargeException extends RuntimeException {}
