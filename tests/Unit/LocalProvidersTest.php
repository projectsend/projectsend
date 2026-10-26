<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;

/*
 * bootstrap/providers.local.php is the one place an installation can list
 * its own providers without an update overwriting it (see UPDATE.md). These
 * write that file for real, because the path is the feature: a test that
 * pointed providers.php somewhere else would not prove anything about it.
 */

function localProvidersPath(): string
{
    return dirname(__DIR__, 2).'/bootstrap/providers.local.php';
}

/**
 * @return list<string>
 */
function bootstrapProviders(): array
{
    return array_values(require dirname(__DIR__, 2).'/bootstrap/providers.php');
}

test('without a local file, the list is core only', function () {
    if (is_file(localProvidersPath())) {
        $this->markTestSkipped('This checkout has its own bootstrap/providers.local.php.');
    }

    $providers = bootstrapProviders();

    expect($providers[array_key_last($providers)])->toBe(AppServiceProvider::class);
});

test('local providers are appended after every core provider', function () {
    if (is_file(localProvidersPath())) {
        $this->markTestSkipped('This checkout has its own bootstrap/providers.local.php.');
    }

    $core = bootstrapProviders();

    file_put_contents(localProvidersPath(), "<?php\n\nreturn ['Acme\\\\FirstProvider', 'Acme\\\\SecondProvider'];\n");

    try {
        expect(bootstrapProviders())->toBe([...$core, 'Acme\\FirstProvider', 'Acme\\SecondProvider']);
    } finally {
        unlink(localProvidersPath());
    }
});

test('no release can ship the local file', function () {
    $ignored = file(dirname(__DIR__, 2).'/.gitignore', FILE_IGNORE_NEW_LINES);

    expect($ignored)->toContain('/bootstrap/providers.local.php');
});
