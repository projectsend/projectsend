<?php

declare(strict_types=1);

use App\Modules\Files\Thumbnails\ImageAudience;
use App\Modules\Files\Thumbnails\ImageRendition;
use App\Modules\Files\Thumbnails\ThumbnailGenerator;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| One render, however many ask at once
|--------------------------------------------------------------------------
|
| Renditions are generated on demand and cached by existence, and nothing
| between the callers stopped two requests decoding the same image at the
| same time — the atomic rename settled which file survived, not whether
| both had done the work.
|
| The trigger is not an attack. A public listing emits one thumbnail URL
| per file, a browser opens six or more connections at once, and the first
| visit to a gallery of ordinary camera images was six simultaneous
| decodes, each holding four bytes per source pixel, on a container sized
| for one. PublicGroupsController reaches the generator with no account.
*/

function sourceImage(int $width = 400, int $height = 300): string
{
    $path = sys_get_temp_dir().'/single-flight-'.bin2hex(random_bytes(6)).'.jpg';
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 30, 90, 150));
    imagejpeg($image, $path, 70);
    imagedestroy($image);

    return $path;
}

function destinationPath(): string
{
    $path = sys_get_temp_dir().'/rendition-'.bin2hex(random_bytes(6)).'.jpg';
    @unlink($path);

    return $path;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/single-flight-*') ?: [] as $f) {
        @unlink($f);
    }
    foreach (glob(sys_get_temp_dir().'/rendition-*') ?: [] as $f) {
        @unlink($f);
    }
});

test('it renders when nothing else holds the lock', function () {
    $source = sourceImage();
    $destination = destinationPath();

    app(ThumbnailGenerator::class)->generate(
        $source, $destination, 'image/jpeg', ImageAudience::External, ImageRendition::Thumbnail,
    );

    expect(is_file($destination))->toBeTrue()
        ->and(filesize($destination))->toBeGreaterThan(0);
});

// The whole point: a waiter that gets in after the winner finished must
// read the file rather than decode the source a second time.
test('a request that waited serves what the winner made instead of rendering again', function () {
    $source = sourceImage();
    $destination = destinationPath();

    // Stand in for the winner: the rendition is already there.
    file_put_contents($destination, 'already rendered');
    $before = filemtime($destination);

    app(ThumbnailGenerator::class)->generate(
        $source, $destination, 'image/jpeg', ImageAudience::External, ImageRendition::Thumbnail,
    );

    // Untouched — not re-encoded over the top.
    expect(file_get_contents($destination))->toBe('already rendered')
        ->and(filemtime($destination))->toBe($before);
});

// An empty file is what a render killed mid-flight leaves behind. It is
// not a rendition, and treating it as one serves a broken image for as
// long as the file lives, because nothing invalidates a cached rendition.
test('an empty file is not treated as somebody else\'s finished work', function () {
    $source = sourceImage();
    $destination = destinationPath();

    file_put_contents($destination, '');

    app(ThumbnailGenerator::class)->generate(
        $source, $destination, 'image/jpeg', ImageAudience::External, ImageRendition::Thumbnail,
    );

    expect(filesize($destination))->toBeGreaterThan(0);
});

// Deliberately not rendering anyway on timeout: falling through would
// reinstate the pile-on at the moment the system is already struggling.
// One failed thumbnail beats a container that dies and takes the warm
// cache with it.
test('it refuses rather than piling on when the wait times out', function () {
    // Shortened so the suite does not pay the real wait; the behaviour
    // under test is what happens when it elapses, not its length.
    config()->set('projectsend.rendition_lock_wait_seconds', 1);

    $source = sourceImage();
    $destination = destinationPath();

    // Somebody else is mid-render and has not finished.
    $held = Cache::lock('rendition:'.sha1($destination), 120);
    expect($held->get())->toBeTrue();

    $generator = app(ThumbnailGenerator::class);

    expect(fn () => $generator->generate(
        $source, $destination, 'image/jpeg', ImageAudience::External, ImageRendition::Thumbnail,
    ))->toThrow(RuntimeException::class);

    // And it did not decode the source behind the holder's back.
    expect(is_file($destination))->toBeFalse();

    $held->release();
});

// The lock is per rendition, not global: two different images must not
// queue behind each other.
test('two different renditions do not block one another', function () {
    $source = sourceImage();
    $mine = destinationPath();
    $theirs = destinationPath();

    $held = Cache::lock('rendition:'.sha1($theirs), 120);
    expect($held->get())->toBeTrue();

    app(ThumbnailGenerator::class)->generate(
        $source, $mine, 'image/jpeg', ImageAudience::External, ImageRendition::Thumbnail,
    );

    expect(is_file($mine))->toBeTrue();

    $held->release();
});

test('the lock is released, so the next request is not blocked by the last', function () {
    $source = sourceImage();
    $destination = destinationPath();
    $generator = app(ThumbnailGenerator::class);

    $generator->generate($source, $destination, 'image/jpeg', ImageAudience::External, ImageRendition::Thumbnail);

    expect(Cache::lock('rendition:'.sha1($destination), 5)->get())->toBeTrue();
});

// A render that throws must not leave the lock held, or one oversized
// image would wedge that rendition for everybody until the TTL expired.
test('a failed render still releases the lock', function () {
    $destination = destinationPath();
    $generator = app(ThumbnailGenerator::class);

    expect(fn () => $generator->generate(
        '/nonexistent/source.jpg', $destination, 'image/jpeg', ImageAudience::External, ImageRendition::Thumbnail,
    ))->toThrow(RuntimeException::class);

    expect(Cache::lock('rendition:'.sha1($destination), 5)->get())->toBeTrue();
});

// A stray empty environment variable would otherwise make every
// concurrent request fail instantly instead of waiting — the exact
// opposite of what this is for, produced by doing nothing wrong.
//
// Asserted on the resolved value rather than on the clock: Laravel's
// block() measures in whole seconds, so a one-second wait can elapse on
// the very next tick and a timing assertion here would be flaky rather
// than wrong.
test('a zero, empty or nonsense wait still waits', function () {
    $resolve = function ($value): int {
        config()->set('projectsend.rendition_lock_wait_seconds', $value);

        $method = new ReflectionMethod(ThumbnailGenerator::class, 'lockWaitSeconds');

        return $method->invoke(app(ThumbnailGenerator::class));
    };

    // Never zero, whatever arrives.
    expect($resolve(0))->toBe(1)
        ->and($resolve(-5))->toBe(1)
        // Not a number at all: fall back to the default rather than to
        // nothing, which is what an unset or misspelled variable gives.
        ->and($resolve(''))->toBe(15)
        ->and($resolve(null))->toBe(15)
        ->and($resolve('nonsense'))->toBe(15)
        // And an honest value is honoured.
        ->and($resolve(30))->toBe(30)
        ->and($resolve('45'))->toBe(45);
});
