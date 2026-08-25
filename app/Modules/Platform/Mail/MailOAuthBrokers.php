<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail;

use App\Modules\Platform\Settings\MailProvider;
use InvalidArgumentException;

/**
 * Resolves the broker for an OAuth mail provider.
 *
 * A closed map rather than an open registry, for the same reason
 * SocialProvider is a closed enum: each broker encodes decisions about a
 * vendor's token semantics (rotation, what kills a grant) that somebody
 * has reasoned about.
 */
class MailOAuthBrokers
{
    public function for(MailProvider $provider): MailOAuthBroker
    {
        return match ($provider) {
            MailProvider::Microsoft365 => app(MicrosoftMailBroker::class),
            MailProvider::Gmail => app(GoogleMailBroker::class),
            default => throw new InvalidArgumentException("{$provider->value} is not an OAuth mail provider."),
        };
    }
}
