<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

/**
 * The "check now" button in the general settings. The check itself is
 * covered by CheckForUpdatesCommandTest; what is tested here is who may
 * press it, and that pressing it repeatedly cannot spend the
 * installation's whole allowance with the release feed.
 *
 * @param  array<string, mixed>  $extra
 */
function fakeReleaseFeed(string $tagName, array $extra = []): void
{
    Http::fake([
        'api.github.com/repos/projectsend/projectsend/releases/latest' => Http::response([
            'tag_name' => $tagName,
            'name' => $extra['name'] ?? "Release {$tagName}",
            'body' => $extra['body'] ?? 'Some release notes.',
            'html_url' => $extra['html_url'] ?? "https://github.com/projectsend/projectsend/releases/tag/{$tagName}",
            'published_at' => $extra['published_at'] ?? '2026-08-02T00:00:00Z',
        ], 200),
    ]);
}

beforeEach(function () {
    config()->set('projectsend.version', '2.0.0');
    config()->set('projectsend.edition', Edition::Community);

    // Both are cached beyond the per-test rollback, so every test states
    // what it needs rather than inheriting whatever ran before it.
    $settings = app(Settings::class);
    $settings->set(Setting::CheckForUpdates, true);
    $settings->set(Setting::LatestVersionCheckedAt, '');
    $settings->set(Setting::LatestKnownVersion, '');
});

test('an administrator can ask the feed on demand and is told what it said', function () {
    $this->actingAs(User::factory()->create());
    fakeReleaseFeed('v2.5.0');

    $this->post('/system/settings/check-for-updates')
        ->assertRedirect()
        ->assertSessionHas('update_check_result', fn (array $result): bool => $result['ok'] === true
            && str_contains($result['message'], '2.5.0'));

    expect(app(Settings::class)->get(Setting::LatestKnownVersion))->toBe('2.5.0');
});

test('the answer reaches the page that asked for it', function () {
    $this->actingAs(User::factory()->create());
    fakeReleaseFeed('v2.5.0');

    $this->post('/system/settings/check-for-updates');

    $this->get('/system/settings/general')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/general')
            ->where('check_result.ok', true)
            ->has('last_checked_at'),
    );
});

test('a second press within the cooldown re-reads the answer instead of the feed', function () {
    $this->actingAs(User::factory()->create());
    fakeReleaseFeed('v2.5.0');

    $this->post('/system/settings/check-for-updates');
    $this->post('/system/settings/check-for-updates')->assertRedirect();

    Http::assertSentCount(1);
});

test('the daily check being switched off does not refuse a question somebody asked out loud', function () {
    app(Settings::class)->set(Setting::CheckForUpdates, false);
    $this->actingAs(User::factory()->create());
    fakeReleaseFeed('v2.5.0');

    $this->post('/system/settings/check-for-updates');

    Http::assertSentCount(1);
    expect(app(Settings::class)->get(Setting::LatestKnownVersion))->toBe('2.5.0');
});

test('an unreachable feed says so and leaves every cached answer alone', function () {
    app(Settings::class)->set(Setting::LatestKnownVersion, '2.4.0');
    $this->actingAs(User::factory()->create());
    Http::fake(['api.github.com/*' => Http::response(null, 500)]);

    $this->post('/system/settings/check-for-updates')
        ->assertSessionHas('update_check_result', fn (array $result): bool => $result['ok'] === false);

    expect(app(Settings::class)->get(Setting::LatestKnownVersion))->toBe('2.4.0');
});

test('a staff member without manage_updates cannot press it', function () {
    $role = Role::query()->create(['name' => 'Settings Only', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert(['role_id' => $role->id, 'permission' => 'edit_settings']);
    $this->actingAs(User::factory()->create(['role_id' => $role->id]));
    fakeReleaseFeed('v2.5.0');

    $this->post('/system/settings/check-for-updates')->assertForbidden();

    Http::assertNothingSent();
});

test('the cloud edition has nothing to check', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    $this->actingAs(User::factory()->create());
    fakeReleaseFeed('v2.5.0');

    $this->post('/system/settings/check-for-updates')->assertForbidden();

    Http::assertNothingSent();
});
