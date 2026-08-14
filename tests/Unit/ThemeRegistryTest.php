<?php

declare(strict_types=1);

use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Theming\ThemeRegistry;

test('"default" always sorts first in available(), regardless of registration order', function () {
    $registry = new ThemeRegistry;
    $registry->register('zzz', 'ZZZ', 'zzz description');
    $registry->register('aaa', 'AAA', 'aaa description');
    $registry->register('default', 'Default', 'default description');
    $registry->register('mmm', 'MMM', 'mmm description');

    $keys = array_map(fn ($theme) => $theme->key, $registry->available(new CapabilityRegistry(Edition::Community)));

    expect($keys)->toBe(['default', 'zzz', 'aaa', 'mmm']);
});

test('the rest keep their relative registration order behind "default"', function () {
    $registry = new ThemeRegistry;
    $registry->register('b', 'B', 'b description');
    $registry->register('a', 'A', 'a description');
    $registry->register('default', 'Default', 'default description');
    $registry->register('c', 'C', 'c description');

    $keys = array_map(fn ($theme) => $theme->key, $registry->available(new CapabilityRegistry(Edition::Community)));

    expect($keys)->toBe(['default', 'b', 'a', 'c']);
});
