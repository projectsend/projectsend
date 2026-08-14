<?php

declare(strict_types=1);

namespace App\Modules\Files;

/**
 * A fixed palette for category badges — closed set so contrast and
 * dark-mode legibility stay guaranteed (each color gets a hand-picked
 * light/dark Tailwind pairing on the frontend), rather than a free hex
 * value needing per-color contrast validation.
 */
enum CategoryColor: string
{
    case Gray = 'gray';
    case Red = 'red';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Green = 'green';
    case Blue = 'blue';
    case Purple = 'purple';
    case Pink = 'pink';
}
