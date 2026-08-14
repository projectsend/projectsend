<?php

declare(strict_types=1);

namespace App\Modules\Identity;

/**
 * The fundamental ProjectSend distinction, inherited from v1: staff
 * ("system users" — v1 levels 9/8/7) administer and upload; clients
 * (v1 level 0) are the recipients files are shared with. Both live in
 * the users table behind a single auth surface, but nearly every
 * permission and workflow depends on which one an account is.
 */
enum UserType: string
{
    case Staff = 'staff';
    case Client = 'client';
}
