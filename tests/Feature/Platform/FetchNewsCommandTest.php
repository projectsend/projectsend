<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\News\NewsItems;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

/**
 * @param  list<array<string, string>>  $items
 */
function fakeNewsFeed(array $items): void
{
    Http::fake([
        'projectsend.org/serve/news' => Http::response($items, 200),
    ]);
}

test('it fetches, sanitizes, and caches news items', function () {
    fakeNewsFeed([
        [
            'title' => 'ProjectSend r2029 Released',
            'date' => '28-03-2026',
            'content' => "Some notes.\r\n\r\n<script>alert(1)</script> <a href=\"https://example.test\">changelog</a>",
            'link' => 'https://example.test/r2029',
        ],
    ]);

    $this->artisan('projectsend:fetch-news')->assertSuccessful();

    $items = app(NewsItems::class)->current();

    expect($items)->toHaveCount(1)
        ->and($items[0]['title'])->toBe('ProjectSend r2029 Released')
        ->and($items[0]['date'])->toBe('2026-03-28')
        ->and($items[0]['link'])->toBe('https://example.test/r2029')
        ->and($items[0]['content'])->not->toContain('<script>')
        ->and($items[0]['content'])->toContain('<a href="https://example.test">changelog</a>')
        ->and($items[0]['content'])->toContain('<br')
        ->and(app(Settings::class)->get(Setting::NewsLastFetchedAt))->not->toBe('');
});

test('an HTML-encoded title is decoded to plain text, since it renders as plain JSX text, not HTML', function () {
    fakeNewsFeed([
        [
            'title' => 'Folders! ProjectSend&#8217;s new version is available now',
            'date' => '01-01-2026',
            'content' => 'Notes.',
            'link' => 'https://example.test/1',
        ],
    ]);

    $this->artisan('projectsend:fetch-news')->assertSuccessful();

    $items = app(NewsItems::class)->current();

    expect($items[0]['title'])->toBe('Folders! ProjectSend’s new version is available now');
});

test('items are sorted newest first and capped to 5', function () {
    fakeNewsFeed([
        ['title' => 'Oldest (dropped)', 'date' => '01-01-2026', 'content' => 'a', 'link' => 'https://example.test/1'],
        ['title' => 'Newest', 'date' => '10-06-2026', 'content' => 'b', 'link' => 'https://example.test/2'],
        ['title' => 'Third', 'date' => '15-03-2026', 'content' => 'c', 'link' => 'https://example.test/3'],
        ['title' => 'Fifth', 'date' => '01-02-2026', 'content' => 'd', 'link' => 'https://example.test/4'],
        ['title' => 'Fourth', 'date' => '01-03-2026', 'content' => 'e', 'link' => 'https://example.test/5'],
        ['title' => 'Second', 'date' => '01-04-2026', 'content' => 'f', 'link' => 'https://example.test/6'],
    ]);

    $this->artisan('projectsend:fetch-news')->assertSuccessful();

    $items = app(NewsItems::class)->current();

    expect($items)->toHaveCount(5)
        ->and($items[0]['title'])->toBe('Newest')
        ->and(collect($items)->pluck('title'))->not->toContain('Oldest (dropped)');
});

test('an entry missing required fields is skipped without failing the whole fetch', function () {
    fakeNewsFeed([
        ['title' => 'Good entry', 'date' => '01-01-2026', 'content' => 'ok', 'link' => 'https://example.test/1'],
        ['title' => 'Missing date', 'content' => 'ok', 'link' => 'https://example.test/2'],
        ['title' => 'Bad date format', 'date' => 'not-a-date', 'content' => 'ok', 'link' => 'https://example.test/3'],
    ]);

    $this->artisan('projectsend:fetch-news')->assertSuccessful();

    $items = app(NewsItems::class)->current();

    expect($items)->toHaveCount(1)
        ->and($items[0]['title'])->toBe('Good entry');
});

test('an unreachable feed fails without throwing, and caches nothing', function () {
    Http::fake(['projectsend.org/*' => Http::response(null, 500)]);

    // A non-zero exit code, not a thrown exception — see the matching
    // comment in CheckForUpdatesCommandTest.
    $this->artisan('projectsend:fetch-news')->assertFailed();

    expect(app(NewsItems::class)->current())->toBe([]);
});

test('a non-array feed response is handled without error', function () {
    Http::fake(['projectsend.org/serve/news' => Http::response('not an array', 200)]);

    $this->artisan('projectsend:fetch-news')->assertSuccessful();

    expect(app(NewsItems::class)->current())->toBe([]);
});

test('the dashboard news prop appears only for staff who hold view_news, in both editions', function () {
    fakeNewsFeed([
        ['title' => 'A release', 'date' => '01-01-2026', 'content' => 'Notes.', 'link' => 'https://example.test/1'],
    ]);
    $this->artisan('projectsend:fetch-news')->assertSuccessful();

    $admin = User::factory()->create();

    foreach ([Edition::Community, Edition::Cloud] as $edition) {
        config()->set('projectsend.edition', $edition);

        $this->actingAs($admin)->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page->where('news.0.title', 'A release'),
        );
    }
});

test('a staff member without view_news does not receive the news prop', function () {
    fakeNewsFeed([
        ['title' => 'A release', 'date' => '01-01-2026', 'content' => 'Notes.', 'link' => 'https://example.test/1'],
    ]);
    $this->artisan('projectsend:fetch-news')->assertSuccessful();

    $role = Role::query()->create(['name' => 'No News Permission', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert(['role_id' => $role->id, 'permission' => 'edit_files']);
    $staff = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staff)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('news', null),
    );
});
