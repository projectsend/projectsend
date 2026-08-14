<?php

declare(strict_types=1);

namespace App\Modules\Platform\Notifications;

use Illuminate\Database\Eloquent\Model;

/**
 * A customized subject/body for one EmailTemplateSlot. A row's mere
 * existence means the slot is customized — there's no separate
 * "enabled" flag.
 *
 * @property int $id
 * @property string $slot
 * @property string $subject
 * @property string $body
 */
class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $fillable = [
        'slot',
        'subject',
        'body',
    ];
}
