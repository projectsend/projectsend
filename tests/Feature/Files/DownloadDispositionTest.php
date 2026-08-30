<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Support\ContentDisposition;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

test('ascii filenames keep the exact quoted header format the app has always sent', function () {
    expect(ContentDisposition::attachment('contract.pdf'))
        ->toBe('attachment; filename="contract.pdf"')
        ->and(ContentDisposition::inline('photo.jpg'))
        ->toBe('inline; filename="photo.jpg"');
});

test('non-ascii filenames add an RFC 6266 filename* ext-value with an ascii fallback', function () {
    $header = ContentDisposition::attachment('informe año 2026.pdf');

    expect($header)
        ->toStartWith('attachment; filename="')
        ->toContain("; filename*=utf-8''")
        ->toContain(rawurlencode('informe año 2026.pdf'));

    // The legacy filename= parameter must stay pure ASCII — that is the
    // whole point of the split.
    preg_match('/filename="((?:[^"\\\\]|\\\\.)*)"/', $header, $matches);
    expect(preg_match('/^[\x20-\x7E]*$/', $matches[1]))->toBe(1);
});

test('quotes are escaped and path separators neutralised', function () {
    expect(ContentDisposition::attachment('a"b.txt'))
        ->toBe('attachment; filename="a\"b.txt"')
        ->and(ContentDisposition::attachment('../../etc/passwd'))
        ->toBe('attachment; filename=".._.._etc_passwd"');
});

test('a filename that transliterates to nothing still offers a usable fallback', function () {
    $header = ContentDisposition::attachment('中文.txt');

    // Whatever the transliterator makes of the CJK part, the fallback is
    // non-empty ASCII and the true name always travels in filename*.
    preg_match('/filename="((?:[^"\\\\]|\\\\.)*)"/', $header, $matches);
    expect(trim($matches[1]))->not->toBe('')
        ->and($header)->toContain("; filename*=utf-8''".rawurlencode('中文.txt'));
});

test('downloads of files with non-ascii names send both header forms on the wire', function () {
    $file = uploadDocumentFile($this->admin, 'año contable — resumen.pdf');

    $header = $this->actingAs($this->admin)
        ->get("/files/{$file->id}/download")
        ->assertOk()
        ->headers->get('Content-Disposition');

    expect($header)
        ->toContain('attachment; filename="')
        ->toContain("filename*=utf-8''".rawurlencode('año contable — resumen.pdf'));
});

/*
|--------------------------------------------------------------------------
| How long a presigned URL stays usable
|--------------------------------------------------------------------------
|
| On the local disk a delivery is an X-Accel-Redirect: it authorises one
| response, to one request. On external storage it is a presigned URL,
| which is a bearer credential -- forwardable, and valid whatever happens
| to the file or the caller's checks in the meantime. Nothing can revoke
| one, so its lifetime is the only dial there is.
|
*/

test('a download link outlives the redirect and not much else', function () {
    $seen = [];

    Storage::fake('files_external');
    Storage::disk('files_external')->buildTemporaryUrlsUsing(
        function (string $path, $expiration, array $options) use (&$seen): string {
            $seen[] = $expiration;

            return 'https://storage.example.test/'.$path;
        }
    );

    $file = uploadDocumentFile($this->admin, 'report.pdf');
    $file->update(['disk' => 'files_external']);

    $this->actingAs($this->admin)->get("/files/{$file->id}/download")->assertRedirect();

    expect($seen)->toHaveCount(1)
        ->and($seen[0]->getTimestamp())->toBeLessThanOrEqual(now()->addSeconds(60)->getTimestamp())
        ->and($seen[0]->getTimestamp())->toBeGreaterThan(now()->addSeconds(30)->getTimestamp());
});

test('a preview link lasts as long as somebody might watch', function () {
    // The other half of the trade: a player holds this URL and asks it for
    // ranges every time the viewer seeks past the buffer, so a minute would
    // break playback of anything longer than a minute.
    $seen = [];

    Storage::fake('files_external');
    Storage::disk('files_external')->buildTemporaryUrlsUsing(
        function (string $path, $expiration, array $options) use (&$seen): string {
            $seen[] = $expiration;

            return 'https://storage.example.test/'.$path;
        }
    );

    // Uploaded rather than factory-made, so it is a real previewable file;
    // FilePreviewTest's helper is private to that file (Pest globals).
    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('clip.mp4', 16, 'video/mp4'),
        'name' => '',
        'description' => '',
    ]);

    $file = File::query()->latest('id')->firstOrFail();
    $file->update(['disk' => 'files_external']);

    $this->actingAs($this->admin)->get("/files/{$file->id}/preview")->assertRedirect();

    expect($seen)->toHaveCount(1)
        ->and($seen[0]->getTimestamp())->toBeGreaterThan(now()->addMinutes(50)->getTimestamp());
});
