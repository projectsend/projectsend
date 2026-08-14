<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

enum SettingType
{
    case String;
    case Boolean;
    case Integer;
    case Json;
}
