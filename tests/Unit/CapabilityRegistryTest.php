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
        ->and($registry->has(Capability::Branding))->toBeFalse()
        // The counterpart of StorageConfigure above: a self-hosted install
        // configures its own bucket and is never handed one.
        ->and($registry->has(Capability::StorageManaged))->toBeFalse();
});

test('cloud edition has cloud exclusives and none of the community-only capabilities', function () {
    $registry = new CapabilityRegistry(Edition::Cloud);

    expect($registry->has(Capability::Branding))->toBeTrue()
        ->and($registry->has(Capability::PlatformManaged))->toBeTrue()
        ->and($registry->has(Capability::StorageManaged))->toBeTrue()
        ->and($registry->has(Capability::UsersManage))->toBeFalse()
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
        'branding.customize',
        'storage.managed',
        'captcha.managed_keys',
        'platform.managed',
        'ai.connector',
    ]);
});
