<?php

declare(strict_types=1);

/*
 * Runs before Laravel boots, from public/index.php.
 *
 * Its one job is to turn the most common fresh-install mistake — an
 * application whose environment was never configured — into a clear message
 * instead of what the framework does unguided: a stack trace when
 * APP_DEBUG is on, or a bare "500 Server Error" when it is off (which is
 * what a production install ships with, so the operator sees nothing at all
 * about what went wrong).
 *
 * APP_KEY is the sentinel. It is required, install-specific, has no default,
 * and needs no database to check — so its absence unambiguously means "this
 * install was never set up", and it is exactly the value the two documented
 * first-run mistakes leave empty: never copying `.env`, or copying it but
 * not running `php artisan key:generate` (see INSTALL.md).
 *
 * Deliberately dependency-free: plain PHP, no autoloader, no framework, no
 * Dotenv — because those are among the things that may not be in place yet,
 * and a guard that needs the app to be working cannot report that it isn't.
 */

/**
 * Resolve a variable from the real process environment.
 *
 * The real environment wins over the `.env` file, matching Laravel: Docker
 * and most production setups inject variables directly and never write a
 * `.env` at all.
 */
function projectsend_preflight_env(string $key): ?string
{
    $value = getenv($key);

    if (is_string($value) && $value !== '') {
        return $value;
    }

    foreach ([$_SERVER, $_ENV] as $bag) {
        if (isset($bag[$key]) && is_string($bag[$key]) && $bag[$key] !== '') {
            return $bag[$key];
        }
    }

    return null;
}

/**
 * Why the application cannot start yet, or null if it can.
 *
 * Pure: it reads the environment and, if present, `$root/.env`, and returns
 * either null (configured — hand back to Laravel) or a [title, reason, fix]
 * triple describing what to do. No output, no exit — so it is testable.
 *
 * @return array{0: string, 1: string, 2: string}|null
 */
function projectsend_preflight_failure(string $root): ?array
{
    $appKey = projectsend_preflight_env('APP_KEY');
    $envFile = $root.'/.env';
    $hasEnvFile = is_file($envFile);

    // A .env that is there but unreadable looks exactly like one that was
    // never created — is_file() is false either way, since it has to follow
    // the link and stat the target — and the two need opposite advice.
    // Reported from the official Docker image, where .env is a symlink into
    // storage/: the image left /var/www/html world-writable and owned by a
    // uid that no longer existed, so the kernel's fs.protected_symlinks
    // refused to let the php-fpm worker follow it. Every request said "no
    // .env file was found" while `docker exec ... cat .env`, as root, printed
    // it back perfectly — which is the most misleading pair of facts this
    // guard could possibly hand somebody.
    $unreadableEnv = $hasEnvFile ? ! is_readable($envFile) : is_link($envFile);

    // Fall back to a cheap read of just APP_KEY from the file — we are not
    // going to boot Dotenv or parse the whole thing for one value. Skipped
    // when the file cannot be read, or file() emits a PHP warning into the
    // page this guard exists to keep clean.
    if ($appKey === null && $hasEnvFile && ! $unreadableEnv) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^\s*(?:export\s+)?APP_KEY\s*=\s*(.*)$/', $line, $m) === 1) {
                // Strip the surrounding quotes Dotenv would also strip.
                $appKey = trim(trim($m[1]), "\"'");

                break;
            }
        }
    }

    if ($appKey !== null && $appKey !== '') {
        return null;
    }

    if ($unreadableEnv) {
        return [
            'ProjectSend cannot read its configuration',
            'A <code>.env</code> is in place, but the user PHP runs as cannot read it — or, if it '
                .'is a symlink, cannot follow it. Note that <code>root</code> is exempt from both '
                .'checks, so reading the file over <code>docker exec</code> or <code>sudo</code> '
                .'proves nothing here.',
            "Compare who owns the file with who PHP runs as, and check the directory holding it:\n\n"
                ."ls -ln .env\nstat -c '%n %U %a' . .env",
        ];
    }

    if (! $hasEnvFile) {
        return [
            'ProjectSend is not configured yet',
            'No <code>.env</code> file was found, and no <code>APP_KEY</code> is set in this '
                .'environment. ProjectSend needs an application key before it can start.',
            "Copy the example configuration, then generate the key:\n\n"
                ."cp .env.example .env\nphp artisan key:generate",
        ];
    }

    return [
        'ProjectSend is not configured yet',
        'A <code>.env</code> file is present, but its <code>APP_KEY</code> is empty. ProjectSend '
            .'needs an application key before it can start.',
        "Generate the key:\n\nphp artisan key:generate",
    ];
}

/**
 * Render the message and stop, or return so Laravel can take over.
 *
 * Called with no argument from public/index.php; the argument exists so
 * tests can point it at a fixture directory.
 */
function projectsend_preflight(?string $root = null): void
{
    $failure = projectsend_preflight_failure($root ?? dirname(__DIR__));

    if ($failure === null) {
        return;
    }

    [$title, $reason, $fix] = $failure;

    // 503, not 500: the install is temporarily unavailable pending setup,
    // not broken. Retry-After keeps a well-behaved proxy or uptime check
    // from hammering it while someone finishes the install.
    if (! headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Retry-After: 3600');
        header('Cache-Control: no-store');
    }

    $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<title>'.$e($title).'</title><style>'
        .':root{color-scheme:light dark}'
        .'*{box-sizing:border-box}'
        .'body{margin:0;min-height:100vh;display:grid;place-items:center;padding:1.5rem;'
        .'background:#f4f5f7;color:#16181d;'
        .'font:16px/1.6 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
        .'@media(prefers-color-scheme:dark){body{background:#0e0f13;color:#e8eaef}'
        .'.card{background:#17191f!important;border-color:#272a32!important}'
        .'code,pre{background:#0e0f13!important;border-color:#272a32!important}}'
        .'.card{max-width:34rem;width:100%;background:#fff;border:1px solid #dcdfe6;'
        .'border-radius:14px;padding:2rem 2.25rem;'
        .'box-shadow:0 1px 2px rgba(0,0,0,.05),0 18px 40px -28px rgba(0,0,0,.4)}'
        .'h1{margin:0 0 .75rem;font-size:1.4rem;letter-spacing:-.01em}'
        .'p{margin:0 0 1rem;color:inherit}'
        .'code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.9em;'
        .'background:#f4f5f7;border:1px solid #dcdfe6;border-radius:4px;padding:.05em .35em}'
        .'pre{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.86rem;'
        .'background:#f4f5f7;border:1px solid #dcdfe6;border-radius:8px;padding:.9rem 1.1rem;'
        .'overflow-x:auto;white-space:pre;line-height:1.7}'
        .'.hint{font-size:.9rem;color:#5d5770}'
        .'@media(prefers-color-scheme:dark){.hint{color:#a49db4}}'
        .'</style></head><body><main class="card">'
        .'<h1>'.$e($title).'</h1>'
        .'<p>'.$reason.'</p>'
        .'<p>'.$e(projectsend_preflight_intro($fix)).'</p>'
        .'<pre>'.$e(projectsend_preflight_command($fix)).'</pre>'
        .'<p class="hint">Run these as the user your web server runs as. '
        .'The full procedure is in <code>INSTALL.md</code>.</p>'
        .'</main></body></html>';

    exit;
}

/**
 * The sentence before the command block in a fix string.
 */
function projectsend_preflight_intro(string $fix): string
{
    return trim(explode("\n\n", $fix, 2)[0]);
}

/**
 * The command block from a fix string.
 */
function projectsend_preflight_command(string $fix): string
{
    $parts = explode("\n\n", $fix, 2);

    return trim($parts[1] ?? $parts[0]);
}

// Auto-run when included by the front controller; suppressed under test so
// the pure helpers above can be exercised against fixtures.
if (! defined('PROJECTSEND_PREFLIGHT_TEST')) {
    projectsend_preflight();
}
