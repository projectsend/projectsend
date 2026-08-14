<?php

declare(strict_types=1);

namespace App\Modules\Clients;

/**
 * The client-facing forms a custom field can be placed on.
 */
enum ClientFieldContext: string
{
    case Registration = 'registration';
    case AccountEdit = 'account_edit';
}
