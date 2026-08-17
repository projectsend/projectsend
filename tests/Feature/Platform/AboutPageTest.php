<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Attribution\Attribution;
use App\Modules\Platform\Attribution\Events\ResolvingAttribution;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // Without a staff user every request redirects to /setup — see
    // EnsureSetupIsComplete.
    $this->admin = User::factory()->create();
});

test('staff can read the about page', function () {
    $this->actingAs($this->admin)->get('/system/about')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/about')
            ->where('license', 'GNU General Public License v2')
            ->where('version', config('projectsend.version')),
    );
});

test('clients cannot reach the about page', function () {
    $client = User::factory()->client()->create();

    // EnsureStaff redirects a client away from a staff GET rather than
    // answering 403 — see its own docblock; only mutations are hard 403.
    $this->actingAs($client)->get('/system/about')->assertRedirect();
});

test('community staff see the environment block', function () {
    config()->set('projectsend.edition', Edition::Community);

    $this->actingAs($this->admin)->get('/system/about')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('environment.php', PHP_VERSION)
            ->where('environment.edition', 'community'),
    );
});

test('cloud hides the environment block, same gate as the dashboard system widget', function () {
    // On a managed installation updates are handled outside the
    // application, so SystemUpdates is absent and the PHP build is not
    // the operator's concern.
    config()->set('projectsend.edition', Edition::Cloud);

    $this->actingAs($this->admin)->get('/system/about')->assertInertia(
        fn (AssertableInertia $page) => $page->where('environment', null),
    );
});

test('the source link points at the public repository, never the private staging one', function () {
    // The source link is the one thing on that page a licence notice
    // actually depends on, and it fails silently: a wrong URL looks fine
    // until somebody clicks it. v1 lives at projectsend/legacy, so the two
    // are easy to transpose.
    $this->actingAs($this->admin)->get('/system/about')->assertInertia(
        fn (AssertableInertia $page) => $page->where('links.source', 'https://github.com/projectsend/projectsend'),
    );
});

test('the attribution prop is shared with every page and defaults to true', function () {
    $this->actingAs($this->admin)->get('/system/about')->assertInertia(
        fn (AssertableInertia $page) => $page->where('attribution', true),
    );
});

test('a listener that hides attribution reaches the shared prop and the generator meta', function () {
    Event::listen(ResolvingAttribution::class, function (ResolvingAttribution $event): void {
        $event->visible = false;
    });

    expect(app(Attribution::class)->visible())->toBeFalse();

    $response = $this->actingAs($this->admin)->get('/system/about');

    $response->assertInertia(fn (AssertableInertia $page) => $page->where('attribution', false));
    $response->assertDontSee('name="generator"', false);
});

test('the generator meta names ProjectSend without its version', function () {
    $response = $this->actingAs($this->admin)->get('/system/about');

    $response->assertSee('<meta name="generator" content="ProjectSend">', false);

    // The version is deliberately absent: this tag is served to anyone
    // who asks, and it would tell a scanner which advisories apply.
    $response->assertDontSee('content="ProjectSend '.config('projectsend.version').'"', false);
});

test('about names the version this installation was last updated to, and when', function () {
    config()->set('projectsend.edition', Edition::Community);
    $settings = app(Settings::class);
    $settings->set(Setting::AppliedVersion, '2.1.0');
    $settings->set(Setting::AppliedVersionAt, '2026-08-17T10:00:00+00:00');

    $this->actingAs($this->admin)->get('/system/about')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('updated.version', '2.1.0')
            ->where('updated.at', '2026-08-17T10:00:00+00:00'),
    );
});

test('an installation that has never been updated says nothing rather than guessing', function () {
    config()->set('projectsend.edition', Edition::Community);
    $settings = app(Settings::class);
    $settings->set(Setting::AppliedVersion, '');
    $settings->set(Setting::AppliedVersionAt, '');

    $this->actingAs($this->admin)->get('/system/about')->assertInertia(
        fn (AssertableInertia $page) => $page->where('updated', null),
    );
});

test('the last-update line is hidden from anyone the environment block is hidden from', function () {
    config()->set('projectsend.edition', Edition::Cloud);
    $settings = app(Settings::class);
    $settings->set(Setting::AppliedVersion, '2.1.0');
    $settings->set(Setting::AppliedVersionAt, '2026-08-17T10:00:00+00:00');

    $this->actingAs($this->admin)->get('/system/about')->assertInertia(
        fn (AssertableInertia $page) => $page->where('environment', null)->where('updated', null),
    );
});
