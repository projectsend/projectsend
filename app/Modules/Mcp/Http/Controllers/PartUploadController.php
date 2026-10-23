<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Http\Controllers;

use App\Modules\Files\Http\Controllers\ChunkedUploadsController;
use App\Modules\Files\Uploads\UploadSession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where an assistant PUTs the bytes of a part: a signed URL with no
 * session and no token behind it.
 *
 * The app has this exact endpoint twice already — once behind the web
 * session, once behind the API's staff token — and both are the wrong
 * door for an assistant, which holds neither: `create_upload` told it to
 * PUT with no Authorization header, because a signed URL *is* the
 * credential, the same presigned semantics the app's own upload page
 * runs on. So this is a third door onto the same controller method, and
 * the only thing it adds is the answer to "who is uploading": the owner
 * of the session named in the URL, which the signature proves we
 * issued.
 *
 * Everything else — the part number bounds, the per-part size cap, the
 * session's own ceiling, the write itself — is the app's `putPart`,
 * called as is. Nothing here is a second copy of that.
 */
class PartUploadController
{
    public function __invoke(Request $request, string $session, int $part): Response
    {
        $controller = app(ChunkedUploadsController::class);
        $model = UploadSession::query()->whereKey($session)->first();

        if ($model === null) {
            abort(404);
        }

        $owner = $model->user;

        // The person the session belongs to, for the ownership check the
        // store makes before it writes a byte.
        $request->setUserResolver(static fn () => $owner);

        return $controller->putPart($request, $model, $part);
    }
}
