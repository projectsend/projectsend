<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Announcements\Events\ResolvingAnnouncement;
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
        fn (AssertableInertia $page) => $page->where('announcement', null),
    );

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('extra_nav_links', []),
    );
});

test('a listener can put a message in front of staff', function () {
    Event::listen(ResolvingAnnouncement::class, function (ResolvingAnnouncement $event): void {
        $event->show('Heads up', 'Something worth reading.', 'Do the thing', 'https://example.test/', 'warning');
    });

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('announcement.title', 'Heads up')
            ->where('announcement.action_url', 'https://example.test/')
            ->where('announcement.tone', 'warning'),
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
    Event::listen(ResolvingAnnouncement::class, function (ResolvingAnnouncement $event): void {
        $event->show('First', 'Set first.');
    });
    Event::listen(ResolvingAnnouncement::class, function (ResolvingAnnouncement $event): void {
        $event->show('Second', 'Should not win.');
    });

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('announcement.title', 'First'),
    );
});

test('an unknown tone falls back rather than rendering unstyled', function () {
    Event::listen(ResolvingAnnouncement::class, function (ResolvingAnnouncement $event): void {
        $event->show('T', 'B', tone: 'chartreuse');
    });

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('announcement.tone', 'info'),
    );
});


// The header icon and the dashboard band read one shared prop, so a
// message reaches somebody who never opens the dashboard. Two props would
// have drifted the first time anybody edited one.
test('the same message is available away from the dashboard', function () {
    Event::listen(ResolvingAnnouncement::class, function (ResolvingAnnouncement $event): void {
        $event->show('Everywhere', 'Not only on the dashboard.');
    });

    $this->actingAs($this->admin)->get('/system/settings/general')->assertInertia(
        fn (AssertableInertia $page) => $page->where('announcement.title', 'Everywhere'),
    );
});

// A client's header carries the bell too. Nothing addressed to staff may
// appear there, however careless the listener.
test('a client is never shown one, even from a listener that sets it unconditionally', function () {
    Event::listen(ResolvingAnnouncement::class, function (ResolvingAnnouncement $event): void {
        if (! $event->isStaff) {
            return;
        }

        $event->show('Staff only', 'Not for clients.');
    });

    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/my-files')->assertInertia(
        fn (AssertableInertia $page) => $page->where('announcement', null),
    );
});
