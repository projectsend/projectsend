<?php

declare(strict_types=1);

// Load the guard without letting it auto-run against the real environment.
define('PROJECTSEND_PREFLIGHT_TEST', true);
require_once __DIR__.'/../../bootstrap/preflight.php';

/**
 * A fixture directory, optionally with a .env whose APP_KEY line is $appKey
 * (null = no APP_KEY line at all; '' = present but empty).
 */
function preflightFixture(?string $appKey, bool $withEnvFile): string
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
