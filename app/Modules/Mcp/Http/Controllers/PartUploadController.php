<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Http\Controllers;

use App\Modules\Files\Http\Controllers\ChunkedUploadsController;
use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Mcp\Http\Middleware\EnsureConnectorCapability;
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

        // A signed URL lives half an hour. What /mcp asks on every call is
        // asked here too, of the session's owner, so the URL stops working
        // the moment the connector is switched off, the account is
        // deactivated, or its role loses upload — as the web's part route
        // asks `can:upload`. Before this, the bytes kept landing in
        // staging until the URL expired.
        if (! EnsureConnectorCapability::granted()) {
            abort(404);
        }

        if ($owner === null || ! $owner->maySignIn() || ! $owner->can('upload')) {
            abort(403);
        }

        // The person the session belongs to, for the ownership check the
        // store makes before it writes a byte.
        $request->setUserResolver(static fn () => $owner);

        return $controller->putPart($request, $model, $part);
    }
}
