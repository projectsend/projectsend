<?php

declare(strict_types=1);

/**
 * Route::redirect()'s destination needs a leading slash, or Laravel
 * deliberately emits a *relative* Location header instead of an
 * absolute one (Illuminate\Routing\RedirectController strips the
 * leading slash it would otherwise generate whenever the destination
 * you passed doesn't have one). A browser resolves a relative Location
 * against the current path's directory — for a source path more than
 * one segment deep, that silently redirects somewhere wrong (e.g.
 * 'system/settings' -> 'system/settings/general' resolves to
 * '/system/system/settings/general', a 404) instead of throwing.
 *
 * This genuinely can't be caught with an HTTP feature test:
 * TestResponse::assertRedirect() normalizes both sides through
 * url()->to() before comparing, which erases the exact relative-vs-
 * absolute distinction that breaks a real browser — and, confirmed
 * empirically, even asserting the raw Location header directly still
 * doesn't reproduce the bug under Laravel's test HTTP kernel, only
 * against a real request through the actual web server. A static
 * source check is the only reliable way to keep this from regressing.
 */
test('every Route::redirect() destination in routes/ uses a leading-slash absolute path', function () {
    $offenders = [];

    foreach (glob(__DIR__.'/../../routes/*.php') as $file) {
        $contents = file_get_contents($file);

        preg_match_all(
            "/Route::redirect\\(\\s*'[^']*'\\s*,\\s*'([^']*)'/",
            $contents,
            $matches,
        );

        foreach ($matches[1] as $destination) {
            if (! str_starts_with($destination, '/')) {
                $offenders[] = basename($file).': '.$destination;
            }
        }
    }

    expect($offenders)->toBe([]);
});
