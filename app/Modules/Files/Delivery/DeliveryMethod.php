<?php

declare(strict_types=1);

namespace App\Modules\Files\Delivery;

/**
 * How a file's bytes get from this server's disk to the visitor.
 *
 * Uploads live outside the web root, so every download passes through a
 * permission check in PHP first. What differs is what happens after that
 * check passes: PHP can read the file and write it out itself, or it can
 * answer with an empty body and a header telling the web server to send
 * the file instead.
 *
 * The header is the fast path and it is not portable — each server reads
 * a different one, and a server reading none of them serves the empty
 * body, which is how an installation ends up handing out 0-byte
 * downloads while every other page works. ProjectSend v1 had this as a
 * four-way setting with PHP as the default; v2 hard-coded nginx's
 * spelling for its first releases, which is
 * https://github.com/projectsend/projectsend/issues/1765.
 */
enum DeliveryMethod: string
{
    /**
     * nginx: `X-Accel-Redirect`, carrying a *URL path* that the
     * `location /protected-files/` block maps back onto the storage
     * directory. That block is marked `internal`, which is what stops a
     * visitor requesting the path directly.
     */
    case Nginx = 'nginx';

    /**
     * Apache with `mod_xsendfile`, and LiteSpeed, which reads the same
     * header: `X-Sendfile`, carrying an *absolute filesystem path*.
     *
     * Never chosen automatically. The module also needs `XSendFilePath`
     * to whitelist the storage directory, and there is no way to detect
     * that from here — picking this on the strength of the module being
     * loaded would trade one silent failure for another.
     */
    case XSendFile = 'xsendfile';

    /**
     * PHP reads the file and streams it.
     *
     * Works on every server, and costs a worker process for the duration
     * of each download — a handful of large concurrent downloads can
     * occupy every worker while the CPU sits idle. That is why it is the
     * fallback rather than the default, and why an installation using it
     * says so on the dashboard rather than being quietly slow.
     */
    case Php = 'php';
}
