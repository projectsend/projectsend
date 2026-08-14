<?php

declare(strict_types=1);

namespace App\Modules\Identity\TwoFactor;

use App\Modules\Identity\UserType;

enum TwoFactorEnforcement: string
{
    case None = 'none';
    case Staff = 'staff';
    case Clients = 'clients';
    case All = 'all';

    public function appliesTo(UserType $type): bool
    {
        return match ($this) {
            self::None => false,
            self::Staff => $type === UserType::Staff,
            self::Clients => $type === UserType::Client,
            self::All => true,
        };
    }
}
