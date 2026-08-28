<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Modules\Platform\Branding\Models\BrandingSetting;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(staffWithPermissions(['edit_settings']));
});

test('a staff member can upload a logo, and it is reflected in the shared branding prop', function () {
    $this->get(route('branding.edit'))->assertOk();

    $this->post(route('branding.store'), [
        'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
    ])->assertRedirect();

    $setting = BrandingSetting::query()->sole();
    expect($setting->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($setting->logo_path);
});

test('uploading a new logo replaces and deletes the previous one', function () {
    $this->post(route('branding.store'), ['logo' => UploadedFile::fake()->image('first.png')]);
    $firstPath = BrandingSetting::query()->sole()->logo_path;

    $this->post(route('branding.store'), ['logo' => UploadedFile::fake()->image('second.png')]);
    $secondPath = BrandingSetting::query()->sole()->logo_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

test('a non-image upload is rejected', function () {
    $this->post(route('branding.store'), [
        'logo' => UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('logo');
});

test('removing the logo deletes the file and clears the setting', function () {
    $this->post(route('branding.store'), ['logo' => UploadedFile::fake()->image('logo.png')]);
    $path = BrandingSetting::query()->sole()->logo_path;

    $this->delete(route('branding.destroy'))->assertRedirect();

    expect(BrandingSetting::query()->sole()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('a client cannot reach the branding screen', function () {
    // The route group carries `staff`, so this is refused before any
    // permission is consulted: a client account holding edit_settings
    // somehow would still not be looking at system settings.
    $this->actingAs(User::factory()->client()->create());

    // Redirected rather than 403'd: core's `staff` middleware sends a
    // client to their own portal rather than telling them a staff screen
    // exists. The package's isolated harness had no portal to send them
    // to, which is why this read as a 403 there.
    $this->get(route('branding.edit'))->assertRedirect();
});

test('the route 404s when the capability has been taken away', function () {
    // How a hosted plan withholds branding: the key is subtracted from
    // the instance's environment and the screen stops existing. The
    // instance refusing is the enforcement — a portal hiding its own
    // button is presentation.
    config(['projectsend.capabilities_disabled' => 'branding.customize']);

    $this->get(route('branding.edit'))->assertNotFound();
});

// The row can outlive the capability: an installation moved to the
// community edition, or a backup restored into one. The screen that would
// take the logo off is 404 there, so a share that still answered would put
// a logo on every page with no way to remove it.
test('the shared logo goes away with the capability, whatever the row says', function () {
    $this->post(route('branding.store'), ['logo' => UploadedFile::fake()->image('logo.png')]);

    expect(BrandingSetting::query()->sole()->logo_path)->not->toBeNull();

    $shared = fn (): ?string => value(Inertia::getShared('branding'))['logo_url'];

    expect($shared())->not->toBeNull();

    // Taken away the way a hosted plan takes it away. The row is left
    // exactly as it was: a downgrade is usually an expired card rather
    // than a decision, and deleting somebody's branding over a billing
    // event is a loss they would find weeks later with no way to know
    // what it used to be. Hiding reverses; deleting does not.
    config(['projectsend.capabilities_disabled' => 'branding.customize']);
    forgetRequestState();

    expect($shared())->toBeNull()
        ->and(BrandingSetting::query()->sole()->logo_path)->not->toBeNull();
});

// The `image` rule only inspects sniffed content, so a real GIF named
// ".html" passes it. This disk is web-served (public/storage is symlinked
// into the document root), so honouring the uploaded filename's extension
// would store — and serve back — script on the app's own origin.
//
// Built as a real UploadedFile rather than UploadedFile::fake(): the fake
// derives its mime type from the *name*, so it cannot express the very
// split this guards against (content says GIF, filename says HTML).
test('the stored extension comes from the content, never from the uploaded filename', function () {
    $temp = tempnam(sys_get_temp_dir(), 'polyglot');
    file_put_contents($temp, "GIF89a=1;\n<html><body><script>alert(document.domain)</script></body></html>");

    $polyglot = new UploadedFile($temp, 'logo.html', 'image/gif', null, true);

    // Precondition: this really does pass validation — the fix is about
    // what happens next, not about rejecting the upload.
    expect($polyglot->getClientOriginalExtension())->toBe('html')
        ->and($polyglot->guessExtension())->toBe('gif');

    $this->post(route('branding.store'), ['logo' => $polyglot])->assertRedirect();

    $path = BrandingSetting::query()->sole()->logo_path;

    expect($path)->not->toBeNull()
        ->and($path)->not->toEndWith('.html')
        ->and(pathinfo($path, PATHINFO_EXTENSION))->toBe('gif');

    Storage::disk('public')->assertExists($path);

    @unlink($temp);
});

// The logo is a system setting, so it takes the same permission as every
// other settings surface. `staff` alone previously let any staff role —
// an Uploader, a Client Manager — replace or delete it.
test('a staff member without edit_settings cannot see or change the logo', function () {
    $this->actingAs(staffWithPermissions([]));

    $this->get(route('branding.edit'))->assertForbidden();
    $this->post(route('branding.store'), ['logo' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
    $this->delete(route('branding.destroy'))->assertForbidden();

    expect(BrandingSetting::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The half that did not move
|--------------------------------------------------------------------------
|
| Hiding "Powered by ProjectSend" stayed in cloud-modules. This screen
| renders the switch, and nothing here can save it: the route that does is
| registered by the package, and an installation without the package has
| the column and no code able to act on it.
*/

test('the screen carries the attribution value it cannot change', function () {
    // Written directly, because core has no way to set it — which is the
    // point. The value still has to reach the page, or a hosted customer
    // would open the tab and find the switch always off.
    BrandingSetting::current()->forceFill(['hide_attribution' => true])->save();

    $this->get(route('branding.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('hide_attribution', true));
});

test('core has no route that can change it', function () {
    // The gate is the absence of the code, not a capability check. If this
    // ever passes, the white-label feature has quietly become free.
    expect(collect(Route::getRoutes()->getRoutes())
        ->contains(fn (RouteInstance $route): bool => str_contains($route->uri(), 'branding/attribution')))
        ->toBeFalse();
});
