<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

/**
 * An update finished; the person who administers this installation should
 * hear about it once, and nobody else should be interrupted at all.
 */
beforeEach(function () {
    // The oldest active administrator — created first, so it is the main
    // one no matter what the tests below add afterwards.
    $this->admin = User::factory()->create();

    // Settings survive RefreshDatabase's rollback in the cache, so every
    // test states the marker it wants rather than assuming a default.
    app(Settings::class)->set(Setting::UpdateWelcomeFrom, '');
    app(Settings::class)->set(Setting::UpdateWelcomeTo, '');
});

function anUpdateJustLanded(string $from = '2.0.0', ?string $to = null): void
{
    app(Settings::class)->set(Setting::UpdateWelcomeFrom, $from);
    app(Settings::class)->set(Setting::UpdateWelcomeTo, $to ?? (string) config('projectsend.version'));
}

test('the main administrator lands on the welcome page after an update', function () {
    anUpdateJustLanded();

    $this->actingAs($this->admin)->get('/dashboard')->assertRedirect('/system/whats-new');
});

test('the page names the release and what it brought', function () {
    anUpdateJustLanded();

    $this->actingAs($this->admin)->get('/system/whats-new')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/whats-new')
            ->where('version', config('projectsend.version'))
            ->where('previousVersion', '2.0.0')
            ->where('justUpdated', true)
            ->has('releases'),
    );
});

// Once. The second visit to the dashboard is somebody trying to get on
// with their day.
test('the redirect happens exactly once', function () {
    anUpdateJustLanded();

    $this->actingAs($this->admin)->get('/dashboard')->assertRedirect('/system/whats-new');
    $this->actingAs($this->admin)->get('/system/whats-new')->assertOk();

    $this->actingAs($this->admin)->get('/dashboard')->assertOk();
});

// Closing it by accident should not be unrecoverable: the address keeps
// working and keeps describing the release, it just stops interrupting.
test('the page still reads after it has been dismissed', function () {
    anUpdateJustLanded();

    $this->actingAs($this->admin)->get('/system/whats-new')->assertOk();

    $this->actingAs($this->admin)->get('/system/whats-new')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('justUpdated', false)
            ->where('version', config('projectsend.version')),
    );
});

// The update happened to the installation, not to five people. Every
// administrator being stopped and made to dismiss the same page would turn
// a pleasant moment into a support question.
test('another administrator is not interrupted', function () {
    anUpdateJustLanded();

    $second = User::factory()->create();

    $this->actingAs($second)->get('/dashboard')->assertOk();
});

// …and reading it does not consume a greeting addressed to somebody else.
test('another administrator reading the page leaves it waiting', function () {
    anUpdateJustLanded();

    $this->actingAs(User::factory()->create())->get('/system/whats-new')->assertOk();

    $this->actingAs($this->admin)->get('/dashboard')->assertRedirect('/system/whats-new');
});

// The founder who left: their account is deactivated, and the greeting
// moves to whoever administers the installation now rather than sitting
// unread forever.
test('a deactivated main administrator hands the greeting to the next one', function () {
    anUpdateJustLanded();

    $second = User::factory()->create();
    $this->admin->update(['active' => false]);

    $this->actingAs($second)->get('/dashboard')->assertRedirect('/system/whats-new');
});

test('staff who may not read system information are not interrupted', function () {
    anUpdateJustLanded();

    $uploader = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($uploader)->get('/dashboard')->assertOk();
    $this->actingAs($uploader)->get('/system/whats-new')->assertForbidden();
});

test('clients see nothing of it', function () {
    anUpdateJustLanded();

    $client = User::factory()->client()->create();

    // EnsureStaff redirects a client away from a staff GET rather than
    // answering 403 — see its docblock.
    $this->actingAs($client)->get('/system/whats-new')->assertRedirect();
});

// Nobody signed in to a managed installation performed the update, so
// "thank you for keeping it current" would be addressed to the wrong
// person. Same gate as About's environment block and the dashboard's
// System card.
test('a managed installation never shows it', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    anUpdateJustLanded();

    $this->actingAs($this->admin)->get('/dashboard')->assertOk();
    $this->actingAs($this->admin)->get('/system/whats-new')->assertNotFound();
});

test('with no update waiting the dashboard is the dashboard', function () {
    $this->actingAs($this->admin)->get('/dashboard')->assertOk();
});

// A redirect swallows a request body, and nothing that writes should ever
// be answered with a greeting.
test('it never intercepts anything but a GET', function () {
    anUpdateJustLanded();

    $this->actingAs($this->admin)
        ->from('/dashboard')
        ->put('/dashboard/widgets', [
            'columns' => 2,
            'widgets' => [['widget_key' => 'counters', 'enabled' => true, 'column_index' => 0, 'position' => 0]],
        ])
        ->assertRedirect('/dashboard');
});
