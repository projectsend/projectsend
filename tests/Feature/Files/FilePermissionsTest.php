<?php

declare(strict_types=1);

// What the `files` disk writes to disk, in modes rather than in config keys.
//
// A download is not served by PHP: PHP authorizes it and hands the web server
// the path with X-Accel-Redirect. So on a host where those are different
// users the mode on a directory decides whether downloads work at all, and
// nothing else on the site notices (#1668).
//
// The umask cases are the point of this file. `visibility` makes Flysystem
// chmod each file after writing it, so it holds regardless; a directory is
// created by mkdir(), which masks its mode argument, so the same setting is a
// ceiling there rather than a guarantee. That asymmetry is invisible from the
// configuration and is exactly what someone would "simplify" away.

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Points the `files` disk at a scratch root, configured the way
 * config/filesystems.php configures it for the given flag.
 */
function filesDiskWith(bool $webServerReadable): string
{
    $root = storage_path('app/files-permission-test');

    config(['filesystems.disks.files' => [
        'driver' => 'local',
        'root' => $root,
        'serve' => false,
        'throw' => false,
        ...($webServerReadable
            ? ['visibility' => 'public', 'permissions' => ['dir' => ['private' => 0755]]]
            : []),
    ]]);

    Storage::forgetDisk('files');

    return $root;
}

function modeOf(string $path): string
{
    clearstatcache(true, $path);

    return substr(sprintf('%o', fileperms($path)), -4);
}

beforeEach(function () {
    $this->originalUmask = umask();
});

afterEach(function () {
    umask($this->originalUmask);
    File::deleteDirectory(storage_path('app/files-permission-test'));
    Storage::forgetDisk('files');
});

test('by default an upload lands in a directory only its owner can traverse', function () {
    umask(0022);
    $root = filesDiskWith(webServerReadable: false);

    Storage::disk('files')->put('2026/08/report.pdf', 'contents');

    // 0700: a web server running as another user cannot open anything
    // underneath this, whatever the file's own mode says.
    expect(modeOf($root.'/2026/08'))->toBe('0700');
});

test('the flag opens both the file and the directory for another user', function () {
    umask(0022);
    $root = filesDiskWith(webServerReadable: true);

    Storage::disk('files')->put('2026/08/report.pdf', 'contents');

    expect(modeOf($root.'/2026/08/report.pdf'))->toBe('0644')
        ->and(modeOf($root.'/2026/08'))->toBe('0755');
});

// Both halves of the same claim, on a pool that denies group and other by
// default. The file still comes out readable because it is chmod'ed after the
// write; the directory does not, because mkdir() masked it — so the flag alone
// does not rescue a host like this and INSTALL.md has to say so.
test('a restrictive umask still caps the directory, though not the file', function () {
    umask(0077);
    $root = filesDiskWith(webServerReadable: true);

    Storage::disk('files')->put('2026/08/report.pdf', 'contents');

    expect(modeOf($root.'/2026/08/report.pdf'))->toBe('0644')
        ->and(modeOf($root.'/2026/08'))->toBe('0700');
});
