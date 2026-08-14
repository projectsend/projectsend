<?php

declare(strict_types=1);

use App\Modules\Platform\Attribution\Attribution;
use App\Modules\Platform\Attribution\Events\ResolvingAttribution;
use Illuminate\Support\Facades\Event;

test('attribution is visible when nothing listens', function () {
    // The community case: no package is installed, so the hook is
    // dispatched into an empty room and the default stands. This is the
    // whole mechanism that keeps attribution unhideable there — there is
    // no setting to reach, only code that is absent.
    expect(app(Attribution::class)->visible())->toBeTrue();
});

test('a listener can hide attribution', function () {
    Event::listen(ResolvingAttribution::class, function (ResolvingAttribution $event): void {
        $event->visible = false;
    });

    expect(app(Attribution::class)->visible())->toBeFalse();
});

test('one listener declining to hide does not overrule another that hides', function () {
    Event::listen(ResolvingAttribution::class, function (ResolvingAttribution $event): void {
        $event->visible = false;
    });

    // Registered second and does nothing — a listener with no opinion
    // must not undo the first one's answer, which is why the payload is
    // documented as only ever moving toward false.
    Event::listen(ResolvingAttribution::class, function (ResolvingAttribution $event): void {
        // no-op
    });

    expect(app(Attribution::class)->visible())->toBeFalse();
});

test('the answer is resolved per call, never cached', function () {
    expect(app(Attribution::class)->visible())->toBeTrue();

    Event::listen(ResolvingAttribution::class, function (ResolvingAttribution $event): void {
        $event->visible = false;
    });

    // A queue worker lives for hours across many messages; an answer
    // memoised on the service or the container would keep sending mail
    // with a footer the operator turned off that morning.
    expect(app(Attribution::class)->visible())->toBeFalse();
});
