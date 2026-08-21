<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Thumbnails\Events\ImageRenderingChanged;
use App\Modules\Files\Thumbnails\Events\RenderingImage;
use App\Modules\Files\Thumbnails\Events\ResolvingImageRendering;
use App\Modules\Files\Thumbnails\ImageAudience;
use App\Modules\Files\Thumbnails\ImageRendition;
use App\Modules\Files\Thumbnails\RenderedImageCache;
use App\Modules\Files\Thumbnails\ThumbnailGenerator;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * The seam a package hooks to change what a rendered image looks like —
 * the watermark in cloud-modules is its first consumer, and none of its
 * code is reachable from this repo. What is asserted here is the
 * contract that package is written against: that the hook fires with the
 * image still in memory, that mutating it reaches the file on disk, that
 * each audience and rendition is cached apart, that a preview only
 * becomes a rendering when a listener says it must, and that announcing
 * a rendering change clears the cache that would otherwise keep serving
 * the old bytes forever.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

test('generating a thumbnail dispatches the rendering hook before the image is written', function () {
    $file = uploadImageFile($this->admin);

    $seen = null;

    Event::listen(RenderingImage::class, function (RenderingImage $event) use (&$seen): void {
        $seen = $event;
    });

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertOk();

    expect($seen)->not->toBeNull()
        ->and($seen->mimeType)->toBe('image/jpeg')
        ->and($seen->audience)->toBe(ImageAudience::Staff)
        // Already scaled into the thumbnail box: a listener sizing a
        // watermark against it is measuring the finished thumbnail, not
        // the original upload (which is 200x100 here).
        ->and($seen->image->getWidth())->toBeLessThanOrEqual(300)
        ->and($seen->image->getHeight())->toBeLessThanOrEqual(300);
});

// The whole point of the audience: a listener has to be able to leave the
// staff file manager alone while marking what everyone else sees.
test('the hook is told which audience each thumbnail is for', function () {
    $client = User::factory()->client()->create();
    $file = uploadImageFile($this->admin);
    shareFileWith($file, $client);

    $seen = [];

    Event::listen(RenderingImage::class, function (RenderingImage $event) use (&$seen): void {
        $seen[] = $event->audience;
    });

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertOk();
    $this->actingAs($client)->get("/files/{$file->id}/thumbnail")->assertOk();

    expect($seen)->toBe([ImageAudience::Staff, ImageAudience::External]);
});

// Two viewers, one URL. Without separate cache entries the first request
// would decide what the second one saw for as long as the file existed.
test('staff and clients are served different cached files for the same thumbnail URL', function () {
    $client = User::factory()->client()->create();
    $file = uploadImageFile($this->admin);
    shareFileWith($file, $client);

    // A listener that only ever touches the external variant — the shape
    // the watermark takes.
    Event::listen(RenderingImage::class, function (RenderingImage $event): void {
        if ($event->audience === ImageAudience::External) {
            $event->image->resize(32, 32);
        }
    });

    $staffPath = ThumbnailGenerator::pathFor($file->id, 'image/jpeg', ImageAudience::Staff, ImageRendition::Thumbnail);
    $externalPath = ThumbnailGenerator::pathFor($file->id, 'image/jpeg', ImageAudience::External, ImageRendition::Thumbnail);

    expect($staffPath)->not->toBe($externalPath);

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$staffPath);

    $this->actingAs($client)->get("/files/{$file->id}/thumbnail")
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$externalPath);

    $disk = Storage::disk('files');

    expect(getimagesize($disk->path($externalPath))[0])->toBe(32)
        ->and(getimagesize($disk->path($staffPath))[0])->toBeGreaterThan(32);
});

// Anonymous, so there is no user to read an audience off — it must still
// come out external rather than defaulting to the internal variant.
test('a public listing thumbnail is generated for the external audience', function () {
    app(Settings::class)->set(Setting::PublicListingEnabled, true);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');

    $file = uploadImageFile($this->admin);
    $file->update(['public' => true]);

    $seen = null;

    Event::listen(RenderingImage::class, function (RenderingImage $event) use (&$seen): void {
        $seen = $event->audience;
    });

    $this->get(route('public.thumbnail', ['public', $file->fresh()->slug]))->assertOk();

    expect($seen)->toBe(ImageAudience::External)
        ->and(Storage::disk('files')->exists(
            ThumbnailGenerator::pathFor($file->id, 'image/jpeg', ImageAudience::External, ImageRendition::Thumbnail)
        ))->toBeTrue();
});

test('what a listener does to the image is what lands on disk', function () {
    $file = uploadImageFile($this->admin);

    Event::listen(RenderingImage::class, function (RenderingImage $event): void {
        $event->image->resize(24, 24);
    });

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertOk();

    $written = getimagesize(Storage::disk('files')->path("thumbnails/{$file->id}.jpg"));

    expect($written[0])->toBe(24)->and($written[1])->toBe(24);
});

// A rendition is written once and then served from cache forever, so a
// package that changes how images render has no other way to make its
// change visible on files anyone has already looked at.
test('announcing a rendering change clears the cached renditions', function () {
    $file = uploadImageFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertOk();
    expect(Storage::disk('files')->exists("thumbnails/{$file->id}.jpg"))->toBeTrue();

    Event::dispatch(new ImageRenderingChanged);

    expect(Storage::disk('files')->exists("thumbnails/{$file->id}.jpg"))->toBeFalse();
});

// The half of the seam a package can actually reach: it cannot construct
// a host class, so it dispatches the event by string class name — which
// only works because the event carries no payload.
test('the change can be announced by string class name, as a package must', function () {
    $file = uploadImageFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertOk();

    Event::dispatch('App\Modules\Files\Thumbnails\Events\ImageRenderingChanged');

    expect(Storage::disk('files')->exists("thumbnails/{$file->id}.jpg"))->toBeFalse();
});

test('flushing a cache that is not there is not an error', function () {
    app(RenderedImageCache::class)->flush();
})->throwsNoExceptions();

// ---------------------------------------------------------------------
// Previews
// ---------------------------------------------------------------------

// The fast path this endpoint has always taken, and still takes on every
// installation where nothing wants to decorate a preview: no decoding, no
// cached copy, just the stored file.
test('a preview serves the original file when no listener asks for a rendering', function () {
    $file = uploadImageFile($this->admin);

    $rendered = false;
    Event::listen(RenderingImage::class, function () use (&$rendered): void {
        $rendered = true;
    });

    $this->actingAs($this->admin)->get("/files/{$file->id}/preview")
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path);

    expect($rendered)->toBeFalse()
        ->and(Storage::disk('files')->exists("previews/{$file->id}.jpg"))->toBeFalse();
});

test('core asks whether a preview must be rendered, and defaults to no', function () {
    $client = User::factory()->client()->create();
    $file = uploadImageFile($this->admin);
    shareFileWith($file, $client);

    $asked = [];
    Event::listen(ResolvingImageRendering::class, function (ResolvingImageRendering $event) use (&$asked): void {
        $asked[] = [$event->audience, $event->rendition, $event->required];
    });

    $this->actingAs($client)->get("/files/{$file->id}/preview")->assertOk();

    expect($asked)->toBe([[ImageAudience::External, ImageRendition::Preview, false]]);
});

// The trap this closes: the watermark listener decides on audience
// alone, so if a PDF or a video reached the resolving hook it would come
// back "render this" — and ThumbnailGenerator has no rendition for
// either, which would 404 every non-image preview on a watermarking
// installation. Core never asks about a type it cannot render.
test('core does not ask about rendering a file it could never render', function () {
    $client = User::factory()->client()->create();
    $file = uploadDocumentFile($this->admin);
    shareFileWith($file, $client);

    $asked = [];
    Event::listen(ResolvingImageRendering::class, function (ResolvingImageRendering $event) use (&$asked): void {
        $asked[] = $event->mimeType;
        $event->required = true;
    });

    $this->actingAs($client)->get("/files/{$file->id}/preview")
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path);

    expect($asked)->toBe([]);
});

// A preview is a rendered view of a file, not the file — so a listener
// that intends to decorate it (the watermark) can have it rendered, and
// what the client then sees is the decorated copy rather than the
// original bytes. This is the whole feature.
test('a listener can turn a preview into a rendered, decorated copy', function () {
    $client = User::factory()->client()->create();
    $file = uploadImageFile($this->admin);
    shareFileWith($file, $client);

    Event::listen(ResolvingImageRendering::class, function (ResolvingImageRendering $event): void {
        if ($event->audience === ImageAudience::External) {
            $event->required = true;
        }
    });

    Event::listen(RenderingImage::class, function (RenderingImage $event): void {
        if ($event->rendition === ImageRendition::Preview) {
            $event->image->resize(48, 48);
        }
    });

    $previewPath = ThumbnailGenerator::pathFor($file->id, 'image/jpeg', ImageAudience::External, ImageRendition::Preview);

    $this->actingAs($client)->get("/files/{$file->id}/preview")
        ->assertOk()
        // Not the original's path: the client is served the rendering.
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$previewPath);

    expect(getimagesize(Storage::disk('files')->path($previewPath))[0])->toBe(48);

    // The same listener leaves staff alone, so staff still get the file.
    $this->actingAs($this->admin)->get("/files/{$file->id}/preview")
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path);
});

// Thumbnails and previews are separate cache entries, or the 300px icon
// and the full-size view would overwrite each other.
test('a preview and a thumbnail of the same file are cached apart and bounded differently', function () {
    $client = User::factory()->client()->create();
    $file = uploadImageFile($this->admin, 'wide.jpg');
    shareFileWith($file, $client);

    Event::listen(ResolvingImageRendering::class, function (ResolvingImageRendering $event): void {
        $event->required = true;
    });

    $bounds = [];
    Event::listen(RenderingImage::class, function (RenderingImage $event) use (&$bounds): void {
        $bounds[$event->rendition->value] = $event->rendition->maxDimension();
    });

    $this->actingAs($client)->get("/files/{$file->id}/thumbnail")->assertOk();
    $this->actingAs($client)->get("/files/{$file->id}/preview")->assertOk();

    expect($bounds)->toBe(['thumbnail' => 300, 'preview' => 1600])
        ->and(Storage::disk('files')->exists("thumbnails/external/{$file->id}.jpg"))->toBeTrue()
        ->and(Storage::disk('files')->exists("previews/external/{$file->id}.jpg"))->toBeTrue();
});

test('a rendered preview is cached, so a second request does not re-render', function () {
    $client = User::factory()->client()->create();
    $file = uploadImageFile($this->admin);
    shareFileWith($file, $client);

    Event::listen(ResolvingImageRendering::class, function (ResolvingImageRendering $event): void {
        $event->required = true;
    });

    $renders = 0;
    Event::listen(RenderingImage::class, function () use (&$renders): void {
        $renders++;
    });

    $this->actingAs($client)->get("/files/{$file->id}/preview")->assertOk();
    $this->actingAs($client)->get("/files/{$file->id}/preview")->assertOk();

    expect($renders)->toBe(1);
});

test('flushing the cache drops rendered previews along with thumbnails', function () {
    $client = User::factory()->client()->create();
    $file = uploadImageFile($this->admin);
    shareFileWith($file, $client);

    Event::listen(ResolvingImageRendering::class, function (ResolvingImageRendering $event): void {
        $event->required = true;
    });

    $this->actingAs($client)->get("/files/{$file->id}/thumbnail")->assertOk();
    $this->actingAs($client)->get("/files/{$file->id}/preview")->assertOk();

    Event::dispatch(new ImageRenderingChanged);

    expect(Storage::disk('files')->exists("thumbnails/external/{$file->id}.jpg"))->toBeFalse()
        ->and(Storage::disk('files')->exists("previews/external/{$file->id}.jpg"))->toBeFalse();
});

// Previewing is an audit-worthy action however the bytes get to the
// viewer — the rendering branch must not quietly skip the log entry.
test('a rendered preview is still logged as a preview', function () {
    $client = User::factory()->client()->create();
    $file = uploadImageFile($this->admin);
    shareFileWith($file, $client);

    Event::listen(ResolvingImageRendering::class, function (ResolvingImageRendering $event): void {
        $event->required = true;
    });

    $this->actingAs($client)->get("/files/{$file->id}/preview")->assertOk();

    expect(ActivityLog::query()
        ->where('action', Action::FilePreviewed)
        ->where('subject_id', $file->id)
        ->where('actor_id', $client->id)
        ->exists())->toBeTrue();
});
