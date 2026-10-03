<?php

declare(strict_types=1);

use App\Modules\Platform\Branding\Models\BrandingSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(staffWithPermissions(['edit_settings']));
});

/**
 * A 200 × 100 image, red on the left half and blue on the right, so a test
 * can tell which part of it a crop actually kept.
 */
function twoColourLogo(string $format = 'png'): UploadedFile
{
    $image = imagecreatetruecolor(200, 100);
    imagefilledrectangle($image, 0, 0, 99, 99, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, 100, 0, 199, 99, imagecolorallocate($image, 0, 0, 255));

    $temp = tempnam(sys_get_temp_dir(), 'logo');
    $format === 'jpg' ? imagejpeg($image, $temp, 100) : imagepng($image, $temp);

    return new UploadedFile($temp, "logo.{$format}", $format === 'jpg' ? 'image/jpeg' : 'image/png', null, true);
}

/** The colour of one pixel of a stored logo, as [r, g, b]. */
function logoPixel(string $path, int $x, int $y): array
{
    $image = imagecreatefromstring(Storage::disk('public')->get($path));
    $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));

    return [$rgb['red'], $rgb['green'], $rgb['blue']];
}

function uploadTwoColourLogo(string $format = 'png'): string
{
    test()->post(route('branding.store'), ['logo' => twoColourLogo($format)])->assertRedirect();

    return BrandingSetting::query()->sole()->logo_path;
}

test('an uploaded logo is used whole until somebody crops it', function () {
    $upload = uploadTwoColourLogo();

    $setting = BrandingSetting::query()->sole();

    expect($setting->logo_original_path)->toBeNull()
        ->and($setting->logo_crop)->toBeNull()
        ->and($setting->logoSourcePath())->toBe($upload);
});

test('cropping keeps the part of the picture the box was drawn on', function () {
    $upload = uploadTwoColourLogo();

    $this->patch(route('branding.logo.crop'), ['x' => 100, 'y' => 0, 'width' => 100, 'height' => 100])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $setting = BrandingSetting::query()->sole();
    $size = getimagesizefromstring(Storage::disk('public')->get($setting->logo_path));

    expect($setting->logo_path)->not->toBe($upload)
        ->and($setting->logo_original_path)->toBe($upload)
        ->and($setting->logo_crop)->toBe(['x' => 100, 'y' => 0, 'width' => 100, 'height' => 100])
        ->and([$size[0], $size[1]])->toBe([100, 100])
        ->and(logoPixel($setting->logo_path, 5, 50))->toBe([0, 0, 255])
        ->and(logoPixel($setting->logo_path, 95, 50))->toBe([0, 0, 255]);

    // The upload itself is untouched.
    Storage::disk('public')->assertExists($upload);
    expect(logoPixel($upload, 5, 50))->toBe([255, 0, 0]);
});

test('cropping again starts from the upload, not from the last crop', function () {
    $upload = uploadTwoColourLogo();

    $this->patch(route('branding.logo.crop'), ['x' => 100, 'y' => 0, 'width' => 100, 'height' => 100]);
    $firstCrop = BrandingSetting::query()->sole()->logo_path;

    // The red half is not in the first crop at all; it is in the upload.
    $this->patch(route('branding.logo.crop'), ['x' => 0, 'y' => 0, 'width' => 50, 'height' => 50])->assertSessionHasNoErrors();

    $setting = BrandingSetting::query()->sole();

    expect($setting->logo_original_path)->toBe($upload)
        ->and(logoPixel($setting->logo_path, 10, 10))->toBe([255, 0, 0]);

    Storage::disk('public')->assertMissing($firstCrop);
});

test('restoring puts the upload back and deletes the crop', function () {
    $upload = uploadTwoColourLogo();
    $this->patch(route('branding.logo.crop'), ['x' => 100, 'y' => 0, 'width' => 100, 'height' => 100]);
    $crop = BrandingSetting::query()->sole()->logo_path;

    $this->delete(route('branding.logo.restore'))->assertRedirect();

    $setting = BrandingSetting::query()->sole();

    expect($setting->logo_path)->toBe($upload)
        ->and($setting->logo_original_path)->toBeNull()
        ->and($setting->logo_crop)->toBeNull();

    Storage::disk('public')->assertMissing($crop);
    Storage::disk('public')->assertExists($upload);
});

test('a box covering the whole picture is the same as restoring', function () {
    $upload = uploadTwoColourLogo();
    $this->patch(route('branding.logo.crop'), ['x' => 100, 'y' => 0, 'width' => 100, 'height' => 100]);

    $this->patch(route('branding.logo.crop'), ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 100])->assertSessionHasNoErrors();

    expect(BrandingSetting::query()->sole()->logo_path)->toBe($upload)
        ->and(Storage::disk('public')->allFiles('branding'))->toBe([$upload]);
});

test('a box reaching outside the picture is refused and changes nothing', function () {
    $upload = uploadTwoColourLogo();

    $this->patch(route('branding.logo.crop'), ['x' => 150, 'y' => 0, 'width' => 100, 'height' => 100])
        ->assertSessionHasErrors('width');

    $this->patch(route('branding.logo.crop'), ['x' => -1, 'y' => 0, 'width' => 10, 'height' => 10])
        ->assertSessionHasErrors('x');

    expect(BrandingSetting::query()->sole()->logo_path)->toBe($upload)
        ->and(Storage::disk('public')->allFiles('branding'))->toBe([$upload]);
});

test('there is nothing to crop before a logo is uploaded', function () {
    $this->patch(route('branding.logo.crop'), ['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10])
        ->assertSessionHasErrors('logo');
});

test('a crop keeps the format of the upload', function () {
    uploadTwoColourLogo('jpg');

    $this->patch(route('branding.logo.crop'), ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100])->assertSessionHasNoErrors();

    $path = BrandingSetting::query()->sole()->logo_path;

    expect(pathinfo($path, PATHINFO_EXTENSION))->toBe('jpg')
        ->and(getimagesizefromstring(Storage::disk('public')->get($path))['mime'])->toBe('image/jpeg');
});

/*
 * A small file can declare a huge picture. The size is read from the header
 * and refused before GD is asked to hold the pixels.
 */
test('an image too large to hold in memory is refused before it is decoded', function () {
    $ihdr = pack('NNCCCCC', 6000, 5000, 8, 2, 0, 0, 0);
    $png = "\x89PNG\r\n\x1a\n"
        .pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr))
        .pack('N', 0).'IEND'.pack('N', crc32('IEND'));

    Storage::disk('public')->put('branding/huge.png', $png);
    BrandingSetting::current()->update(['logo_path' => 'branding/huge.png']);

    $this->patch(route('branding.logo.crop'), ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100])
        ->assertSessionHasErrors('logo');

    expect(BrandingSetting::query()->sole()->logo_original_path)->toBeNull();
});

test('a new upload after a crop deletes both files and starts uncropped', function () {
    $upload = uploadTwoColourLogo();
    $this->patch(route('branding.logo.crop'), ['x' => 100, 'y' => 0, 'width' => 100, 'height' => 100]);
    $crop = BrandingSetting::query()->sole()->logo_path;

    $this->post(route('branding.store'), ['logo' => UploadedFile::fake()->image('new.png', 80, 80)]);

    $setting = BrandingSetting::query()->sole();

    expect($setting->logo_original_path)->toBeNull()
        ->and($setting->logo_crop)->toBeNull()
        ->and(Storage::disk('public')->allFiles('branding'))->toBe([$setting->logo_path]);

    Storage::disk('public')->assertMissing($upload);
    Storage::disk('public')->assertMissing($crop);
});

test('removing a cropped logo deletes both files', function () {
    uploadTwoColourLogo();
    $this->patch(route('branding.logo.crop'), ['x' => 100, 'y' => 0, 'width' => 100, 'height' => 100]);

    $this->delete(route('branding.destroy'))->assertRedirect();

    $setting = BrandingSetting::query()->sole();

    expect($setting->logo_path)->toBeNull()
        ->and($setting->logo_original_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('branding'))->toBe([]);
});

test('the screen opens the cropper on the upload, with the last box drawn', function () {
    $upload = uploadTwoColourLogo();
    $this->patch(route('branding.logo.crop'), ['x' => 100, 'y' => 0, 'width' => 100, 'height' => 100]);

    $this->get(route('branding.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('logo_source_url', Storage::disk('public')->url($upload))
            ->where('logo_crop', ['x' => 100, 'y' => 0, 'width' => 100, 'height' => 100])
            ->where('logo_cropped', true));
});

test('cropping and restoring need what the rest of the screen needs', function () {
    uploadTwoColourLogo();

    $this->actingAs(staffWithPermissions([]));
    $this->patch(route('branding.logo.crop'), ['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10])->assertForbidden();
    $this->delete(route('branding.logo.restore'))->assertForbidden();

    expect(BrandingSetting::query()->sole()->logo_original_path)->toBeNull();
});

test('cropping and restoring 404 when the capability has been taken away', function () {
    // Before any request: the capability set is worked out once, on the
    // first one, as the other branding tests rely on.
    config(['projectsend.capabilities_disabled' => 'branding.customize']);
    Storage::disk('public')->put('branding/logo.png', twoColourLogo()->getContent());
    BrandingSetting::current()->update(['logo_path' => 'branding/logo.png']);

    $this->patch(route('branding.logo.crop'), ['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10])->assertNotFound();
    $this->delete(route('branding.logo.restore'))->assertNotFound();

    expect(BrandingSetting::query()->sole()->logo_original_path)->toBeNull();
});
