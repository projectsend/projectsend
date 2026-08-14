<?php

declare(strict_types=1);

namespace App\Modules\Platform\Scheduling;

enum TaskRunStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
}
