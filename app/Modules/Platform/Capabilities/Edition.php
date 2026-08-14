<?php

declare(strict_types=1);

namespace App\Modules\Platform\Capabilities;

enum Edition: string
{
    case Community = 'community';
    case Cloud = 'cloud';
}
