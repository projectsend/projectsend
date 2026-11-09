<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Thumbnails\LocalSourceFile;
use App\Modules\Files\Uploads\StoreUploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

test('a file name cannot reorder what it shows', function () {
    // "invoice<RLO>fdp.exe" reads as "invoiceexe.pdf".
    expect(StoreUploadedFile::sanitizeFilename("invoice\u{202E}fdp.exe"))->toBe('invoicefdp.exe')
        ->and(StoreUploadedFile::sanitizeFilename("a\u{2066}b\u{2069}.pdf"))->toBe('ab.pdf')
        ->and(StoreUploadedFile::sanitizeFilename('año contable.pdf'))->toBe('año contable.pdf');
});

test('a huge file on external storage is not pulled down to draw a thumbnail', function () {
    $file = File::factory()->create(['disk' => 'files_external', 'size' => 300 * 1024 * 1024, 'mime_type' => 'image/png']);
    $called = false;

    expect(fn () => app(LocalSourceFile::class)->use($file, function () use (&$called) {
        $called = true;
    }))->toThrow(RuntimeException::class);
    expect($called)->toBeFalse();
});

test('a zip request names a bounded number of files', function () {
    $this->actingAs($this->admin)
        ->postJson('/zip-downloads', ['file_ids' => range(1, 10001)])
        ->assertJsonValidationErrors('file_ids');
});

test('signed-in comment posting and editing are rate limited', function () {
    foreach (['files.comments.store' => 'throttle:20,1,comment-post', 'comments.update' => 'throttle:20,1,comment-edit'] as $name => $throttle) {
        expect(Route::getRoutes()->getByName($name)->gatherMiddleware())->toContain($throttle);
    }
});
