<?php

declare(strict_types=1);

use App\Modules\Platform\Updates\ReleaseNotes;

/**
 * The changelog is written for people, by hand, and this reads it. Every
 * shape asserted here is one that exists in CHANGELOG.md today — the
 * parser is not general-purpose Markdown, and does not need to be.
 */
function changelog(): string
{
    return <<<'MARKDOWN'
        # Changelog

        What changed in each release, written for the people running it.

        ## Unreleased

        Collected as it lands.

        ## 2.2.0 — 2026-09-01

        A short release.

        ### Fixed

        - **Downloads no longer stall.** The part that hung is gone, and the retry
          is now bounded.

        ## 2.1.0 — 2026-08-20

        ### Added

        - **A REST API.** Files and clients, with [scoped tokens](https://example.com) and `php artisan` docs.
        - **Clients sign in with their email address**, not their username.

        ### Upgrade notes

        - Run the migrations before starting the workers.

        ## 2.0.0 — 2026-08-14

        The rewrite.
        MARKDOWN;
}

it('reads a release into its heading, date and items', function (): void {
    $releases = (new ReleaseNotes)->all(changelog());

    expect($releases)->toHaveCount(3)
        ->and($releases[0]['version'])->toBe('2.2.0')
        ->and($releases[0]['date'])->toBe('2026-09-01')
        ->and($releases[0]['intro'])->toBe(['A short release.'])
        ->and($releases[0]['groups'][0]['heading'])->toBe('Fixed')
        ->and($releases[0]['groups'][0]['items'][0]['title'])->toBe('Downloads no longer stall');
});

// "Unreleased" carries no version number, so it never matches the heading
// pattern — which is the whole reason the pattern requires one.
it('skips the unreleased section', function (): void {
    $versions = array_column((new ReleaseNotes)->all(changelog()), 'version');

    expect($versions)->toBe(['2.2.0', '2.1.0', '2.0.0']);
});

// Bullets wrap at 100 columns in the file; a reader wants the sentence.
it('joins a bullet that wraps across lines', function (): void {
    $item = (new ReleaseNotes)->all(changelog())[0]['groups'][0]['items'][0];

    expect($item['body'])->toBe('The part that hung is gone, and the retry is now bounded.');
});

it('keeps link text and drops the markup around it', function (): void {
    $item = (new ReleaseNotes)->all(changelog())[1]['groups'][0]['items'][0];

    expect($item['title'])->toBe('A REST API')
        ->and($item['body'])->toBe('Files and clients, with scoped tokens and php artisan docs.');
});

// A bold fragment the sentence continues through is not a lead-in.
// Promoting it produced "Clients sign in with their email address. , not
// their username" on the page.
it('leaves a bullet whose bold opening is not a sentence intact', function (): void {
    $item = (new ReleaseNotes)->all(changelog())[1]['groups'][0]['items'][1];

    expect($item['title'])->toBe('')
        ->and($item['body'])->toBe('Clients sign in with their email address, not their username.');
});

it('keeps a bullet with no bold at all', function (): void {
    $group = (new ReleaseNotes)->all(changelog())[1]['groups'][1];

    expect($group['heading'])->toBe('Upgrade notes')
        ->and($group['items'][0])->toBe(['title' => '', 'body' => 'Run the migrations before starting the workers.']);
});

// The point of recording both versions: somebody who updates twice a year
// crosses several releases at once, and all of them are news to them.
it('returns every release in the gap, newest first', function (): void {
    $versions = array_column((new ReleaseNotes)->between('2.0.0', '2.2.0', changelog()), 'version');

    expect($versions)->toBe(['2.2.0', '2.1.0']);
});

it('excludes the version being updated from, and anything newer than the one reached', function (): void {
    $versions = array_column((new ReleaseNotes)->between('2.1.0', '2.1.0', changelog()), 'version');

    expect($versions)->toBe([]);
});

// The first update of an installation older than this feature has no
// recorded previous version. One release is better than the entire history.
it('shows only the version reached when the previous one is unknown', function (): void {
    $versions = array_column((new ReleaseNotes)->between('', '2.1.0', changelog()), 'version');

    expect($versions)->toBe(['2.1.0']);
});

// A release whose notes were never written up: the page drops the section
// rather than inventing one.
it('returns nothing for a version the changelog does not mention', function (): void {
    expect((new ReleaseNotes)->between('2.2.0', '2.3.0', changelog()))->toBe([]);
});

// The real file, so a change to how the changelog is written shows up
// here rather than on an administrator's screen.
it('reads the changelog this release actually ships', function (): void {
    $releases = (new ReleaseNotes)->between('', (string) config('projectsend.version'));

    expect($releases)->not->toBeEmpty()
        ->and($releases[0]['version'])->toBe(config('projectsend.version'))
        ->and($releases[0]['groups'])->not->toBeEmpty();
});
