<?php

declare(strict_types=1);

use App\Modules\Platform\Branding\Models\BrandingSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(staffWithPermissions(['edit_settings']));
});

/** 200 × 100, red on the left half and blue on the right. */
function darkTestLogo(): UploadedFile
{
    $image = imagecreatetruecolor(200, 100);
    imagefilledrectangle($image, 0, 0, 99, 99, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, 100, 0, 199, 99, imagecolorallocate($image, 0, 0, 255));

    $temp = tempnam(sys_get_temp_dir(), 'logo');
    imagepng($image, $temp);

    return new UploadedFile($temp, 'logo.png', 'image/png', null, true);
}

function uploadBothLogos(): BrandingSetting
{
    test()->post(route('branding.store'), ['logo' => UploadedFile::fake()->image('light.png', 200, 80)])->assertRedirect();
    test()->post(route('branding.logo-dark.store'), ['logo_dark' => darkTestLogo()])->assertRedirect()->assertSessionHasNoErrors();

    return BrandingSetting::query()->sole();
}

test('a logo for dark mode is stored beside the main one and shared with every page', function () {
    $setting = uploadBothLogos();

    expect($setting->logo_dark_path)->not->toBeNull()
        ->and($setting->logo_dark_path)->not->toBe($setting->logo_path);
    Storage::disk('public')->assertExists($setting->logo_dark_path);

    $darkUrl = $setting->logoUrl(\App\Modules\Platform\Branding\LogoVariant::Dark);

    $this->get(route('branding.edit'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('logo_dark.url', $darkUrl)
        ->where('logo_dark.cropped', false));

    auth()->logout();

    $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('branding.logo_url', $setting->logoUrl())
        ->where('branding.logo_dark_url', $darkUrl));
});

test('a dark-mode logo needs a main logo first', function () {
    $this->post(route('branding.logo-dark.store'), ['logo_dark' => darkTestLogo()])
        ->assertSessionHasErrors('logo_dark');

    expect(BrandingSetting::query()->first()?->logo_dark_path)->toBeNull();
});

test('a dark-mode logo must be an image', function () {
    $this->post(route('branding.store'), ['logo' => UploadedFile::fake()->image('light.png')]);

    $this->post(route('branding.logo-dark.store'), [
        'logo_dark' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('logo_dark');
});

test('replacing the dark-mode logo deletes the previous one and leaves the main logo alone', function () {
    $first = uploadBothLogos();

    $this->post(route('branding.logo-dark.store'), ['logo_dark' => darkTestLogo()])->assertRedirect();

    $second = BrandingSetting::query()->sole();
    expect($second->logo_dark_path)->not->toBe($first->logo_dark_path)
        ->and($second->logo_path)->toBe($first->logo_path);
    Storage::disk('public')->assertMissing($first->logo_dark_path);
});

test('cropping the dark-mode logo keeps the right part and leaves the main logo alone', function () {
    $before = uploadBothLogos();

    $this->patch(route('branding.logo-dark.crop'), ['x' => 100, 'y' => 0, 'width' => 100, 'height' => 100])
        ->assertRedirect()->assertSessionHasNoErrors();

    $after = BrandingSetting::query()->sole();
    $image = imagecreatefromstring(Storage::disk('public')->get($after->logo_dark_path));
    $rgb = imagecolorsforindex($image, imagecolorat($image, 10, 10));

    expect(imagesx($image))->toBe(100)
        ->and([$rgb['red'], $rgb['green'], $rgb['blue']])->toBe([0, 0, 255])
        ->and($after->logo_dark_original_path)->toBe($before->logo_dark_path)
        ->and($after->logo_dark_crop)->toBe(['x' => 100, 'y' => 0, 'width' => 100, 'height' => 100])
        ->and($after->logo_path)->toBe($before->logo_path)
        ->and($after->logo_crop)->toBeNull();

    $this->delete(route('branding.logo-dark.restore'))->assertRedirect();

    $restored = BrandingSetting::query()->sole();
    expect($restored->logo_dark_path)->toBe($before->logo_dark_path)
        ->and($restored->logo_dark_original_path)->toBeNull();
    Storage::disk('public')->assertMissing($after->logo_dark_path);
});

test('removing the dark-mode logo keeps the main one', function () {
    $setting = uploadBothLogos();

    $this->delete(route('branding.logo-dark.destroy'))->assertRedirect();

    $after = BrandingSetting::query()->sole();
    expect($after->logo_dark_path)->toBeNull()
        ->and($after->logo_path)->toBe($setting->logo_path);
    Storage::disk('public')->assertMissing($setting->logo_dark_path);
    Storage::disk('public')->assertExists($setting->logo_path);
});

test('removing the main logo removes the dark-mode one with it', function () {
    $setting = uploadBothLogos();

    $this->delete(route('branding.destroy'))->assertRedirect();

    $after = BrandingSetting::query()->sole();
    expect($after->logo_path)->toBeNull()
        ->and($after->logo_dark_path)->toBeNull();
    Storage::disk('public')->assertMissing($setting->logo_dark_path);
});

test('the dark-mode logo goes away with the capability, like the main one', function () {
    uploadBothLogos();

    $shared = fn (): ?string => value(Inertia::getShared('branding'))['logo_dark_url'];
    expect($shared())->not->toBeNull();

    config(['projectsend.capabilities_disabled' => 'branding.customize']);
    forgetRequestState();

    expect($shared())->toBeNull();
});

test('the dark-mode routes 404 when the capability has been taken away', function () {
    // Before any request: the capability set is worked out once, on the
    // first one.
    config(['projectsend.capabilities_disabled' => 'branding.customize']);

    $this->post(route('branding.logo-dark.store'), ['logo_dark' => darkTestLogo()])->assertNotFound();
    $this->patch(route('branding.logo-dark.crop'), ['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10])->assertNotFound();
    $this->delete(route('branding.logo-dark.restore'))->assertNotFound();
    $this->delete(route('branding.logo-dark.destroy'))->assertNotFound();
});

test('the dark-mode routes need edit_settings', function () {
    $this->actingAs(staffWithPermissions([]));

    $this->post(route('branding.logo-dark.store'), ['logo_dark' => darkTestLogo()])->assertForbidden();
    $this->delete(route('branding.logo-dark.destroy'))->assertForbidden();
});

test('the API reports the dark-mode logo beside the main one', function () {
    $setting = uploadBothLogos();
    $staff = staffWithPermissions(['edit_settings']);
    $token = $staff->createToken('Integration', ['edit_settings'])->plainTextToken;
    auth()->logout();

    $this->withToken($token)->getJson('/api/v1/modules/branding/logo')
        ->assertOk()
        ->assertJsonPath('data.logo_dark_url', $setting->logoUrl(\App\Modules\Platform\Branding\LogoVariant::Dark));
});
