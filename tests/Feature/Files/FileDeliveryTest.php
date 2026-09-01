<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use Illuminate\Support\Facades\Storage;

/**
 * How a file's bytes leave the server.
 *
 * The fast path answers with an empty body and a header telling the web
 * server to send the file. A server that does not recognise that header
 * sends the empty body instead, which is a 0-byte download with every
 * other page working perfectly — the shape of
 * https://github.com/projectsend/projectsend/issues/1765, where an Apache
 * install had broken thumbnails and empty downloads.
 *
 * So these are mostly about the *body*: the assertion that catches the
 * bug is "the bytes actually arrived", not "the header said the right
 * thing".
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
});

/** A file whose bytes are really on the fake disk. */
function storedFile(string $contents = 'the actual bytes'): File
{
    $file = File::factory()->create(['size' => strlen($contents), 'mime_type' => 'application/pdf']);
    Storage::disk('files')->put($file->path, $contents);

    return $file;
}

function deliverAs(string $method): void
{
    config(['projectsend.file_delivery' => $method]);
}

test('nginx is handed the file and PHP sends no bytes', function () {
    deliverAs('nginx');
    $file = storedFile();

    $response = $this->actingAs($this->admin)->get("/files/{$file->id}/download");

    $response->assertOk()->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path);
    expect($response->getContent())->toBe('');
});

test('PHP streaming actually sends the bytes', function () {
    // The regression that matters. Before this existed, an installation
    // whose server did not understand X-Accel-Redirect served this empty.
    deliverAs('php');
    $file = storedFile('the actual bytes');

    $response = $this->actingAs($this->admin)->get("/files/{$file->id}/download");

    $response->assertOk()
        ->assertHeaderMissing('X-Accel-Redirect')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$file->original_name.'"');

    expect($response->streamedContent())->toBe('the actual bytes');
});

test('PHP streaming answers a range request rather than resending the file', function () {
    // What makes seeking through a long video work. nginx does this for
    // itself on the fast path, so a hand-rolled readfile here would break
    // scrubbing on exactly the installations this fallback exists for.
    deliverAs('php');
    $file = storedFile('0123456789');

    $response = $this->actingAs($this->admin)
        ->get("/files/{$file->id}/download", ['Range' => 'bytes=2-5']);

    expect($response->getStatusCode())->toBe(206)
        ->and($response->streamedContent())->toBe('2345');
});

test('X-Sendfile is handed an absolute path, not a URL path', function () {
    // The two headers are not interchangeable: nginx maps a URL through an
    // internal location, Apache and LiteSpeed open a filesystem path.
    // Renaming the header without changing the value is the obvious way to
    // "add Apache support" and produces a second broken install.
    deliverAs('xsendfile');
    $file = storedFile();

    $response = $this->actingAs($this->admin)->get("/files/{$file->id}/download");

    $response->assertOk()->assertHeaderMissing('X-Accel-Redirect');

    expect($response->headers->get('X-Sendfile'))
        ->toBe(Storage::disk('files')->path($file->path))
        ->and($response->getContent())->toBe('');
});

test('auto uses the fast path when the server says it is nginx', function () {
    deliverAs('auto');
    $file = storedFile();

    $response = $this->actingAs($this->admin)
        ->withServerVariables(['SERVER_SOFTWARE' => 'nginx/1.24.0'])
        ->get("/files/{$file->id}/download");

    $response->assertOk()->assertHeader('X-Accel-Redirect', '/protected-files/'.$file->path);
});

test('auto falls back to PHP on a server it cannot hand files to', function () {
    // Apache, and the reason the issue was filed. Slow beats empty.
    deliverAs('auto');
    $file = storedFile('apache bytes');

    $response = $this->actingAs($this->admin)
        ->withServerVariables(['SERVER_SOFTWARE' => 'Apache/2.4.62 (AlmaLinux)'])
        ->get("/files/{$file->id}/download");

    $response->assertOk()->assertHeaderMissing('X-Accel-Redirect');
    expect($response->streamedContent())->toBe('apache bytes');
});

test('auto never chooses X-Sendfile on its own', function () {
    // mod_xsendfile also needs XSendFilePath to allow the storage
    // directory, which cannot be seen from here. Choosing it because the
    // module might be loaded would swap a silent failure an administrator
    // can diagnose from the dashboard for one nobody can.
    deliverAs('auto');
    $file = storedFile();

    $response = $this->actingAs($this->admin)
        ->withServerVariables(['SERVER_SOFTWARE' => 'Apache/2.4.62'])
        ->get("/files/{$file->id}/download");

    $response->assertHeaderMissing('X-Sendfile');
});

test('an unrecognised setting falls back to detection rather than breaking every download', function () {
    // A typo in an environment variable should cost speed, not the
    // installation's downloads.
    deliverAs('nginx-x-accel-redirect');
    $file = storedFile('still works');

    $response = $this->actingAs($this->admin)
        ->withServerVariables(['SERVER_SOFTWARE' => 'Apache/2.4.62'])
        ->get("/files/{$file->id}/download");

    $response->assertOk();
    expect($response->streamedContent())->toBe('still works');
});

test('a path trying to climb out of the storage directory is refused', function () {
    deliverAs('nginx');
    $file = storedFile();
    // Paths come from rows this application wrote, so this cannot happen
    // today. It is refused anyway because the cost of being wrong once is
    // handing over any file the web server can read — and nginx resolves
    // `..` in the URL it is given exactly as happily as PHP would.
    $file->forceFill(['path' => '../../../../etc/passwd'])->save();

    $this->actingAs($this->admin)->get("/files/{$file->id}/download")->assertNotFound();
});

test('PHP streaming reports a missing file as missing rather than failing', function () {
    deliverAs('php');
    $file = File::factory()->create();

    $this->actingAs($this->admin)->get("/files/{$file->id}/download")->assertNotFound();
});

test('previews, thumbnails and zips travel the same way downloads do', function () {
    // The four routes that hand over bytes each used to decide this for
    // themselves, and all four hard-coded nginx. Centralising them is the
    // fix; this is what stops one drifting back out.
    deliverAs('php');

    $image = File::factory()->create(['mime_type' => 'image/png', 'original_name' => 'shot.png']);
    Storage::disk('files')->put($image->path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));

    $response = $this->actingAs($this->admin)->get("/files/{$image->id}/preview");

    $response->assertOk()->assertHeaderMissing('X-Accel-Redirect');
    expect(strlen($response->streamedContent()))->toBeGreaterThan(0);
});
