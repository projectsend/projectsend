<?php

declare(strict_types=1);

namespace App\Modules\Clients;

/**
 * Whether a custom field is exposed to the client themself, and if so,
 * whether they can keep changing it or only set it once. "EditableOnce"
 * locks the field read-only as soon as the client has a stored value —
 * useful for things like a terms-acceptance checkbox.
 */
enum ClientFieldEditability: string
{
    case Hidden = 'hidden';
    case Editable = 'editable';
    case EditableOnce = 'editable_once';
}
