<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Installation\Installation;
use App\Modules\Platform\Installation\InstallationKind;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * There is no way to be in a container and not in one within a single test
 * run, so the detection is answered for — same seam and same reasoning as
 * InstallationKindTest's own fake.
 */
class NoticeInstallation extends Installation
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

function applied(string $version): void
{
    app(Settings::class)->set(Setting::AppliedVersion, $version);
    app(Settings::class)->set(Setting::AppliedVersionAt, now()->toIso8601String());
}

/**
 * The shared prop as the page would receive it. Read out of the root
 * view's data rather than through assertInertia's callback, because most
 * of these assert the notice's *absence* — and a callback that never runs
 * proves nothing.
 *
 * @return array<string, string>|null
 */
function noticeFor(User $user): ?array
{
    $page = test()->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->viewData('page');

    return $page['props']['code_notice'] ?? null;
}

beforeEach(function () {
    $this->admin = User::factory()->create();
    config()->set('projectsend.version', '2.1.0');
});

test('nothing is said when the applied version is the running one', function () {
    applied('2.1.0');

    expect(noticeFor($this->admin))->toBeNull();
});

test('nothing is said on an installation that has never applied an update', function () {
    expect(app(Settings::class)->get(Setting::AppliedVersion))->toBe('')
        ->and(noticeFor($this->admin))->toBeNull();
});

// The failure this exists for: files replaced, PHP never reloaded, so the
// database is ahead of the code every visitor is being served.
test('an applied version ahead of the running code reports stale code', function () {
    applied('2.2.0');

    $notice = noticeFor($this->admin);

    expect($notice)->not->toBeNull()
        ->and($notice['reason'])->toBe('stale_code')
        ->and($notice['applied'])->toBe('2.2.0')
        ->and($notice['running'])->toBe('2.1.0');
});

// The other half of the same mistake: new files unpacked, the update never
// run, so the schema is behind the code.
test('an applied version behind the running code reports a pending update', function () {
    applied('2.0.0');

    expect(noticeFor($this->admin)['reason'])->toBe('pending_update');
});

test('it is gated on view_system_info', function () {
    applied('2.2.0');

    $uploader = User::factory()->create([
        'role_id' => Role::query()->where('name', SystemRole::AccountManager->value)->value('id'),
    ]);

    expect(noticeFor($uploader))->toBeNull()
        ->and(noticeFor($this->admin))->not->toBeNull();
})->skip(fn (): bool => Role::query()->where('name', SystemRole::AccountManager->value)->doesntExist(), 'needs the seeded roles');

// Unlike update_notice, which is edition-gated: what code a server is
// executing is a fact about the machine, not a feature of an edition.
test('it is not gated on the edition', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    applied('2.2.0');

    expect(noticeFor($this->admin))->not->toBeNull();
});

test('it names the command this kind of installation can actually run', function (bool $container, bool $source, string $expected) {
    app()->instance(Installation::class, new NoticeInstallation($container, $source));
    applied('2.2.0');

    expect(noticeFor($this->admin)['install_kind'])->toBe($expected);
})->with([
    'a container from the published image' => [true, false, InstallationKind::Container->value],
    'a container built from a checkout' => [true, true, InstallationKind::ContainerSource->value],
    'a server somebody administers' => [false, false, InstallationKind::Manual->value],
]);

// The rollback story, asserted end to end: whatever the marker said, the
// command rewrites it to whatever is actually running. Through the artisan
// seam, as everything that runs this command has to be — see
// Tests\Support\RecordingUpdate. The settings write this asserts is the
// real one.
test('running the update clears the notice', function () {
    recordingUpdate();

    applied('2.2.0');
    expect(noticeFor($this->admin))->not->toBeNull();

    $this->artisan('projectsend:update')->assertSuccessful();

    expect(noticeFor($this->admin))->toBeNull();
});
