<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;

/**
 * The anonymous half of preview: a public file shown in the browser
 * rather than only handed over. Its own switch, separate from the
 * client-portal one, because the audiences are different — anyone with
 * the link, versus someone with an account.
 *
 * Shares publicListingFile()/publicListingImageFile() with
 * PublicGroupsTest, which is where the surrounding public-listing
 * behaviour (slugs, expiry, the directory switch) is covered.
 */
beforeEach(function () {
    Storage::fake('files');

    // EnsureSetupIsComplete sends every guest to /setup until staff exist.
    $this->staff = User::factory()->create();

    app(Settings::class)->set(Setting::PublicListingEnabled, true);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');
    app(Settings::class)->set(Setting::Theme, 'default');
    app(Settings::class)->set(Setting::PublicListingPreviewEnabled, true);
});

test('a visitor can preview a public file, and it is logged as a public preview', function () {
    $file = publicListingFile();

    $this->get(route('public.preview', ['public', $file->slug]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="report.pdf"')
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path);

    expect(ActivityLog::query()->where('action', Action::PublicFilePreviewed)->where('subject_id', $file->id)->exists())->toBeTrue()
        // Previewing is not taking. The download counters must not move.
        ->and(ActivityLog::query()->where('action', Action::PublicFileDownloaded)->where('subject_id', $file->id)->exists())->toBeFalse();
});

test('a file that is not public, or has expired, has no preview', function () {
    $private = publicListingFile(['public' => false]);
    $expired = publicListingFile(['expires_at' => now()->subDay()]);

    $this->get(route('public.preview', ['public', $private->slug]))->assertNotFound();
    $this->get(route('public.preview', ['public', $expired->slug]))->assertNotFound();
});

test('with visitor preview switched off the route is gone but the download is not', function () {
    app(Settings::class)->set(Setting::PublicListingPreviewEnabled, false);

    $file = publicListingFile();

    $this->get(route('public.preview', ['public', $file->slug]))->assertNotFound();
    $this->get(route('public.download', ['public', $file->slug]))->assertOk();
});

test('a type no browser renders has no public preview either', function () {
    $file = publicListingFile(['original_name' => 'archive.zip', 'mime_type' => 'application/zip']);

    $this->get(route('public.preview', ['public', $file->slug]))->assertNotFound();
});

// 403, not 404, for the same reason download() does it: the link never
// broke, the file has simply been taken as often as it was meant to be.
test('a spent download limit closes the preview too', function () {
    $file = publicListingFile(['download_limit' => 1]);

    $this->get(route('public.download', ['public', $file->slug]))->assertOk();

    $this->get(route('public.preview', ['public', $file->slug]))->assertForbidden();
});

test('the public file page carries a preview url only when a visitor may use one', function () {
    $file = publicListingFile();

    $this->get(route('public.file', ['public', $file->slug]))->assertInertia(
        fn ($page) => $page->component('public/themes/default/file')
            ->where('preview_url', route('public.preview', ['public', $file->slug])),
    );

    app(Settings::class)->set(Setting::PublicListingPreviewEnabled, false);

    $this->get(route('public.file', ['public', $file->slug]))
        ->assertInertia(fn ($page) => $page->where('preview_url', null));
});

// Offering a button whose only possible answer is 403 is worse than
// offering none, so the page stops advertising a spent file.
test('a public file whose limit is spent stops offering a preview', function () {
    $file = publicListingFile(['download_limit' => 1]);

    $this->get(route('public.download', ['public', $file->slug]))->assertOk();

    $this->get(route('public.file', ['public', $file->slug]))
        ->assertInertia(fn ($page) => $page->where('preview_url', null));
});
