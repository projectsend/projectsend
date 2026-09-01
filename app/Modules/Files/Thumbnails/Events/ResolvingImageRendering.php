<?php

declare(strict_types=1);

namespace App\Modules\Files\Thumbnails\Events;

use App\Modules\Files\Thumbnails\ImageAudience;
use App\Modules\Files\Thumbnails\ImageRendition;

/**
 * "Does this viewer have to be served a rendering, or will the original
 * file do?" — asked once per preview request, before any decoding
 * happens.
 *
 * A thumbnail never asks: it is a rendering by definition, nothing else
 * would fit in a listing row. A preview is the case with two valid
 * answers. Serving the stored file is far cheaper — handed to the web
 * server with no PHP in the path at all where that is possible, or a
 * redirect straight to external storage — and it is what this app has
 * always done. Decoding and
 * re-encoding a full-size photograph instead is only worth it when
 * something actually intends to change what the viewer sees.
 *
 * So the default is false and core keeps its fast path; a listener that
 * intends to decorate this particular rendering for this particular
 * audience says so, and only then does core render. In practice that
 * means a client's preview is a rendered, watermarked copy on an
 * installation that watermarks, and the original bytes everywhere else,
 * with no cost paid by installations that do not.
 *
 * Listened to by *string* class name from a package, same as every other
 * hook here — see docs/extension-points-architecture.md.
 */
final class ResolvingImageRendering
{
    /**
     * Set true by any listener that will alter this rendering. Never set
     * back to false: one listener declining is not the others' answer,
     * so this only ever moves in one direction.
     */
    public bool $required = false;

    public function __construct(
        public readonly ImageAudience $audience,
        public readonly ImageRendition $rendition,
        public readonly string $mimeType,
    ) {}
}
