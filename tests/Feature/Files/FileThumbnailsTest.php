<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

function uploadPdfFile(User $as, string $name = 'contract.pdf'): File
{
    test()->actingAs($as)->post('/files', [
        'file' => UploadedFile::fake()->create($name, 4, 'application/pdf'),
        'name' => '',
        'description' => '',
    ]);

    return File::query()->latest('id')->firstOrFail();
}

test('requesting a thumbnail generates and caches it on disk', function () {
    $file = uploadImageFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Content-Disposition', 'inline; filename="photo.jpg"');

    expect(Storage::disk('files')->exists("thumbnails/{$file->id}.jpg"))->toBeTrue();
});

test('a second request reuses the cached thumbnail instead of regenerating it', function () {
    $file = uploadImageFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertOk();

    // If the controller tried to regenerate from source it would now fail
    // (the original is gone) — a passing second request proves the cache
    // was reused instead.
    Storage::disk('files')->delete($file->path);

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertOk();
});

// Existence is the whole cache test, and nothing invalidates a rendition
// once it is there: RenderedImageCache::flush() runs on an event no core
// code raises. So an empty file left by a render that died before writing
// anything was served as the thumbnail from then on.
test('an empty cached rendition is replaced rather than served', function () {
    $file = uploadImageFile($this->admin);
    $path = "thumbnails/{$file->id}.jpg";

    Storage::disk('files')->put($path, '');

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertOk();

    expect(Storage::disk('files')->size($path))->toBeGreaterThan(0);
});

test('the public thumbnail route replaces an empty rendition too', function () {
    $file = publicListingImageFile($this->admin);
    $path = "thumbnails/external/{$file->id}.jpg";

    app(Settings::class)->set(Setting::PublicListingEnabled, true);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');

    Storage::disk('files')->put($path, '');

    $this->get(route('public.thumbnail', ['public', $file->slug]))->assertOk();

    expect(Storage::disk('files')->size($path))->toBeGreaterThan(0);
});

test('a generated rendition leaves nothing half-written beside it', function () {
    // The temporary file the generator renames into place is its own, and
    // it does not outlive the request that made it.
    $file = uploadImageFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertOk();

    expect(collect(Storage::disk('files')->files('thumbnails'))
        ->filter(fn (string $path): bool => str_contains($path, '.partial'))
        ->all())->toBe([]);
});

test('a non-image file 404s when a thumbnail is requested', function () {
    $file = uploadPdfFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertNotFound();
});

test('the preview endpoint serves the original file inline and logs a preview, not a download', function () {
    $file = uploadImageFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/preview")
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path)
        ->assertHeader('Content-Disposition', 'inline; filename="photo.jpg"');

    expect(ActivityLog::query()->where('action', Action::FileDownloaded)->where('subject_id', $file->id)->exists())->toBeFalse();

    $entry = ActivityLog::query()->where('action', Action::FilePreviewed)->where('subject_id', $file->id)->sole();
    expect($entry->actor_id)->toBe($this->admin->id);
});

// A PDF has no thumbnail (nothing here decodes one) but does have a
// preview, which is the whole reason the two allowlists are separate.
test('a file with no thumbnail can still be previewed', function () {
    $file = uploadPdfFile($this->admin);

    $this->actingAs($this->admin)->get("/files/{$file->id}/preview")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path);
});

test('a file of a type no browser plays 404s when a preview is requested', function () {
    $file = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'archive',
        'original_name' => 'archive.zip',
        'path' => '2026/08/'.Str::uuid()->toString().'.zip',
        'mime_type' => 'application/zip',
        'size' => 64,
    ]);

    $this->actingAs($this->admin)->get("/files/{$file->id}/preview")->assertNotFound();
});

// The mime type is detected from the bytes, not the filename, so an
// extension on the upload allowlist is no guarantee of a safe payload: a
// .txt holding HTML is stored as text/html. Serving that inline from this
// app's origin would execute script with the viewer's session, so the
// preview allowlist — not the upload allowlist — has to be what stops it.
test('a file stored with a script-executing mime type is never served inline', function (string $mimeType, string $extension) {
    $file = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'notes',
        'original_name' => 'notes.'.$extension,
        'path' => '2026/08/'.Str::uuid()->toString().'.'.$extension,
        'mime_type' => $mimeType,
        'size' => 64,
    ]);

    $this->actingAs($this->admin)->get("/files/{$file->id}/preview")->assertNotFound();
    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertNotFound();

    // A refused preview is not a preview — nothing to audit.
    expect(ActivityLog::query()->where('action', Action::FilePreviewed)->where('subject_id', $file->id)->exists())->toBeFalse();
})->with([
    'html disguised as a text file' => ['text/html', 'txt'],
    'svg disguised as a text file' => ['image/svg+xml', 'txt'],
    'xml' => ['application/xml', 'xml'],
]);

test('requesting a thumbnail does not create any activity log entry', function () {
    $file = uploadImageFile($this->admin);
    $countBeforeThumbnail = ActivityLog::query()->count();

    $this->actingAs($this->admin)->get("/files/{$file->id}/thumbnail")->assertOk();

    expect(ActivityLog::query()->count())->toBe($countBeforeThumbnail);
});

test('a client cannot view the thumbnail or preview of a file not shared with them', function () {
    $client = User::factory()->client()->create();
    $file = uploadImageFile($this->admin);

    $this->actingAs($client)->get("/files/{$file->id}/thumbnail")->assertForbidden();
    $this->actingAs($client)->get("/files/{$file->id}/preview")->assertForbidden();
});
