<?php

declare(strict_types=1);

namespace App\Modules\Platform\Updates;

/**
 * Reads CHANGELOG.md and hands back the releases in a version range, as
 * structure rather than as Markdown.
 *
 * The changelog is the source because it is the only description of a
 * release that is *inside* the release. GitHub's release notes would need
 * a network call at the exact moment an administrator has just updated —
 * possibly on a server with no outbound access at all — to describe code
 * that is already sitting on disk. This file ships in every zip
 * (verify-release.sh refuses one without it) and therefore in the image
 * built from it.
 *
 * Parsed rather than rendered: nothing here ever becomes HTML. The page
 * gets titles and sentences and does its own formatting, so a stray
 * `<script>` in a changelog written years from now cannot become one.
 */
class ReleaseNotes
{
    /**
     * `## 2.1.0 — 2026-08-20`, with the date optional. The separator is an
     * em dash in every entry written so far; a hyphen is accepted too,
     * since which one gets typed is not something to lose a release note
     * over.
     */
    private const HEADING = '/^##\s+v?(?<version>\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)\s*(?:[—–-]\s*(?<date>.+?))?\s*$/u';

    /**
     * Every release later than $from, up to and including $to, newest
     * first.
     *
     * An empty $from means the installation never recorded a version, so
     * there is no gap to describe and only $to is shown. Versions are
     * compared, not string-matched: a changelog holds every release ever
     * made, and only the ones this update actually crossed are news.
     *
     * @return list<array{version: string, date: string, intro: list<string>, groups: list<array{heading: string, items: list<array{title: string, body: string}>}>}>
     */
    public function between(string $from, string $to, ?string $changelog = null): array
    {
        if ($to === '') {
            return [];
        }

        return array_values(array_filter(
            $this->all($changelog),
            fn (array $release): bool => version_compare($release['version'], $to, '<=')
                && ($from === '' ? $release['version'] === $to : version_compare($release['version'], $from, '>')),
        ));
    }

    /**
     * Every release in the changelog, newest first, in file order.
     *
     * "Unreleased" is skipped by construction: its heading carries no
     * version number, so it never matches.
     *
     * @return list<array{version: string, date: string, intro: list<string>, groups: list<array{heading: string, items: list<array{title: string, body: string}>}>}>
     */
    public function all(?string $changelog = null): array
    {
        $markdown = $changelog ?? $this->read();

        if ($markdown === null) {
            return [];
        }

        $releases = [];
        $current = null;

        foreach ($this->paragraphs($markdown) as $block) {
            if (preg_match(self::HEADING, $block, $matches) === 1) {
                if ($current !== null) {
                    $releases[] = $current;
                }

                $current = [
                    'version' => $matches['version'],
                    'date' => trim($matches['date'] ?? ''),
                    'intro' => [],
                    'groups' => [],
                ];

                continue;
            }

            if ($current === null) {
                // The changelog's own preamble, before the first release.
                continue;
            }

            $current = $this->absorb($current, $block);
        }

        if ($current !== null) {
            $releases[] = $current;
        }

        return $releases;
    }

    /**
     * Fold one block of the release's body into it.
     *
     * @param  array{version: string, date: string, intro: list<string>, groups: list<array{heading: string, items: list<array{title: string, body: string}>}>}  $release
     * @return array{version: string, date: string, intro: list<string>, groups: list<array{heading: string, items: list<array{title: string, body: string}>}>}
     */
    private function absorb(array $release, string $block): array
    {
        if (preg_match('/^###\s+(?<heading>.+?)\s*$/u', $block, $matches) === 1) {
            $release['groups'][] = ['heading' => $matches['heading'], 'items' => []];

            return $release;
        }

        if (! str_starts_with(ltrim($block), '- ')) {
            // Prose. Before the first "### Added" it is the release's own
            // summary — 2.0.0 opens with three paragraphs of it. After
            // one, it belongs to that group and is rare enough that
            // folding it in as an untitled item beats inventing a shape
            // for it.
            if ($release['groups'] === []) {
                $release['intro'][] = $this->inline($block);

                return $release;
            }

            return $this->append($release, ['title' => '', 'body' => $this->inline($block)]);
        }

        foreach ($this->bullets($block) as $bullet) {
            $release = $this->append($release, $this->item($bullet));
        }

        return $release;
    }

    /**
     * Add an item to the release's last group, opening an unnamed one if
     * there is nothing to add it to — the shape a small fix-only release
     * takes when it lists changes without a `### Heading` above them.
     *
     * @param  array{version: string, date: string, intro: list<string>, groups: list<array{heading: string, items: list<array{title: string, body: string}>}>}  $release
     * @param  array{title: string, body: string}  $item
     * @return array{version: string, date: string, intro: list<string>, groups: list<array{heading: string, items: list<array{title: string, body: string}>}>}
     */
    private function append(array $release, array $item): array
    {
        if ($release['groups'] === []) {
            $release['groups'][] = ['heading' => '', 'items' => []];
        }

        $index = count($release['groups']) - 1;
        $group = $release['groups'][$index];
        $group['items'][] = $item;
        $release['groups'][$index] = $group;

        return $release;
    }

    /**
     * Split a bullet into its bold lead-in and the rest.
     *
     * Entries are written as `- **What changed.** Why it matters`, and
     * that lead-in is the only part most people read, so it becomes a
     * heading on screen.
     *
     * The sentence-ending punctuation *inside* the bold is what makes it a
     * lead-in. Some bullets instead open with a bold fragment that the
     * sentence continues through — `- **Clients sign in with their email
     * address**, not their username` — and promoting that to a heading
     * produces "Clients sign in with their email address. , not their
     * username". Those keep their sentence whole and get no title.
     *
     * @return array{title: string, body: string}
     */
    private function item(string $bullet): array
    {
        if (preg_match('/^\*\*(?<title>.+?)\*\*(?<body>.*)$/us', $bullet, $matches) === 1) {
            $title = $this->inline($matches['title']);

            if (preg_match('/[.!?]$/u', $title) === 1) {
                return [
                    // The full stop belongs to the sentence, not to the
                    // heading it becomes on screen.
                    'title' => rtrim($title, '.'),
                    'body' => $this->inline($matches['body']),
                ];
            }
        }

        return ['title' => '', 'body' => $this->inline($bullet)];
    }

    /**
     * The bullets in one list block, each with its continuation lines
     * joined back on. Markdown wraps at 100 columns here; a reader wants
     * the sentence.
     *
     * @return list<string>
     */
    private function bullets(string $block): array
    {
        $bullets = [];

        foreach (explode("\n", $block) as $line) {
            if (preg_match('/^\s*[-*]\s+(?<text>.*)$/u', $line, $matches) === 1) {
                $bullets[] = $matches['text'];

                continue;
            }

            if ($bullets !== []) {
                $bullets[array_key_last($bullets)] .= ' '.trim($line);
            }
        }

        return array_values(array_filter(array_map(trim(...), $bullets), fn (string $b): bool => $b !== ''));
    }

    /**
     * Markdown down to text, for everything that is not the bold lead-in:
     * emphasis and code fences go, links keep their text.
     *
     * Kept deliberately small. This renders our own changelog, not
     * arbitrary Markdown, and the failure mode of a light touch is a
     * stray asterisk rather than a mangled sentence.
     */
    private function inline(string $text): string
    {
        $text = (string) preg_replace('/\[(?<text>[^]]+)]\([^)]+\)/u', '$1', $text);
        $text = (string) preg_replace('/\*\*(?<text>.+?)\*\*/us', '$1', $text);
        $text = (string) preg_replace('/`(?<text>[^`]+)`/u', '$1', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @return list<string>
     */
    private function paragraphs(string $markdown): array
    {
        $blocks = preg_split('/\n\s*\n/u', str_replace("\r\n", "\n", $markdown)) ?: [];

        return array_values(array_filter(array_map(trim(...), $blocks), fn (string $b): bool => $b !== ''));
    }

    /**
     * Null when there is no changelog to read — which is not a failure
     * worth reporting: a development checkout may be mid-rewrite, and the
     * page it feeds says thank you either way.
     */
    private function read(): ?string
    {
        $path = base_path('CHANGELOG.md');

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }
}
