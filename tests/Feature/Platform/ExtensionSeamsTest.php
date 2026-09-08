<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Announcements\Events\ResolvingDashboardCallout;
use App\Modules\Platform\Navigation\Events\ResolvingNavigationLinks;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| Two seams a package fills, and core does not
|--------------------------------------------------------------------------
|
| Both are dispatched unconditionally, and with nothing listening the
| documented default holds — no links, no callout. That is what makes them
| safe to add to a community installation that will never have a listener.
*/

test('with nothing listening the dashboard is exactly what it was', function () {
    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('callout', null),
    );

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('extra_nav_links', []),
    );
});

test('a listener can put a band on the dashboard', function () {
    Event::listen(ResolvingDashboardCallout::class, function (ResolvingDashboardCallout $event): void {
        $event->show('Heads up', 'Something worth reading.', 'Do the thing', 'https://example.test/', 'warning');
    });

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('callout.title', 'Heads up')
            ->where('callout.action_url', 'https://example.test/')
            ->where('callout.tone', 'warning'),
    );
});

test('a listener can add a sidebar link', function () {
    Event::listen(ResolvingNavigationLinks::class, function (ResolvingNavigationLinks $event): void {
        $event->add('Somewhere else', 'https://example.test/', external: true);
    });

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('extra_nav_links.0.title', 'Somewhere else')
            ->where('extra_nav_links.0.external', true),
    );
});

// The sidebar is the administration area. A client's portal shows their
// own files and nothing about the installation, so these must not reach
// them however careless a listener is.
test('a client gets no contributed links, even from a listener that adds unconditionally', function () {
    Event::listen(ResolvingNavigationLinks::class, function (ResolvingNavigationLinks $event): void {
        $event->add('Staff only really', 'https://example.test/');
    });

    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('extra_nav_links', []),
    );
});

// One band. A dashboard that can accumulate banners accumulates them, and
// the second is what teaches people to skip the first.
test('the first listener to set a callout keeps it', function () {
    Event::listen(ResolvingDashboardCallout::class, function (ResolvingDashboardCallout $event): void {
        $event->show('First', 'Set first.');
    });
    Event::listen(ResolvingDashboardCallout::class, function (ResolvingDashboardCallout $event): void {
        $event->show('Second', 'Should not win.');
    });

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('callout.title', 'First'),
    );
});

test('an unknown tone falls back rather than rendering unstyled', function () {
    Event::listen(ResolvingDashboardCallout::class, function (ResolvingDashboardCallout $event): void {
        $event->show('T', 'B', tone: 'chartreuse');
    });

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('callout.tone', 'info'),
    );
});
