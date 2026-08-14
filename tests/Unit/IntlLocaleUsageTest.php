<?php

declare(strict_types=1);

/**
 * A source scan, not a behaviour test, because the behaviour it protects
 * cannot be reached from PHP and there is no JavaScript test runner here.
 *
 * The bug it exists to prevent: this application's locale keys are catalogue
 * file names (`lang/zh_CN.json`), while every `Intl` API — including
 * `toLocaleString` and friends — requires a BCP 47 tag (`zh-CN`). Handed the
 * underscore form they throw `RangeError: Invalid language tag` rather than
 * degrading. Thrown during a React render that unmounts the tree, so the user
 * gets a blank page and the server log stays empty, because nothing ever
 * reached the server.
 *
 * Selecting Chinese did exactly that, and it was found by a person rather than
 * by any of the four checks that gate CI: the types are fine, the lint is
 * fine, the build is fine, and the PHP suite never renders a component.
 * `resources/js/lib/intl-locale.ts` is the conversion; this keeps every call
 * site going through it.
 */
$pattern = '/\.toLocale(?:String|DateString|TimeString)\(\s*locale\b|Intl\.[A-Za-z]+\(\s*\[?\s*locale\b/';

// dirname() rather than base_path(): this runs at file scope, where the
// application container is not booted yet.
$root = dirname(__DIR__, 2);

$offenders = [];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/resources/js', FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
        continue;
    }

    foreach (file($file->getPathname()) as $number => $line) {
        if (preg_match($pattern, $line) === 1) {
            $offenders[] = str_replace($root.'/', '', $file->getPathname()).':'.($number + 1);
        }
    }
}

test('the raw locale key is never handed to Intl', function () use ($offenders) {
    expect($offenders)->toBe([], implode("\n", array_merge(
        ['These pass the app locale straight to Intl, which throws on keys like zh_CN and blanks the page.'],
        ['Wrap it: intlLocale(locale) — see resources/js/lib/intl-locale.ts.'],
        $offenders,
    )));
});

test('intlLocale converts catalogue keys and refuses nonsense', function () use ($root) {
    $source = file_get_contents($root.'/resources/js/lib/intl-locale.ts');

    // The helper the test above points people at has to keep existing, and
    // has to keep doing the two things that matter: swap the separator, and
    // never throw.
    expect($source)
        ->toContain("replace(/_/g, '-')")
        ->toContain('Intl.getCanonicalLocales')
        ->toContain('return undefined');
});
