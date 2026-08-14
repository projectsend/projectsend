<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use App\Modules\Identity\UserType;

/**
 * Who the allowed-extensions list (Setting::AllowedUploadExtensions) is
 * enforced against. v1 parity: "Limit file types uploading to" (no one /
 * everyone / clients).
 */
enum UploadTypeRestriction: string
{
    case None = 'none';
    case Clients = 'clients';
    case All = 'all';

    public function appliesTo(UserType $type): bool
    {
        return match ($this) {
            self::None => false,
            self::Clients => $type === UserType::Client,
            self::All => true,
        };
    }
}
