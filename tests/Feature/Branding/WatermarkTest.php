<?php

declare(strict_types=1);

use claviska\SimpleImage;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use App\Modules\Platform\Branding\Models\BrandingSetting;
use App\Modules\Files\Thumbnails\Events\RenderingImage;
use App\Modules\Files\Thumbnails\Events\ResolvingImageRendering;
use App\Modules\Files\Thumbnails\ImageAudience;
use App\Modules\Files\Thumbnails\ImageRendition;
use App\Modules\Platform\Branding\Watermark\ThumbnailWatermarker;
use App\Modules\Platform\Branding\Watermark\WatermarkPosition;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(staffWithPermissions(['edit_settings']));
});

/**
 * The host's RenderingImage, as the listener actually sees it: an object
 * with an `image` and an `audience` whose `value` is the host enum's
 * backing string. The real classes cannot be imported here — this
 * package builds with no host application present — so standing in for
 * them with anonymous objects is not a shortcut, it *is* the contract
 * being tested.
 */
/*
 * The real events, not the anonymous stand-ins this file carried in the
 * package. It was built and tested with no host application present, so
 * the host's event classes could not be imported and had to be imitated
 * by shape. In core they exist, and a test that constructs the genuine
 * article is testing the genuine contract -- a renamed property or a
 * changed enum now fails here rather than passing against a double that
 * still has the old shape.
 */
function renderingImage(SimpleImage $image, ImageAudience $audience = ImageAudience::External): RenderingImage
{
    return new RenderingImage($image, 'image/png', $audience, ImageRendition::Thumbnail);
}

function storeWatermarkImage(string $color = 'red', int $size = 100): string
{
    $path = 'branding/'.uniqid().'.png';

    Storage::disk('public')->put($path, '');

    (new SimpleImage())
        ->fromNew($size, $size, $color)
        ->toFile(Storage::disk('public')->path($path), 'image/png');

    return $path;
}

test('the branding screen exposes the current watermark settings and the positions to choose from', function () {
    $this->get(route('branding.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('watermark.enabled', false)
            ->where('watermark.position', 'bottom-right')
            ->where('watermark_positions', [
                'top-left', 'top-center', 'top-right',
                'middle-left', 'center', 'middle-right',
                'bottom-left', 'bottom-center', 'bottom-right',
            ]));
});

test('a staff member can turn on watermarking with an image, a position, a size and an opacity', function () {
    $this->post(route('branding.watermark.update'), [
        'image' => UploadedFile::fake()->image('mark.png', 400, 200),
        'enabled' => true,
        'position' => 'top-left',
        'size' => 40,
        'opacity' => 25,
    ])->assertRedirect();

    $setting = BrandingSetting::query()->sole();

    expect($setting->watermark_enabled)->toBeTrue()
        ->and($setting->watermark_position)->toBe(WatermarkPosition::TopLeft)
        ->and($setting->watermark_size)->toBe(40)
        ->and($setting->watermark_opacity)->toBe(25);

    Storage::disk('public')->assertExists($setting->watermark_path);
});

// Otherwise adjusting the opacity would mean re-picking the file every
// time, which is the fastest way to make a settings screen unusable.
test('the image is optional once one is stored', function () {
    $this->post(route('branding.watermark.update'), [
        'image' => UploadedFile::fake()->image('mark.png'),
        'enabled' => true,
        'position' => 'center',
        'size' => 40,
        'opacity' => 25,
    ]);

    $storedPath = BrandingSetting::query()->sole()->watermark_path;

    $this->post(route('branding.watermark.update'), [
        'enabled' => true,
        'position' => 'center',
        'size' => 60,
        'opacity' => 80,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $setting = BrandingSetting::query()->sole();

    expect($setting->watermark_path)->toBe($storedPath)
        ->and($setting->watermark_size)->toBe(60)
        ->and($setting->watermark_opacity)->toBe(80);
});

test('turning watermarking on with no image stored and none supplied is rejected', function () {
    $this->post(route('branding.watermark.update'), [
        'enabled' => true,
        'position' => 'center',
        'size' => 40,
        'opacity' => 50,
    ])->assertSessionHasErrors('image');
});

test('the settings can be saved with the feature switched off and no image at all', function () {
    $this->post(route('branding.watermark.update'), [
        'enabled' => false,
        'position' => 'center',
        'size' => 40,
        'opacity' => 50,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(BrandingSetting::query()->sole()->watermark_enabled)->toBeFalse();
});

test('a position outside the nine supported ones is rejected', function () {
    $this->post(route('branding.watermark.update'), [
        'enabled' => false,
        'position' => 'somewhere-else',
        'size' => 40,
        'opacity' => 50,
    ])->assertSessionHasErrors('position');
});

test('a size or opacity outside its range is rejected', function () {
    $this->post(route('branding.watermark.update'), [
        'enabled' => false, 'position' => 'center', 'size' => 4, 'opacity' => 50,
    ])->assertSessionHasErrors('size');

    $this->post(route('branding.watermark.update'), [
        'enabled' => false, 'position' => 'center', 'size' => 101, 'opacity' => 50,
    ])->assertSessionHasErrors('size');

    $this->post(route('branding.watermark.update'), [
        'enabled' => false, 'position' => 'center', 'size' => 40, 'opacity' => 0,
    ])->assertSessionHasErrors('opacity');
});

test('replacing the image deletes the previous one', function () {
    $this->post(route('branding.watermark.update'), [
        'image' => UploadedFile::fake()->image('first.png'),
        'enabled' => true, 'position' => 'center', 'size' => 40, 'opacity' => 50,
    ]);
    $firstPath = BrandingSetting::query()->sole()->watermark_path;

    $this->post(route('branding.watermark.update'), [
        'image' => UploadedFile::fake()->image('second.png'),
        'enabled' => true, 'position' => 'center', 'size' => 40, 'opacity' => 50,
    ]);
    $secondPath = BrandingSetting::query()->sole()->watermark_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

// An "enabled" with nothing to draw is not a state the screen may leave
// behind — see BrandingController::destroyWatermark().
test('removing the image deletes the file and switches watermarking off', function () {
    $this->post(route('branding.watermark.update'), [
        'image' => UploadedFile::fake()->image('mark.png'),
        'enabled' => true, 'position' => 'center', 'size' => 40, 'opacity' => 50,
    ]);
    $path = BrandingSetting::query()->sole()->watermark_path;

    $this->delete(route('branding.watermark.destroy'))->assertRedirect();

    $setting = BrandingSetting::query()->sole();

    expect($setting->watermark_path)->toBeNull()
        ->and($setting->watermark_enabled)->toBeFalse();

    Storage::disk('public')->assertMissing($path);
});

// The host caches every image it renders and never revisits it, so a
// change that does not announce itself is a change nobody who has already
// browsed their files will ever see.
test('saving or removing the watermark asks the host to drop its rendered images', function () {
    Event::fake();

    $this->post(route('branding.watermark.update'), [
        'image' => UploadedFile::fake()->image('mark.png'),
        'enabled' => true, 'position' => 'center', 'size' => 40, 'opacity' => 50,
    ]);

    $this->delete(route('branding.watermark.destroy'));

    Event::assertDispatchedTimes('App\Modules\Files\Thumbnails\Events\ImageRenderingChanged', 2);
});

test('a staff member without edit_settings cannot change the watermark', function () {
    $this->actingAs(staffWithPermissions([]));

    $this->post(route('branding.watermark.update'), [
        'enabled' => false, 'position' => 'center', 'size' => 40, 'opacity' => 50,
    ])->assertForbidden();

    $this->delete(route('branding.watermark.destroy'))->assertForbidden();
});

test('the routes 404 when the capability is unavailable', function () {
    config(['projectsend.capabilities_disabled' => 'branding.customize']);

    $this->post(route('branding.watermark.update'), [
        'enabled' => false, 'position' => 'center', 'size' => 40, 'opacity' => 50,
    ])->assertNotFound();
});

test('the stored extension comes from the content, never from the uploaded filename', function () {
    $temp = tempnam(sys_get_temp_dir(), 'polyglot');
    file_put_contents($temp, "GIF89a=1;\n<html><body><script>alert(document.domain)</script></body></html>");

    $this->post(route('branding.watermark.update'), [
        'image' => new UploadedFile($temp, 'mark.html', 'image/gif', null, true),
        'enabled' => true, 'position' => 'center', 'size' => 40, 'opacity' => 50,
    ])->assertRedirect();

    $path = BrandingSetting::query()->sole()->watermark_path;

    expect(pathinfo($path, PATHINFO_EXTENSION))->toBe('gif');

    @unlink($temp);
});

// ---------------------------------------------------------------------
// The listener itself
// ---------------------------------------------------------------------

test('the mark is drawn in the corner it was anchored to, and nowhere else', function () {
    BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => storeWatermarkImage('red'),
        'watermark_position' => WatermarkPosition::BottomRight,
        'watermark_size' => 40,
        'watermark_opacity' => 100,
    ]);

    $thumbnail = (new SimpleImage())->fromNew(200, 200, 'white');

    app(ThumbnailWatermarker::class)->handle(renderingImage($thumbnail));

    // Inside the mark: 40% of 200px is an 80px box, inset 4% (8px) from
    // the bottom-right corner, so (150, 150) lands well within it.
    expect($thumbnail->getColorAt(150, 150)['red'])->toBeGreaterThan(200)
        ->and($thumbnail->getColorAt(150, 150)['blue'])->toBeLessThan(50)
        // The opposite corner is untouched white.
        ->and($thumbnail->getColorAt(10, 10))->toMatchArray(['red' => 255, 'green' => 255, 'blue' => 255]);
});

// The same backstop the shared logo has: a row can outlive the capability
// that allowed it, and a community-edition installation must not be
// stamping images it has no screen to configure.
test('nothing is drawn when the host does not grant the capability', function () {
    BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => storeWatermarkImage('red'),
        'watermark_position' => WatermarkPosition::BottomRight,
        'watermark_size' => 40,
        'watermark_opacity' => 100,
    ]);

    $thumbnail = (new SimpleImage())->fromNew(200, 200, 'white');

    // Taken away the way a hosted plan takes it away.
    config(['projectsend.capabilities_disabled' => 'branding.customize']);
    forgetRequestState();

    app(ThumbnailWatermarker::class)->handle(renderingImage($thumbnail));

    expect($thumbnail->getColorAt(150, 150))->toMatchArray(['red' => 255, 'green' => 255, 'blue' => 255]);

    // And with it granted, the very same call does mark the image — so the
    // assertion above is the capability talking, not a broken fixture.
    config(['projectsend.capabilities_disabled' => null]);
    forgetRequestState();

    app(ThumbnailWatermarker::class)->handle(renderingImage($thumbnail));

    expect($thumbnail->getColorAt(150, 150)['red'])->toBeGreaterThan(200)
        ->and($thumbnail->getColorAt(150, 150)['blue'])->toBeLessThan(50);
});

// SimpleImage's bestFit() returns early when the image already fits, so
// reaching for it here would have left the size setting doing nothing at
// all for any mark smaller than the box — the most likely case, since a
// logo is usually a small file.
test('the size setting scales a mark up as well as down', function (int $markSize, int $percent, int $expected) {
    BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => storeWatermarkImage('red', $markSize),
        'watermark_position' => WatermarkPosition::TopLeft,
        'watermark_size' => $percent,
        'watermark_opacity' => 100,
    ]);

    $thumbnail = (new SimpleImage())->fromNew(200, 200, 'white');

    app(ThumbnailWatermarker::class)->handle(renderingImage($thumbnail));

    // The mark is square and inset 8px from the top-left, so the last
    // covered pixel is at (8 + expected - 1) on both axes.
    $inside = $thumbnail->getColorAt(8 + $expected - 2, 8 + $expected - 2);
    $outside = $thumbnail->getColorAt(8 + $expected + 2, 8 + $expected + 2);

    expect($inside['red'])->toBeGreaterThan(200)->and($inside['blue'])->toBeLessThan(50)
        ->and($outside)->toMatchArray(['red' => 255, 'green' => 255, 'blue' => 255]);
})->with([
    'a large mark is scaled down' => [400, 25, 50],
    'a small mark is scaled up' => [20, 50, 100],
]);

test('opacity is honoured, so the mark blends rather than replaces', function () {
    BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => storeWatermarkImage('black'),
        'watermark_position' => WatermarkPosition::Center,
        'watermark_size' => 80,
        'watermark_opacity' => 50,
    ]);

    $thumbnail = (new SimpleImage())->fromNew(200, 200, 'white');

    app(ThumbnailWatermarker::class)->handle(renderingImage($thumbnail));

    // Black at 50% over white is grey, not black — the distinguishing
    // assertion, since a full-opacity bug would still darken the pixel.
    $centre = $thumbnail->getColorAt(100, 100);

    expect($centre['red'])->toBeGreaterThan(80)->toBeLessThan(180);
});

// ---------------------------------------------------------------------
// The live sample on the settings screen
// ---------------------------------------------------------------------

// Staff surfaces are never watermarked, so without this an administrator
// has no way to judge their own settings short of signing in as a client.
test('the sample renders the mark with the values passed in, not the saved ones', function () {
    BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => storeWatermarkImage('red'),
        // Saved as a small mark in a corner; the request asks for a big
        // centred one, and the request is what must come back.
        'watermark_position' => WatermarkPosition::TopLeft,
        'watermark_size' => 10,
        'watermark_opacity' => 20,
    ]);

    $response = $this->get(route('branding.watermark.sample', [
        'position' => 'center', 'size' => 90, 'opacity' => 100,
    ]))->assertOk()->assertHeader('Content-Type', 'image/png');

    $image = (new SimpleImage())->fromString($response->getContent());

    // Dead centre is under a 90%-wide, fully opaque red mark. The backdrop
    // is a grey ramp, so a red channel far above the other two can only be
    // the mark.
    $centre = $image->getColorAt((int) ($image->getWidth() / 2), (int) ($image->getHeight() / 2));

    expect($centre['red'])->toBeGreaterThan(150)
        ->and($centre['red'] - $centre['blue'])->toBeGreaterThan(80);
});

// The backdrop exists to answer "will my mark still read?", which it can
// only do if it actually spans dark to light. It rendered as a flat block
// once — SimpleImage's alpha is 1-is-opaque, and the ramp was drawn
// entirely transparent — and no assertion about the mark noticed.
test('the sample backdrop ramps from dark to light so opacity can be judged', function () {
    BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => storeWatermarkImage('red'),
    ]);

    // Top-left, so the ramp's own top-right corner stays uncovered.
    $response = $this->get(route('branding.watermark.sample', [
        'position' => 'top-left', 'size' => 20, 'opacity' => 100,
    ]))->assertOk();

    $image = (new SimpleImage())->fromString($response->getContent());

    $left = $image->getColorAt(4, $image->getHeight() - 4);
    $right = $image->getColorAt($image->getWidth() - 4, 4);

    expect($right['red'])->toBeGreaterThan($left['red'] + 100);
});

test('the sample refuses values the form itself would refuse', function (array $query) {
    BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => storeWatermarkImage('red'),
    ]);

    $this->get(route('branding.watermark.sample', $query))->assertSessionHasErrors();
})->with([
    'unknown position' => [['position' => 'nowhere', 'size' => 40, 'opacity' => 50]],
    'size below the range' => [['position' => 'center', 'size' => 1, 'opacity' => 50]],
    'opacity above the range' => [['position' => 'center', 'size' => 40, 'opacity' => 300]],
]);

// The screen asks for this image before there is necessarily anything to
// draw. A 404 lets it hide the sample; a blank 200 would look like a bug.
test('the sample 404s when no watermark image is stored', function () {
    $this->get(route('branding.watermark.sample', [
        'position' => 'center', 'size' => 40, 'opacity' => 50,
    ]))->assertNotFound();
});

test('the sample is gated exactly like the rest of the screen', function () {
    BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => storeWatermarkImage('red'),
    ]);

    $query = ['position' => 'center', 'size' => 40, 'opacity' => 50];

    $this->actingAs(staffWithPermissions([]));
    $this->get(route('branding.watermark.sample', $query))->assertForbidden();

    $this->actingAs(staffWithPermissions(['edit_settings']));
    config(['projectsend.capabilities_disabled' => 'branding.customize']);
    $this->get(route('branding.watermark.sample', $query))->assertNotFound();
});

/**
 * The host's ResolvingImageRendering, as the listener sees it: a mutable
 * `required` plus the same duck-typed audience.
 */
function resolvingImageRendering(ImageAudience $audience = ImageAudience::External): ResolvingImageRendering
{
    return new ResolvingImageRendering($audience, ImageRendition::Thumbnail, 'image/png');
}

// A preview is a rendered view of a file, not the file — but rendering
// one is expensive, so the host serves the stored bytes unless a listener
// says it must render. Saying so is the only thing that gets the mark
// onto a client's preview at all.
test('the host is asked to render a preview only when there is a mark to draw', function (?callable $arrange, bool $expected) {
    $arrange?->call($this);

    $event = resolvingImageRendering();

    app(ThumbnailWatermarker::class)->resolve($event);

    expect($event->required)->toBe($expected);
})->with([
    'nothing configured' => [null, false],
    'switched off' => [fn () => BrandingSetting::create([
        'watermark_enabled' => false,
        'watermark_path' => storeWatermarkImage('red'),
    ]), false],
    'on, but no image chosen' => [fn () => BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => null,
    ]), false],
    // Asking the host to render something this listener would then
    // decline to draw on would cost a full re-encode for nothing.
    'on, but the image is gone from disk' => [fn () => BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => 'branding/deleted-by-someone.png',
    ]), false],
    'fully configured' => [fn () => BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => storeWatermarkImage('red'),
    ]), true],
]);

// Staff previews stay the stored file — not a rendered copy of it — for
// the same reason staff thumbnails stay unmarked.
test('the host is never asked to render a staff preview', function () {
    BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => storeWatermarkImage('red'),
    ]);

    $event = resolvingImageRendering(ImageAudience::Staff);

    app(ThumbnailWatermarker::class)->resolve($event);

    expect($event->required)->toBeFalse();
});

// Watermarking exists for the copies that leave the building. Marking the
// staff file manager and file editor too would only obscure the originals
// from the people who uploaded them.
test('staff thumbnails are left unmarked even with watermarking fully configured', function () {
    BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => storeWatermarkImage('red'),
        'watermark_position' => WatermarkPosition::Center,
        'watermark_size' => 90,
        'watermark_opacity' => 100,
    ]);

    $staffThumbnail = (new SimpleImage())->fromNew(200, 200, 'white');
    $clientThumbnail = (new SimpleImage())->fromNew(200, 200, 'white');

    app(ThumbnailWatermarker::class)->handle(renderingImage($staffThumbnail, ImageAudience::Staff));
    app(ThumbnailWatermarker::class)->handle(renderingImage($clientThumbnail, ImageAudience::External));

    expect($staffThumbnail->getColorAt(100, 100))->toMatchArray(['red' => 255, 'green' => 255, 'blue' => 255])
        ->and($clientThumbnail->getColorAt(100, 100)['blue'])->toBeLessThan(50);
});

test('nothing is drawn when watermarking is off, unconfigured, or its image has gone missing', function (?callable $arrange) {
    $arrange?->call($this);

    $thumbnail = (new SimpleImage())->fromNew(200, 200, 'white');

    app(ThumbnailWatermarker::class)->handle(renderingImage($thumbnail));

    expect($thumbnail->getColorAt(150, 150))->toMatchArray(['red' => 255, 'green' => 255, 'blue' => 255]);
})->with([
    'no settings row at all' => [null],
    'switched off' => [fn () => BrandingSetting::create([
        'watermark_enabled' => false,
        'watermark_path' => storeWatermarkImage('red'),
    ])],
    'on, but no image was ever chosen' => [fn () => BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => null,
    ])],
    // A row can outlive its file: a restored backup, a pruned disk. It
    // must degrade to a plain thumbnail rather than throwing, which here
    // would mean a broken image on every listing row in the app.
    'on, but the image is gone from disk' => [fn () => BrandingSetting::create([
        'watermark_enabled' => true,
        'watermark_path' => 'branding/deleted-by-someone.png',
    ])],
]);
