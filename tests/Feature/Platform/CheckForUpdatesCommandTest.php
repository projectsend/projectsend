<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Updates\LatestReleaseInfo;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

/**
 * @param  array<string, mixed>  $extra
 */
function fakeLatestRelease(string $tagName, array $extra = []): void
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
});

test('it does nothing on the cloud edition, without even calling the feed', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    fakeLatestRelease('v2.5.0');

    $this->artisan('projectsend:check-for-updates')->assertSuccessful();

    Http::assertNothingSent();
    expect(app(Settings::class)->get(Setting::LatestKnownVersion))->toBe('');
});

test('it does nothing when the CheckForUpdates setting is disabled', function () {
    config()->set('projectsend.edition', Edition::Community);
    app(Settings::class)->set(Setting::CheckForUpdates, false);
    fakeLatestRelease('v2.5.0');

    $this->artisan('projectsend:check-for-updates')->assertSuccessful();

    Http::assertNothingSent();
});

test('an up-to-date release is cached but does not notify anyone', function () {
    config()->set('projectsend.edition', Edition::Community);
    $admin = User::factory()->create();
    fakeLatestRelease('v2.0.0');

    $this->artisan('projectsend:check-for-updates')->assertSuccessful();

    expect(app(Settings::class)->get(Setting::LatestKnownVersion))->toBe('2.0.0')
        ->and(InAppNotification::query()->where('user_id', $admin->id)->count())->toBe(0);
});

test('a newer release notifies staff who hold manage_updates but not staff who lack it', function () {
    config()->set('projectsend.edition', Edition::Community);
    $admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'No Update Permission', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert(['role_id' => $role->id, 'permission' => 'edit_files']);
    $otherStaff = User::factory()->create(['role_id' => $role->id]);

    fakeLatestRelease('v2.5.0', ['name' => 'v2.5.0 — Big Release', 'body' => 'Fixes and features.']);

    $this->artisan('projectsend:check-for-updates')->assertSuccessful();

    expect(app(Settings::class)->get(Setting::LatestKnownVersion))->toBe('2.5.0')
        ->and(app(Settings::class)->get(Setting::LatestReleaseTitle))->toBe('v2.5.0 — Big Release')
        ->and(app(Settings::class)->get(Setting::LatestReleaseNotes))->toBe('Fixes and features.')
        ->and(app(Settings::class)->get(Setting::LatestReleaseUrl))->toBe('https://github.com/projectsend/projectsend/releases/tag/v2.5.0')
        ->and(InAppNotification::query()->where('user_id', $admin->id)->where('type', 'update_available')->count())->toBe(1)
        ->and(InAppNotification::query()->where('user_id', $otherStaff->id)->count())->toBe(0);

    $release = app(LatestReleaseInfo::class)->current();
    expect($release)->not->toBeNull()
        ->and($release['version'])->toBe('2.5.0')
        ->and($release['title'])->toBe('v2.5.0 — Big Release')
        ->and($release['notes'])->toBe('Fixes and features.');
});

test('running it again for the same known latest version does not re-notify', function () {
    config()->set('projectsend.edition', Edition::Community);
    $admin = User::factory()->create();
    fakeLatestRelease('v2.5.0');

    $this->artisan('projectsend:check-for-updates')->assertSuccessful();
    $this->artisan('projectsend:check-for-updates')->assertSuccessful();

    expect(InAppNotification::query()->where('user_id', $admin->id)->where('type', 'update_available')->count())->toBe(1);
});

test('a non-semver release tag (v1\'s r-number scheme) is skipped without marking an update available', function () {
    config()->set('projectsend.edition', Edition::Community);
    fakeLatestRelease('r2029');

    $this->artisan('projectsend:check-for-updates')->assertSuccessful();

    expect(app(Settings::class)->get(Setting::LatestKnownVersion))->toBe('');
});

test('the update_notice shared prop appears only for staff who hold manage_updates', function () {
    config()->set('projectsend.edition', Edition::Community);
    $admin = User::factory()->create();

    $role = Role::query()->create(['name' => 'No Update Permission 2', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert(['role_id' => $role->id, 'permission' => 'edit_files']);
    $otherStaff = User::factory()->create(['role_id' => $role->id]);

    fakeLatestRelease('v2.5.0', ['name' => 'v2.5.0', 'body' => 'Notes here.']);
    $this->artisan('projectsend:check-for-updates')->assertSuccessful();

    $this->actingAs($admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('update_notice.version', '2.5.0')
            ->where('update_notice.notes', 'Notes here.'),
    );

    $this->actingAs($otherStaff)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('update_notice', null),
    );
});

test('an unreachable feed fails without throwing, and caches nothing', function () {
    config()->set('projectsend.edition', Edition::Community);
    Http::fake(['api.github.com/*' => Http::response(null, 500)]);

    // A non-zero exit code, not a thrown exception — this is what lets
    // Laravel's scheduler dispatch ScheduledTaskFailed and RecordsScheduledTaskRuns
    // pick it up, rather than this transient network problem silently
    // reporting as a success.
    $this->artisan('projectsend:check-for-updates')->assertFailed();

    expect(app(Settings::class)->get(Setting::LatestKnownVersion))->toBe('');
});
