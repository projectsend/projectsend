<?php

declare(strict_types=1);

/**
 * A source scan, like DateFormattingUsageTest and for the same reason: there
 * is no JavaScript test runner here, and none of the checks that gate CI can
 * tell a translated screen from a hardcoded one. The types are fine, the
 * lint is fine, and the PHP suite never renders a component.
 *
 * What it protects: use-translation.ts promises that "every user-facing
 * string in a component must go through t()". The way that promise broke was
 * never one stray string on a busy page — it was whole pages that skipped
 * the hook entirely: five auth and settings screens shipped with zero calls,
 * so a client who had chosen Spanish reset their password in English. One of
 * them had copied its <Head> title from the profile page too, so the browser
 * tab said "Profile settings" over the password form.
 *
 * Two scans, matching the two shapes of that miss:
 *
 * - Every page under pages/auth and pages/settings must use the hook. These
 *   screens always carry copy of their own (a title at minimum), so a page
 *   here with no useTranslation is a page somebody forgot, not a page with
 *   nothing to say. Pages elsewhere are not scanned — a public theme page
 *   can legitimately render nothing but data.
 * - No literal <Head title="..."> anywhere. A browser-tab title is user-
 *   facing copy like any other, and the literal form is also where the
 *   copy-paste mistake above lived.
 */

// dirname() rather than base_path(): this runs at file scope, where the
// application container is not booted yet.
$root = dirname(__DIR__, 2);

$untranslatedPages = [];

foreach (['auth', 'settings'] as $section) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/resources/js/pages/'.$section, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'tsx') {
            continue;
        }

        if (! str_contains((string) file_get_contents($file->getPathname()), 'useTranslation')) {
            $untranslatedPages[] = str_replace($root.'/', '', $file->getPathname());
        }
    }
}

$literalTitles = [];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/resources/js', FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if ($file->getExtension() !== 'tsx') {
        continue;
    }

    $relative = str_replace($root.'/', '', $file->getPathname());

    foreach (file($file->getPathname()) as $number => $line) {
        if (str_contains($line, '<Head title="')) {
            $literalTitles[] = $relative.':'.($number + 1);
        }
    }
}

test('every auth and settings page goes through the translator', function () use ($untranslatedPages) {
    expect($untranslatedPages)->toBe([], implode("\n", array_merge(
        ['These pages never call useTranslation(), so everything they say is English in every language.'],
        ['Wrap each user-facing string: t(\'...\') — see resources/js/hooks/use-translation.ts.'],
        $untranslatedPages,
    )));
});

test('no page hardcodes its browser-tab title', function () use ($literalTitles) {
    expect($literalTitles)->toBe([], implode("\n", array_merge(
        ['These <Head> titles are string literals, so the tab reads English in every language.'],
        ['Pass the title through t() — <Head title={t(\'...\')} />.'],
        $literalTitles,
    )));
});
