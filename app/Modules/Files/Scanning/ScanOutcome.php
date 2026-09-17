<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

enum ScanOutcome
{
    case Clean;
    case Infected;
    case TooLarge;
    case Encrypted;
    /** The file's own bytes could not be read. Nothing to do with the scanner. */
    case Unreadable;

    case Unavailable;
}
