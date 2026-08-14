<?php

declare(strict_types=1);

/**
 * A source scan, for the same reason IntlLocaleUsageTest is one: there is no
 * JavaScript test runner here, and none of the four checks that gate CI can
 * see this class of bug. The types are fine, the lint is fine, the build is
 * fine, and the PHP suite never renders a component.
 *
 * What it protects: every date the application shows has to be rendered in
 * the viewer's own locale *and* their own timezone, both of which arrive on
 * the shared Inertia props. A `new Date(iso).toLocaleString()` written by
 * hand silently uses the browser's language and the browser's zone instead —
 * it looks right to whoever wrote it and is wrong for everybody else.
 *
 * That is not hypothetical. Before `useFormatDate()` existed, forty render
 * sites across twenty-two files were driven by fourteen separate formatter
 * implementations; thirteen of them passed no locale at all, so a client who
 * had chosen Spanish read their file list in English dates. Each one was
 * individually reasonable. The pile was not.
 *
 * `resources/js/hooks/use-format-date.ts` is the one way in; this keeps it
 * that way.
 */
$pattern = '/\.toLocale(?:String|DateString|TimeString)\s*\(/';

// dirname() rather than base_path(): this runs at file scope, where the
// application container is not booted yet.
$root = dirname(__DIR__, 2);

// The single place allowed to call Intl's date formatters directly — it is
// what the hook is built out of, and it takes locale and timezone explicitly
// so it stays usable outside a React tree.
$allowed = 'resources/js/lib/format-date.ts';

$offenders = [];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/resources/js', FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
        continue;
    }

    $relative = str_replace($root.'/', '', $file->getPathname());

    if ($relative === $allowed) {
        continue;
    }

    foreach (file($file->getPathname()) as $number => $line) {
        if (preg_match($pattern, $line) === 1) {
            $offenders[] = $relative.':'.($number + 1);
        }
    }
}

test('dates are never formatted outside the shared helper', function () use ($offenders) {
    expect($offenders)->toBe([], implode("\n", array_merge(
        ['These format a date by hand, so they follow the browser rather than the viewer\'s chosen language and timezone.'],
        ['Use useFormatDate() — date() and dateTime() for instants, calendarDate() for a bare YYYY-MM-DD.'],
        $offenders,
    )));
});

test('the helper threads the timezone into every instant it formats', function () use ($root, $allowed) {
    $source = file_get_contents($root.'/'.$allowed);

    // The two that render a point in time have to pass timeZone through, or
    // the whole feature is a setting that changes nothing.
    expect(substr_count($source, 'timeZone }'))->toBeGreaterThanOrEqual(2);

    // And the one that renders a calendar date has to pin UTC instead, or a
    // file's expiry moves a day for everyone west of Greenwich — the bug
    // that shipped before this existed.
    expect($source)->toContain("timeZone: 'UTC'");
});
