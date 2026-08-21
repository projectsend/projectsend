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
 * different for the published image, for a container somebody builds from a
 * checkout, and for files unpacked onto a server. Getting it wrong is worse
 * than saying nothing: before this existed, every surface printed
 * `docker compose pull` to everybody, including the people INSTALL.md was
 * written for, who have no docker to run it with — and including the
 * clone-and-build stacks where those commands succeed without updating
 * anything (#1661).
 */
class FakeInstallation extends Installation
{
    public function __construct(
        private readonly bool $container,
        private readonly bool $source = false,
    ) {}

    protected function inContainer(): bool
    {
        return $this->container;
    }

    protected function builtFromSource(): bool
    {
        return $this->source;
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

test('a container running the published image reports itself as one', function () {
    expect((new FakeInstallation(container: true))->kind())->toBe(InstallationKind::Container);
});

test('a container built from a checkout is told apart from the image', function () {
    // `docker compose pull` on this stack skips every ProjectSend service
    // and then reports success, so being handed that command is how an
    // installation stays on the version it is on.
    expect((new FakeInstallation(container: true, source: true))->kind())->toBe(InstallationKind::ContainerSource);
});

test('a working tree is only asked about inside a container', function () {
    expect((new FakeInstallation(container: false, source: true))->kind())->toBe(InstallationKind::Manual);
});

test('the image says so itself, whatever is on disk', function () {
    // Precedence, asserted where it matters: the test suite runs from a
    // working tree, so without the marker this same object answers
    // ContainerSource. An operator bind-mounting a checkout into the
    // published image is still updating it by pulling.
    $installation = new class extends Installation
    {
        protected function inContainer(): bool
        {
            return true;
        }
    };

    expect($installation->kind())->toBe(InstallationKind::ContainerSource);

    putenv('PROJECTSEND_IMAGE=1');

    try {
        expect($installation->kind())->toBe(InstallationKind::Container);
    } finally {
        putenv('PROJECTSEND_IMAGE');
    }
})->skip(fn () => ! file_exists(base_path('.git')), 'Asserts precedence over a working tree, and there is none here.');

test('anything else is treated as a manual install', function () {
    // The safer wrong answer: manual instructions are steps a person reads
    // and checks, the container command is one they would paste.
    expect((new FakeInstallation(container: false))->kind())->toBe(InstallationKind::Manual);
});

test('the dashboard tells the frontend which kind of install this is', function (bool $container, bool $source, string $expected) {
    app()->instance(Installation::class, new FakeInstallation($container, $source));

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('system.install_kind', $expected));
})->with([
    'the published image' => [true, false, 'container'],
    'built from a checkout' => [true, true, 'container-source'],
    'manual' => [false, false, 'manual'],
]);

test('the update notice carries the install kind too', function (bool $container, bool $source, string $expected) {
    app()->instance(Installation::class, new FakeInstallation($container, $source));
    anUpdateIsAvailable();

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('update_notice.install_kind', $expected));
})->with([
    'the published image' => [true, false, 'container'],
    'built from a checkout' => [true, true, 'container-source'],
    'manual' => [false, false, 'manual'],
]);

test('no update means no notice, whatever the install kind', function () {
    app()->instance(Installation::class, new FakeInstallation(container: false));

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('update_notice', null));
});
