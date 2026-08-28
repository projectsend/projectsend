<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Modules\Platform\Branding\Http\Controllers\Api\BrandingController;

/*
|--------------------------------------------------------------------------
| Branding — module API routes
|--------------------------------------------------------------------------
|
| Mounted by the host at /api/v1/modules/branding, inside the API's auth
| stack (bearer token, active staff account) and behind
| `capability:branding.customize`. None of that is restated here — the host
| applies it, which is why these are plain relative paths.
|
| `token-can:` names a permission from the *host's* vocabulary. A module
| cannot invent ability strings: they have to reach the token-issuance UI
| and the reserved-namespace invariant, so they belong in the host's
| Permission enum. `edit_settings` is the same key the web branding routes
| use, so the API boundary mirrors the web one rather than inventing a
| second answer to "who may see the logo".
|
*/

Route::get('logo', [BrandingController::class, 'show'])
    ->middleware('token-can:edit_settings')
    ->name('logo.show');

Route::get('watermark', [BrandingController::class, 'watermark'])
    ->middleware('token-can:edit_settings')
    ->name('watermark.show');
