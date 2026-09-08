<?php

declare(strict_types=1);

use App\Modules\Identity\Social\SocialIdentity;
use App\Modules\Identity\Social\SocialProvider;
use App\Modules\Identity\Social\SocialSettings;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * What each provider's word on an address is worth.
 *
 * `emailVerified` is what licenses binding a new identity to an account
 * that already exists, which is where v1 was taken over. Everything
 * upstream of it is a claim somebody else wrote.
 *
 * This exercises fromSocialite() directly, on raw claims. The tests in
 * tests/Feature/Auth/SocialLoginTest.php build a SocialIdentity by hand
 * and so never reach this mapping — which is why the Microsoft branch
 * could trust a tenant match alone with a full suite passing
 * (GHSA-2rfh-v3j2-2jg7).
 */
const TENANT = 'e1f2a3b4-0000-0000-0000-abcdefabcdef';

/** @param array<string, mixed> $claims */
function microsoftIdentity(array $claims, ?string $tenantId = TENANT): ?SocialIdentity
{
    $user = new SocialiteUser;
    $user->map(['id' => 'attacker-subject', 'email' => $claims['email'] ?? null, 'name' => 'Someone']);
    $user->setRaw($claims);

    $settings = new SocialSettings(['provider' => SocialProvider::Microsoft->value, 'tenant_id' => $tenantId]);

    return SocialIdentity::fromSocialite(SocialProvider::Microsoft, $user, $settings);
}

/*
|--------------------------------------------------------------------------
| Entra: a tenant match is not a verified address
|--------------------------------------------------------------------------
|
| Pinning the tenant defeats the classic cross-tenant nOAuth, and that is
| all it defeats. Inside the tenant, `email` is user-mutable and can be
| populated from unverified sources — a B2B guest's otherMails among them
| — so a colleague or an invited guest could present the victim's address.
| Microsoft's own guidance names the control: xms_edov.
*/

test('a tenant match alone is not enough', function () {
    $identity = microsoftIdentity([
        'tid' => TENANT,
        'email' => 'admin@example.test',
    ]);

    expect($identity?->emailVerified)->toBeFalse();
});

test('the domain-owner claim is what makes it verified', function () {
    $identity = microsoftIdentity([
        'tid' => TENANT,
        'email' => 'admin@example.test',
        'xms_edov' => true,
    ]);

    expect($identity?->emailVerified)->toBeTrue();
});

test('the claim is read when Entra sends it as a string', function () {
    // Optional claims have been observed arriving as strings rather than
    // JSON booleans, and the branch beside this one already allows for the
    // same on email_verified.
    expect(microsoftIdentity(['tid' => TENANT, 'email' => 'a@example.test', 'xms_edov' => 'true'])?->emailVerified)
        ->toBeTrue();
});

test('a false or junk claim is not verified', function (mixed $value) {
    expect(microsoftIdentity(['tid' => TENANT, 'email' => 'a@example.test', 'xms_edov' => $value])?->emailVerified)
        ->toBeFalse();
})->with([
    'false' => [false],
    '"false"' => ['false'],
    'null' => [null],
    'empty' => [''],
    'a typo' => ['ture'],
    'zero' => [0],
]);

test('the domain-owner claim alone, from the wrong tenant, is not enough', function () {
    // Both halves. xms_edov says the tenant owns the domain; the tid says
    // it is *our* tenant. A foreign tenant that owns its own domain is
    // still a stranger.
    $identity = microsoftIdentity([
        'tid' => 'some-other-tenant',
        'email' => 'admin@example.test',
        'xms_edov' => true,
    ]);

    expect($identity?->emailVerified)->toBeFalse();
});

test('an installation with no tenant pinned verifies nothing', function () {
    expect(microsoftIdentity(['tid' => TENANT, 'email' => 'a@example.test', 'xms_edov' => true], tenantId: null)?->emailVerified)
        ->toBeFalse();
});
