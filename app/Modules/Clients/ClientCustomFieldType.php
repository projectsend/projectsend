<?php

declare(strict_types=1);

namespace App\Modules\Clients;

/**
 * The input types a custom field definition can render as. "Select"
 * fields carry their choices in the field's `options` column; the rest
 * are self-describing.
 */
enum ClientCustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Select = 'select';
    case Checkbox = 'checkbox';
}
