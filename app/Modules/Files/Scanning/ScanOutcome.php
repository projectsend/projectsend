<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

enum ScanOutcome
{
    case Clean;
    case Infected;
    case TooLarge;
    case Encrypted;
    case Unavailable;
}
