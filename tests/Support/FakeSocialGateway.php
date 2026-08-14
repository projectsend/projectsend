<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Identity\Social\SocialGateway;
use App\Modules\Identity\Social\SocialIdentity;
use App\Modules\Identity\Social\SocialSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * An identity provider that lives in a variable.
 *
 * Swapped in with `$this->swap(SocialGateway::class, ...)`, this
 * exercises everything above the wire — which addresses may reach which
 * accounts, provisioning, approval, the two-factor hand-off — with no
 * OAuth server anywhere. It also counts calls, so a test can assert the
 * provider was *not* consulted, which is how "a disabled provider is
 * never contacted" is proven rather than assumed.
 *
 * The same shape as FakeLdapDirectory, deliberately.
 */
class FakeSocialGateway implements SocialGateway
{
    public int $redirects = 0;

    public int $identityCalls = 0;

    public function __construct(private ?SocialIdentity $identity = null) {}

    public function willReturn(?SocialIdentity $identity): self
    {
        $this->identity = $identity;

        return $this;
    }

    public function redirect(SocialSettings $settings): RedirectResponse
    {
        $this->redirects++;

        return new RedirectResponse('https://provider.test/authorize');
    }

    public function identity(SocialSettings $settings): ?SocialIdentity
    {
        $this->identityCalls++;

        return $this->identity;
    }
}
