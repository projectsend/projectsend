<?php

declare(strict_types=1);

use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Capabilities\Edition;

test('every capability declares at least one edition', function () {
    foreach (Capability::cases() as $capability) {
        expect($capability->editions())->not->toBeEmpty();
    }
});

test('community edition has the self-management capabilities and no cloud exclusives', function () {
    $registry = new CapabilityRegistry(Edition::Community);

    expect($registry->has(Capability::UsersManage))->toBeTrue()
        ->and($registry->has(Capability::StorageConfigure))->toBeTrue()
        ->and($registry->has(Capability::EmailTransportConfigure))->toBeTrue()
        ->and($registry->has(Capability::SystemUpdates))->toBeTrue()
        ->and($registry->has(Capability::SchedulerMonitoring))->toBeTrue()
        ->and($registry->has(Capability::CustomAssets))->toBeTrue()
        // Branding is both editions since 2026-08-28. Dressing an
        // installation in its own logo is not a hosted concern; what a
        // hosted *plan* withholds is answered by subtraction below.
        ->and($registry->has(Capability::Branding))->toBeTrue()
        // The white-label half is not. Taking ProjectSend's name off the
        // pages a customer's visitors see is what a hosted customer pays
        // for, and the code that can answer "hide it" is not in this repo.
        ->and($registry->has(Capability::AttributionHide))->toBeFalse()
        // The counterpart of StorageConfigure above: a self-hosted install
        // configures its own bucket and is never handed one.
        ->and($registry->has(Capability::StorageManaged))->toBeFalse();
});

test('cloud edition has cloud exclusives and none of the community-only capabilities', function () {
    $registry = new CapabilityRegistry(Edition::Cloud);

    expect($registry->has(Capability::Branding))->toBeTrue()
        ->and($registry->has(Capability::PlatformManaged))->toBeTrue()
        // Both editions since 2.2.0: a platform provisions seats, it does
        // not decide who fills them. See the case's own comment.
        ->and($registry->has(Capability::UsersManage))->toBeTrue()
        ->and($registry->has(Capability::StorageManaged))->toBeTrue()
        ->and($registry->has(Capability::StorageConfigure))->toBeFalse()
        ->and($registry->has(Capability::EmailTransportConfigure))->toBeFalse()
        ->and($registry->has(Capability::SystemUpdates))->toBeFalse()
        ->and($registry->has(Capability::SchedulerMonitoring))->toBeFalse()
        ->and($registry->has(Capability::CustomAssets))->toBeFalse();
});

test('enabledKeys returns the string keys of enabled capabilities', function () {
    $registry = new CapabilityRegistry(Edition::Cloud);

    // Order follows the enum, which is the order the control plane reads
    // them in — see GET /platform/v1/status in cloud-modules.
    expect($registry->enabledKeys())->toBe([
        'users.manage',
        'branding.customize',
        'attribution.hide',
        'storage.managed',
        'captcha.managed_keys',
        'platform.managed',
        'ai.connector',
    ]);
});

/*
|--------------------------------------------------------------------------
| Subtraction
|--------------------------------------------------------------------------
|
| An edition grants; an operator may take away. The asymmetry is the whole
| design, and these pin it: the list can only ever make the answer smaller.
*/

test('a capability the edition grants can be taken away', function () {
    // The case this was built for: branding on a free hosted plan.
    $registry = new CapabilityRegistry(Edition::Cloud, 'branding.customize');

    expect($registry->has(Capability::Branding))->toBeFalse()
        ->and((new CapabilityRegistry(Edition::Community, 'branding.customize'))->has(Capability::Branding))->toBeFalse();
});

test('taking one away leaves the rest alone', function () {
    $registry = new CapabilityRegistry(Edition::Cloud, 'branding.customize');

    expect($registry->has(Capability::Branding))->toBeFalse()
        ->and($registry->has(Capability::AttributionHide))->toBeTrue()
        ->and($registry->has(Capability::StorageManaged))->toBeTrue();
});

test('nothing in the environment can grant a capability the edition lacks', function () {
    // The reason this list is subtractive and not a general override. A
    // variable that could add would put the hosted edition's proprietary
    // screens one line of .env away on every self-hosted install, which is
    // not a gate at all.
    $registry = new CapabilityRegistry(Edition::Community, '');

    expect($registry->has(Capability::StorageManaged))->toBeFalse()
        ->and($registry->has(Capability::AttributionHide))->toBeFalse();
});

test('a stale key names something that no longer exists, and is ignored', function () {
    // The variable outlives both the plan that wrote it and the release
    // that named the key. An instance refusing to boot over one would be a
    // self-inflicted outage on upgrade day.
    $registry = new CapabilityRegistry(Edition::Cloud, 'branding.customize,capability.that.never.existed');

    expect($registry->has(Capability::Branding))->toBeFalse()
        ->and($registry->enabledKeys())->toContain('storage.managed');
});

test('spacing and empty entries are somebody typing, not a different instruction', function () {
    $registry = new CapabilityRegistry(Edition::Cloud, ' branding.customize ,, ');

    expect($registry->has(Capability::Branding))->toBeFalse();
});

test('an unset or empty list takes nothing away', function () {
    foreach ([null, '', '   '] as $value) {
        $registry = new CapabilityRegistry(Edition::Cloud, $value);

        expect($registry->has(Capability::Branding))
            ->toBeTrue(var_export($value, true).' should disable nothing');
    }
});

test('what the installation reports is what it actually grants', function () {
    // projectsend:status reports enabledKeys(), and a control plane
    // compares it against the plan it wrote. A subtracted capability that
    // still appeared there would make that comparison useless.
    $registry = new CapabilityRegistry(Edition::Cloud, 'branding.customize');

    expect($registry->enabledKeys())->not->toContain('branding.customize')
        ->and($registry->enabledKeys())->toContain('attribution.hide');
});
