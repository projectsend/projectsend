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

/**
 * Preview beyond images: the types a browser plays natively, which this
 * app never decodes and therefore never renders, caches or watermarks.
 * The allowlist that admits them is PreviewKind, deliberately separate
 * from the rendition allowlist asserted in FileThumbnailsTest.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    // Settings survive RefreshDatabase's rollback through their cache, so
    // a test that means "the default" has to say so rather than assume it.
    app(Settings::class)->set(Setting::ClientsCanPreviewFiles, true);
});

function uploadMediaFile(User $as, string $name, string $mimeType): File
{
    test()->actingAs($as)->post('/files', [
        'file' => UploadedFile::fake()->create($name, 16, $mimeType),
        'name' => '',
        'description' => '',
    ]);

    return File::query()->latest('id')->firstOrFail();
}

test('a media file is served inline, as itself, from the local disk', function (string $name, string $mimeType) {
    $file = uploadMediaFile($this->admin, $name, $mimeType);

    $this->actingAs($this->admin)->get("/files/{$file->id}/preview")
        ->assertOk()
        ->assertHeader('Content-Type', $mimeType)
        ->assertHeader('Content-Disposition', 'inline; filename="'.$name.'"')
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path)
        ->assertHeader('Content-Length', (string) $file->size);
})->with([
    'mp4 video' => ['clip.mp4', 'video/mp4'],
    'webm video' => ['clip.webm', 'video/webm'],
    'mp3 audio' => ['song.mp3', 'audio/mpeg'],
    'wav audio' => ['song.wav', 'audio/x-wav'],
    'flac audio' => ['song.flac', 'audio/flac'],
    'pdf' => ['contract.pdf', 'application/pdf'],
]);

// A format the browser would only show a black rectangle for is not
// previewable, however happily it uploads and downloads.
test('a media container no browser decodes is not previewable', function (string $name, string $mimeType) {
    $file = uploadMediaFile($this->admin, $name, $mimeType);

    $this->actingAs($this->admin)->get("/files/{$file->id}/preview")->assertNotFound();
})->with([
    'quicktime' => ['clip.mov', 'video/quicktime'],
    'avi' => ['clip.avi', 'video/x-msvideo'],
    'matroska' => ['clip.mkv', 'video/x-matroska'],
]);

// One deliberate act, however many Range requests the browser turns it
// into. Without the guard, watching one video buries the activity log.
test('replaying a preview logs it once, not once per request', function () {
    $file = uploadMediaFile($this->admin, 'clip.mp4', 'video/mp4');

    foreach (range(1, 5) as $ignored) {
        $this->actingAs($this->admin)->get("/files/{$file->id}/preview")->assertOk();
    }

    expect(ActivityLog::query()->where('action', Action::FilePreviewed)->where('subject_id', $file->id)->count())->toBe(1);
});

// Keyed by viewer, so one person's playback cannot swallow the record of
// somebody else looking at the same file.
test('two viewers of the same file are each logged', function () {
    $client = User::factory()->client()->create();
    $file = uploadMediaFile($this->admin, 'clip.mp4', 'video/mp4');
    shareFileWith($file, $client);

    $this->actingAs($this->admin)->get("/files/{$file->id}/preview")->assertOk();
    $this->actingAs($client)->get("/files/{$file->id}/preview")->assertOk();

    $actors = ActivityLog::query()->where('action', Action::FilePreviewed)->where('subject_id', $file->id)
        ->pluck('actor_id')->sort()->values()->all();

    expect($actors)->toBe(collect([$this->admin->id, $client->id])->sort()->values()->all());
});

test('with client preview switched off a client cannot preview, and staff still can', function () {
    app(Settings::class)->set(Setting::ClientsCanPreviewFiles, false);

    $client = User::factory()->client()->create();
    $file = uploadMediaFile($this->admin, 'contract.pdf', 'application/pdf');
    shareFileWith($file, $client);

    $this->actingAs($client)->get("/files/{$file->id}/preview")->assertNotFound();
    $this->actingAs($this->admin)->get("/files/{$file->id}/preview")->assertOk();
});

// The switch is about looking, never about having: a client who is
// refused a preview downloads exactly as before.
test('a client refused a preview can still download the file', function () {
    app(Settings::class)->set(Setting::ClientsCanPreviewFiles, false);

    $client = User::factory()->client()->create();
    $file = uploadMediaFile($this->admin, 'contract.pdf', 'application/pdf');
    shareFileWith($file, $client);

    $this->actingAs($client)->get("/files/{$file->id}/download")->assertOk();
});

test('the portal tells its theme whether preview is offered', function () {
    app(Settings::class)->set(Setting::Theme, 'default');
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/my-files')
        ->assertInertia(fn ($page) => $page->where('preview_enabled', true));

    app(Settings::class)->set(Setting::ClientsCanPreviewFiles, false);

    $this->actingAs($client)->get('/my-files')
        ->assertInertia(fn ($page) => $page->where('preview_enabled', false));
});
