<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Installation\Installation;
use App\Modules\Platform\Installation\InstallationKind;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

/**
 * The application tells administrators how to upgrade, and the answer is
 * different for a container and for files unpacked onto a server. Getting it
 * wrong is worse than saying nothing: before this existed, both surfaces
 * printed `docker compose pull` to everybody, including the people INSTALL.md
 * was written for, who have no docker to run it with.
 */
class FakeInstallation extends Installation
{
    public function __construct(private readonly bool $container) {}

    protected function inContainer(): bool
    {
        return $this->container;
    }
}

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/** Puts a newer release in the cache so the update surfaces have something to show. */
function anUpdateIsAvailable(): void
{
    $settings = app(Settings::class);
    $current = (string) config('projectsend.version');

    $settings->set(Setting::CheckForUpdates, true);
    $settings->set(Setting::LatestKnownVersion, $current.'1');
    $settings->set(Setting::LatestReleaseTitle, 'A newer ProjectSend');
    $settings->set(Setting::LatestReleaseNotes, 'Notes.');
    $settings->set(Setting::LatestReleaseUrl, 'https://example.test/release');
    $settings->set(Setting::LatestReleasePublishedAt, '2026-08-09T00:00:00Z');
}

test('a container reports itself as one', function () {
    expect((new FakeInstallation(container: true))->kind())->toBe(InstallationKind::Container);
});

test('anything else is treated as a manual install', function () {
    // The safer wrong answer: manual instructions are steps a person reads
    // and checks, the container command is one they would paste.
    expect((new FakeInstallation(container: false))->kind())->toBe(InstallationKind::Manual);
});

test('the dashboard tells the frontend which kind of install this is', function (bool $container, string $expected) {
    app()->instance(Installation::class, new FakeInstallation($container));

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('system.install_kind', $expected));
})->with([
    'container' => [true, 'container'],
    'manual' => [false, 'manual'],
]);

test('the update notice carries the install kind too', function (bool $container, string $expected) {
    app()->instance(Installation::class, new FakeInstallation($container));
    anUpdateIsAvailable();

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('update_notice.install_kind', $expected));
})->with([
    'container' => [true, 'container'],
    'manual' => [false, 'manual'],
]);

test('no update means no notice, whatever the install kind', function () {
    app()->instance(Installation::class, new FakeInstallation(container: false));

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('update_notice', null));
});
