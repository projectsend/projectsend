<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Scheduling\ScheduledTaskRun;
use App\Modules\Platform\Scheduling\TaskRunStatus;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

/**
 * A new installation shows its administrator around, once — and the list
 * it shows never points at something this edition or this person cannot
 * do.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();

    // Settings survive RefreshDatabase's rollback in the cache, so state
    // both markers rather than assuming their defaults.
    app(Settings::class)->set(Setting::GettingStartedPending, false);
    app(Settings::class)->set(Setting::UpdateWelcomeTo, '');
});

function justInstalled(): void
{
    app(Settings::class)->set(Setting::GettingStartedPending, true);
}

/** @return list<string> */
function quickStartKeys(User $user): array
{
    $keys = [];

    test()->actingAs($user)->get('/system/getting-started')->assertInertia(
        function (AssertableInertia $page) use (&$keys) {
            $keys = array_column($page->toArray()['props']['items'], 'key');
        },
    );

    return $keys;
}

test('the main administrator lands on it after installing', function () {
    justInstalled();

    $this->actingAs($this->admin)->get('/dashboard')->assertRedirect('/system/getting-started');
});

test('it happens exactly once', function () {
    justInstalled();

    $this->actingAs($this->admin)->get('/dashboard')->assertRedirect('/system/getting-started');
    $this->actingAs($this->admin)->get('/system/getting-started')->assertOk();

    $this->actingAs($this->admin)->get('/dashboard')->assertOk();
});

// Closing it on the way past should not be unrecoverable.
test('it stays readable afterwards, with the welcome wording dropped', function () {
    justInstalled();

    $this->actingAs($this->admin)->get('/system/getting-started')->assertOk();

    $this->actingAs($this->admin)->get('/system/getting-started')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/getting-started')
            ->where('justInstalled', false)
            ->has('items'),
    );
});

test('other staff are not interrupted, but may read it', function () {
    justInstalled();

    $second = User::factory()->create();

    $this->actingAs($second)->get('/dashboard')->assertOk();
    $this->actingAs($second)->get('/system/getting-started')->assertOk();

    // …and reading it did not consume the greeting.
    $this->actingAs($this->admin)->get('/dashboard')->assertRedirect('/system/getting-started');
});

test('clients cannot reach it', function () {
    $client = User::factory()->client()->create();

    // EnsureStaff redirects a client away from a staff GET rather than
    // answering 403 — see its docblock.
    $this->actingAs($client)->get('/system/getting-started')->assertRedirect();
});

test('an installation that merely updated is never welcomed to itself', function () {
    app(Settings::class)->set(Setting::UpdateWelcomeFrom, '2.0.0');
    app(Settings::class)->set(Setting::UpdateWelcomeTo, '2.1.0');

    $this->actingAs($this->admin)->get('/dashboard')->assertRedirect('/system/whats-new');
});

// Both markers at once cannot happen in practice — an update marker is
// only raised for an installation that already existed — but if it did,
// release notes for a version they never ran are the wrong greeting.
test('installing wins over updating', function () {
    justInstalled();
    app(Settings::class)->set(Setting::UpdateWelcomeTo, '2.1.0');

    $this->actingAs($this->admin)->get('/dashboard')->assertRedirect('/system/getting-started');
});

test('completing setup raises the greeting', function () {
    User::query()->delete();

    $this->post('/setup', [
        'site_name' => 'Acme Files',
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'a-long-enough-password',
        'password_confirmation' => 'a-long-enough-password',
    ])->assertRedirect('/setup/success');

    expect(app(Settings::class)->get(Setting::GettingStartedPending))->toBeTrue();
});

// Unattended provisioning skips the setup screen entirely, and is how
// every container that came up from environment variables was installed.
test('provisioning from the command line raises it too', function () {
    User::query()->delete();

    $this->artisan('projectsend:admin', [
        '--name' => 'Ada',
        '--email' => 'ada@example.com',
        '--password' => 'a-long-enough-password',
    ])->assertSuccessful();

    expect(app(Settings::class)->get(Setting::GettingStartedPending))->toBeTrue();
});

test('--if-none on an installed site raises nothing', function () {
    $this->artisan('projectsend:admin', ['--if-none' => true])->assertSuccessful();

    expect(app(Settings::class)->get(Setting::GettingStartedPending))->toBeFalse();
});

// The list is the point of the page, and a link to a screen that answers
// 403 is worse than no link at all.
test('it only lists what this person may actually do', function () {
    $uploader = User::factory()->role(SystemRole::Uploader)->create();

    $keys = quickStartKeys($uploader);

    expect($keys)->toContain('upload')
        ->and($keys)->not->toContain('team')
        ->and($keys)->not->toContain('email')
        ->and($keys)->not->toContain('theme');
});

// The example the brief named: a managed installation has no staff
// accounts of its own to hand out, no mail server to point anywhere and
// no scheduler to check.
test('a managed installation is not sent to screens it does not have', function () {
    config()->set('projectsend.edition', Edition::Cloud);

    $keys = quickStartKeys($this->admin);

    expect($keys)->toContain('client', 'upload', 'theme', 'email-theme')
        ->and($keys)->not->toContain('team')
        ->and($keys)->not->toContain('email')
        ->and($keys)->not->toContain('scheduler');
});

test('a self-hosted installation gets the full list', function () {
    config()->set('projectsend.edition', Edition::Community);

    expect(quickStartKeys($this->admin))->toContain('client', 'upload', 'group', 'theme', 'email-theme', 'email', 'team', 'scheduler');
});

// Two of them can be answered from the database rather than guessed, and
// a tick that means "we assume so" would be worse than no tick.
test('the client and upload steps tick themselves', function () {
    justInstalled();

    $this->actingAs($this->admin)->get('/system/getting-started')->assertInertia(
        fn (AssertableInertia $page) => $page->where('items.0.key', 'client')->where('items.0.done', false),
    );

    User::factory()->client()->create();

    $this->actingAs($this->admin)->get('/system/getting-started')->assertInertia(
        fn (AssertableInertia $page) => $page->where('items.0.done', true),
    );
});

// Not every step is equally urgent, and a list that pretends otherwise is
// a list nobody reads twice. The mail server is the one whose absence is
// silent — nothing announces that password resets are going nowhere.
test('it marks which steps the installation does not work without', function () {
    $items = [];

    $this->actingAs($this->admin)->get('/system/getting-started')->assertInertia(function (AssertableInertia $page) use (&$items) {
        $items = collect($page->toArray()['props']['items'])->keyBy('key');
    });

    expect($items['email']['essential'])->toBeTrue()
        ->and($items['client']['essential'])->toBeTrue()
        ->and($items['scheduler']['essential'])->toBeTrue()
        ->and($items['email-theme']['essential'])->toBeFalse()
        ->and($items['team']['essential'])->toBeFalse();
});

// The email-theme link has to land on the email tab, not on the page with
// a sentence asking the reader to find it.
test('the email theme step deep-links to its own tab', function () {
    $items = [];

    $this->actingAs($this->admin)->get('/system/getting-started')->assertInertia(function (AssertableInertia $page) use (&$items) {
        $items = $page->toArray()['props']['items'];
    });

    $emailTheme = collect($items)->firstWhere('key', 'email-theme');

    expect($emailTheme['href'])->toContain('tab=email');
});

// The one essential step that could never be completed: it was hardcoded
// unticked, while the screen it links to was already answering the
// question from these very rows.
test('the scheduler step ticks itself once the scheduler has run here', function () {
    $items = [];

    $this->actingAs($this->admin)->get('/system/getting-started')->assertInertia(function (AssertableInertia $page) use (&$items) {
        $items = collect($page->toArray()['props']['items'])->keyBy('key');
    });

    expect($items['scheduler']['done'])->toBeFalse();

    ScheduledTaskRun::query()->create([
        'command' => 'projectsend:purge-expired-files',
        'status' => TaskRunStatus::Success,
        'message' => null,
        'duration_ms' => 12,
        'ran_at' => now(),
    ]);

    $this->actingAs($this->admin)->get('/system/getting-started')->assertInertia(function (AssertableInertia $page) use (&$items) {
        $items = collect($page->toArray()['props']['items'])->keyBy('key');
    });

    expect($items['scheduler']['done'])->toBeTrue();
});

// A run that failed still proves cron reaches this installation, which is
// what the step asks. Why it failed is the Scheduler screen's job.
test('a failed run still counts as the scheduler running', function () {
    ScheduledTaskRun::query()->create([
        'command' => 'projectsend:fetch-news',
        'status' => TaskRunStatus::Failed,
        'message' => 'Scheduled command failed with exit code [1].',
        'duration_ms' => null,
        'ran_at' => now(),
    ]);

    $items = [];

    $this->actingAs($this->admin)->get('/system/getting-started')->assertInertia(function (AssertableInertia $page) use (&$items) {
        $items = collect($page->toArray()['props']['items'])->keyBy('key');
    });

    expect($items['scheduler']['done'])->toBeTrue();
});
