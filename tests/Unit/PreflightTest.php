<?php

declare(strict_types=1);

// Load the guard without letting it auto-run against the real environment.
define('PROJECTSEND_PREFLIGHT_TEST', true);
require_once __DIR__.'/../../bootstrap/preflight.php';

/**
 * A fixture directory, optionally with a .env whose APP_KEY line is $appKey
 * (null = no APP_KEY line at all; '' = present but empty).
 *
 * $installed and $built are the two things a release zip ships and a `git
 * clone` does not — vendored dependencies and a compiled frontend. They are
 * present by default so that the configuration cases below test only the
 * thing they name, and each has its own test further down.
 */
function preflightFixture(?string $appKey, bool $withEnvFile, bool $installed = true, bool $built = true): string
{
    $dir = sys_get_temp_dir().'/preflight-'.bin2hex(random_bytes(6));
    mkdir($dir);

    if ($withEnvFile) {
        $lines = ['APP_ENV=production'];
        if ($appKey !== null) {
            $lines[] = "APP_KEY=$appKey";
        }
        $lines[] = 'DB_CONNECTION=mysql';
        file_put_contents($dir.'/.env', implode("\n", $lines)."\n");
    }

    if ($installed) {
        mkdir($dir.'/vendor');
        file_put_contents($dir.'/vendor/autoload.php', '<?php');
    }

    if ($built) {
        mkdir($dir.'/public/build', 0777, true);
        file_put_contents($dir.'/public/build/manifest.json', '{}');
    }

    return $dir;
}

beforeEach(function (): void {
    // The real environment must not leak into these checks.
    putenv('APP_KEY');
    unset($_SERVER['APP_KEY'], $_ENV['APP_KEY']);
});

it('passes when APP_KEY comes from the environment, with no .env file', function (): void {
    putenv('APP_KEY=base64:'.base64_encode(random_bytes(32)));

    expect(projectsend_preflight_failure(preflightFixture(null, false)))->toBeNull();
});

it('passes when APP_KEY is set in the .env file', function (): void {
    $dir = preflightFixture('base64:'.base64_encode(random_bytes(32)), true);

    expect(projectsend_preflight_failure($dir))->toBeNull();
});

it('fails when there is no .env file and no APP_KEY in the environment', function (): void {
    $failure = projectsend_preflight_failure(preflightFixture(null, false));

    expect($failure)->not->toBeNull()
        ->and($failure[1])->toContain('No <code>.env</code> file was found')
        ->and(projectsend_preflight_command($failure[2]))->toContain('cp .env.example .env')
        ->and(projectsend_preflight_command($failure[2]))->toContain('php artisan key:generate');
});

it('fails when a .env exists but its APP_KEY is empty', function (): void {
    $failure = projectsend_preflight_failure(preflightFixture('', true));

    expect($failure)->not->toBeNull()
        ->and($failure[1])->toContain('APP_KEY</code> is empty')
        ->and(projectsend_preflight_command($failure[2]))->toContain('php artisan key:generate')
        ->and(projectsend_preflight_command($failure[2]))->not->toContain('cp .env.example');
});

it('fails when a .env exists with no APP_KEY line at all', function (): void {
    // No APP_KEY line is the same "empty key" case to the operator.
    expect(projectsend_preflight_failure(preflightFixture(null, true)))->not->toBeNull();
});

it('says so when .env is a symlink it cannot follow, rather than that none exists', function (): void {
    // The official image symlinks .env into storage/. When that link cannot
    // be followed — a missing target, or a directory whose permissions stop
    // the web server user traversing it — every stat says "no such file",
    // and "copy .env.example" is then exactly the wrong thing to be told.
    $dir = preflightFixture(null, false);
    symlink($dir.'/storage/.env', $dir.'/.env');

    $failure = projectsend_preflight_failure($dir);

    expect($failure)->not->toBeNull()
        ->and($failure[0])->toBe('ProjectSend cannot read its configuration')
        ->and($failure[1])->toContain('cannot read it')
        ->and(projectsend_preflight_command($failure[2]))->toContain('ls -ln .env')
        ->and(projectsend_preflight_command($failure[2]))->not->toContain('cp .env.example');
});

it('says so when .env exists but its permissions deny the reader', function (): void {
    $dir = preflightFixture('base64:'.base64_encode(random_bytes(32)), true);
    chmod($dir.'/.env', 0000);
    clearstatcache();

    $failure = projectsend_preflight_failure($dir);

    expect($failure)->not->toBeNull()
        ->and($failure[0])->toBe('ProjectSend cannot read its configuration');
})->skip(fn (): bool => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root can read anything, so the mode says nothing');

it('reads a quoted APP_KEY the way Dotenv would', function (): void {
    $dir = preflightFixture('"base64:'.base64_encode(random_bytes(32)).'"', true);

    expect(projectsend_preflight_failure($dir))->toBeNull();
});

it('lets the real environment override an empty .env key', function (): void {
    // Docker injects APP_KEY without ever writing it into the file.
    putenv('APP_KEY=base64:'.base64_encode(random_bytes(32)));

    expect(projectsend_preflight_failure(preflightFixture('', true)))->toBeNull();
});

// Only reachable from source — a release zip ships vendored. public/index.php
// requires the autoloader on the line after this guard, so without the check
// this state is a bare fatal with an empty log (#1627).
it('fails when Composer has never run', function (): void {
    $failure = projectsend_preflight_failure(
        preflightFixture('base64:'.base64_encode(random_bytes(32)), true, installed: false)
    );

    expect($failure)->not->toBeNull()
        ->and($failure[0])->toBe('ProjectSend is not installed yet')
        ->and(projectsend_preflight_command($failure[2]))->toContain('composer install');
});

// The fix the .env branch prints is `php artisan key:generate`, which cannot
// run without the autoloader — so telling somebody about the key first would
// hand them a second, more confusing error.
it('reports missing dependencies before a missing .env', function (): void {
    $failure = projectsend_preflight_failure(preflightFixture(null, false, installed: false));

    expect($failure[0])->toBe('ProjectSend is not installed yet');
});

it('fails when the frontend has never been built', function (): void {
    $failure = projectsend_preflight_failure(
        preflightFixture('base64:'.base64_encode(random_bytes(32)), true, built: false)
    );

    expect($failure)->not->toBeNull()
        ->and($failure[0])->toBe('ProjectSend is not built yet')
        ->and(projectsend_preflight_command($failure[2]))->toContain('npm run build');
});

// laravel-vite-plugin writes public/hot while `npm run dev` runs, and the dev
// server serves the assets. Blocking a developer mid-session would make this
// guard worse than the exception it replaces.
it('treats a running vite dev server as built', function (): void {
    $dir = preflightFixture('base64:'.base64_encode(random_bytes(32)), true, built: false);
    mkdir($dir.'/public');
    file_put_contents($dir.'/public/hot', 'http://localhost:5173');

    expect(projectsend_preflight_failure($dir))->toBeNull();
});

// A key is the more fundamental problem of the two, and its message is the
// one that has to survive.
it('reports a missing APP_KEY before unbuilt assets', function (): void {
    $failure = projectsend_preflight_failure(preflightFixture('', true, built: false));

    expect($failure[0])->toBe('ProjectSend is not configured yet');
});
