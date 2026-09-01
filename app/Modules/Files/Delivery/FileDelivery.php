<?php

declare(strict_types=1);

namespace App\Modules\Files\Delivery;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Puts a file that lives on this server's local disk on the wire.
 *
 * The single place that knows how the bytes travel. Four routes used to
 * decide that for themselves and all four hard-coded nginx's header, so
 * an Apache or LiteSpeed installation served four different flavours of
 * empty response — uploads worked, thumbnails were broken images, and
 * downloads arrived as 0 bytes. Callers now say *what* to send and this
 * decides *how*.
 *
 * It authorizes nothing. Every caller has already done that its own way
 * — a policy, a share token, a public-listing check — and the path it
 * passes is always derived from a row it just authorized, never from the
 * request. That is load-bearing: `serve()` will send any file under the
 * storage root, so a caller that passed user input would have built a
 * file-disclosure bug. The root check below is the backstop, not the
 * rule.
 *
 * ### Choosing the method
 *
 * `PROJECTSEND_FILE_DELIVERY` picks one explicitly. Left at `auto` — the
 * default — nginx gets its own fast path and everything else gets PHP
 * streaming.
 *
 * Auto deliberately never chooses `xsendfile`. Apache's `mod_xsendfile`
 * needs `XSendFilePath` to whitelist the storage directory as well as
 * being loaded, and nothing here can see whether it does; choosing it
 * because the module is present would swap a silent failure anybody can
 * diagnose from the dashboard for one nobody can. So it stays something
 * an operator turns on having configured it.
 *
 * A value that is not a method falls back to auto rather than throwing.
 * A typo in an environment variable should cost speed, not every
 * download on the installation.
 */
class FileDelivery
{
    /**
     * The disk uploads live on. Named rather than injected because the
     * whole class is about the local-disk case: a file on S3 never
     * reaches here, it is a signed redirect from StoredFileResponse.
     */
    private const DISK = 'files';

    /** The internal nginx location that maps back onto the storage root. */
    private const NGINX_LOCATION = '/protected-files/';

    public function __construct(private readonly Request $request) {}

    /**
     * The method in force, and whether it was detected or stated.
     *
     * @return array{method: DeliveryMethod, detected: bool}
     */
    public function resolve(): array
    {
        $configured = config('projectsend.file_delivery');
        $explicit = is_string($configured) ? DeliveryMethod::tryFrom($configured) : null;

        if ($explicit !== null) {
            return ['method' => $explicit, 'detected' => false];
        }

        return ['method' => $this->detect(), 'detected' => true];
    }

    public function method(): DeliveryMethod
    {
        return $this->resolve()['method'];
    }

    /**
     * The same answer as a plain array, for a screen or a probe.
     *
     * Spelled out rather than leaning on a backed enum encoding itself,
     * because this shape is read by the dashboard and by whatever watches
     * the installation from outside, and neither should change meaning if
     * the enum ever grows a JsonSerializable of its own.
     *
     * @return array{method: string, detected: bool}
     */
    public function describe(): array
    {
        $resolved = $this->resolve();

        return [
            'method' => $resolved['method']->value,
            // True when nobody said which to use. The distinction matters
            // to the reader: a detected `php` is an installation that
            // could be faster, a stated one is somebody's decision.
            'detected' => $resolved['detected'],
        ];
    }

    /**
     * What the server says it is.
     *
     * `SERVER_SOFTWARE` is set by the web server itself through the
     * FastCGI parameters, so it describes the process actually holding
     * the connection to PHP. That is the right thing to ask: the header
     * has to be understood by *that* server, not by whatever sits in
     * front of it.
     *
     * The known-wrong case is nginx reverse-proxying Apache, which
     * INSTALL.md offers as a way to keep an existing Apache. This reads
     * Apache and picks PHP streaming, so downloads work and are slower
     * than they need to be — the safe direction, and the reason the
     * override exists.
     */
    private function detect(): DeliveryMethod
    {
        $software = $this->request->server('SERVER_SOFTWARE');
        $software = strtolower(is_string($software) ? $software : '');

        return str_contains($software, 'nginx') ? DeliveryMethod::Nginx : DeliveryMethod::Php;
    }

    /**
     * @param  string  $path  disk-relative, and always derived from an
     *                        already-authorized row — never from the request
     * @param  int|null  $length  when the caller already knows it; PHP
     *                            streaming ignores it and measures the file
     */
    public function serve(string $path, string $mimeType, string $disposition, ?int $length = null): Response|BinaryFileResponse
    {
        $this->assertRelative($path);

        $headers = array_filter([
            'Content-Type' => $mimeType,
            'Content-Disposition' => $disposition,
            'Content-Length' => $length === null ? null : (string) $length,
        ], static fn (?string $value): bool => $value !== null);

        return match ($this->method()) {
            DeliveryMethod::Nginx => response('', 200, [
                'X-Accel-Redirect' => self::NGINX_LOCATION.$path,
                ...$headers,
            ]),
            DeliveryMethod::XSendFile => response('', 200, [
                // An absolute filesystem path, unlike nginx's URL path.
                // Renaming the header without changing the value is the
                // obvious way to "add Apache support" and produces a
                // second broken install.
                'X-Sendfile' => $this->absolutePathWithin($path),
                ...$headers,
            ]),
            DeliveryMethod::Php => $this->stream($this->absolutePathWithin($path), $headers),
        };
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function stream(string $absolute, array $headers): BinaryFileResponse
    {
        // A large download can outlive max_execution_time, and the visitor
        // sees a truncated file rather than an error. The web server is
        // not holding this one open for us.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        // BinaryFileResponse rather than a hand-written readfile loop: it
        // answers Range requests, which is what makes seeking through a
        // long video work. nginx does that for itself on the fast path, so
        // rolling our own here would break preview scrubbing on exactly
        // the installations this fallback exists for.
        //
        // Content-Length is deliberately dropped from the headers: the
        // response sets its own from the file, and a caller's figure that
        // disagrees — a stale `files.size`, or a range being served —
        // truncates the download.
        unset($headers['Content-Length']);

        return new BinaryFileResponse($absolute, 200, $headers);
    }

    /**
     * The path must stay a path *inside* the storage area.
     *
     * Checked for every method, and without touching the filesystem,
     * because nginx resolves `..` in the URL it is handed just as
     * happily as a filesystem call would. Callers pass paths from rows
     * they authorized rather than from the request, so this is a
     * backstop; it is here because the cost of being wrong about that,
     * once, is handing over any file the web server can read.
     */
    private function assertRelative(string $path): void
    {
        abort_if(
            $path === ''
                || str_starts_with($path, '/')
                || preg_match('#(^|/)\.\.(/|$)#', $path) === 1,
            404,
        );
    }

    /**
     * The absolute path, proven to resolve inside the storage root.
     *
     * Only the two methods that hand over a *filesystem* path need this,
     * and only they can afford it: it resolves symlinks, so it answers
     * the question `assertRelative()` cannot — whether the file is really
     * where the path says it is.
     *
     * It also requires the file to exist, which is why nginx does not go
     * through it. On that path PHP never opens the file, and adding a
     * stat to every download to discover something nginx is about to
     * discover anyway would be a cost with no answer attached.
     */
    private function absolutePathWithin(string $path): string
    {
        $disk = Storage::disk(self::DISK);

        $absolute = realpath($disk->path($path));
        $root = realpath($disk->path(''));

        abort_if(
            $absolute === false || $root === false || ! str_starts_with($absolute, rtrim($root, '/').'/'),
            404,
        );

        return $absolute;
    }
}
