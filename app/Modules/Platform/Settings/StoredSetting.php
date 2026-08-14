<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

use Illuminate\Database\Eloquent\Model;

class StoredSetting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
