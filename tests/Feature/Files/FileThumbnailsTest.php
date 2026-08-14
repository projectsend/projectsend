<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
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

test('a non-renderable file 404s when a preview is requested', function () {
    $file = uploadPdfFile($this->admin);

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
