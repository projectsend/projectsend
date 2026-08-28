<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use App\Modules\Files\Thumbnails\Events\ImageRenderingChanged;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use App\Modules\Platform\Branding\Models\BrandingSetting;
use App\Modules\Platform\Branding\Watermark\WatermarkPosition;
use App\Modules\Platform\Branding\Watermark\WatermarkSample;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Two site-wide pieces of artwork: the logo shown in the sidebar in
 * place of the default icon, and the mark stamped onto the thumbnails
 * and previews clients and public visitors see. Every route here is gated end-to-end by the host's
 * `capability:branding.customize` middleware (see routes.php) — this
 * module has no idea what edition it's running in, it just trusts the
 * gate.
 */
class BrandingController extends Controller
{
    public function edit(): Response
    {
        // An unsaved instance rather than `current()`: rendering a settings
        // screen must not write a row, and the model carries the same
        // defaults the table does (see its $attributes) so the form starts
        // on the values a first save would produce.
        $setting = BrandingSetting::query()->first() ?? new BrandingSetting;

        return Inertia::render('branding/edit', [
            'logo_url' => $setting->logoUrl(),
            // Read, never written here. Hiding attribution is the
            // white-label half and stays a hosted feature: the switch is
            // rendered only where Capability::AttributionHide is held, and
            // the route that saves it is registered by cloud-modules. Core
            // carries the column because it owns the table, and carries no
            // way to set it.
            'hide_attribution' => $setting->hide_attribution,
            'watermark' => [
                'enabled' => $setting->watermark_enabled,
                'image_url' => $setting->watermarkUrl(),
                'position' => $setting->watermark_position->value,
                'size' => $setting->watermark_size,
                'opacity' => $setting->watermark_opacity,
            ],
            'watermark_positions' => WatermarkPosition::values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ]);

        /** @var UploadedFile $upload */
        $upload = $validated['logo'];

        $setting = BrandingSetting::current();

        if ($setting->logo_path !== null) {
            Storage::disk('public')->delete($setting->logo_path);
        }

        $setting->update(['logo_path' => $this->storeImage($upload)]);

        return back()->with('success', __('Logo updated.'));
    }

    public function destroy(): RedirectResponse
    {
        $setting = BrandingSetting::query()->first();

        if ($setting?->logo_path !== null) {
            Storage::disk('public')->delete($setting->logo_path);
            $setting->update(['logo_path' => null]);
        }

        return back()->with('success', __('Logo removed.'));
    }

        /**
     * Save the whole watermark form at once — toggle, artwork, placement,
     * scale and opacity. One endpoint rather than one per field because
     * they are only meaningful together: turning it on without an image,
     * or changing the size without seeing the position, are not states
     * worth being able to save.
     */
    public function updateWatermark(Request $request): RedirectResponse
    {
        $setting = BrandingSetting::current();

        $validated = $request->validate([
            // The image is optional on every save *except* the one that
            // turns watermarking on with nothing stored yet — otherwise
            // adjusting the opacity would mean re-picking the file each
            // time. `exclude_if` keeps the rule off the payload entirely
            // rather than requiring a re-upload.
            'image' => [
                $setting->watermark_path === null && $request->boolean('enabled') ? 'required' : 'nullable',
                'image',
                'max:2048',
            ],
            'enabled' => ['required', 'boolean'],
            'position' => ['required', Rule::in(WatermarkPosition::values())],
            'size' => ['required', 'integer', 'min:5', 'max:100'],
            'opacity' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'image.required' => __('Choose the image to use as the watermark.'),
        ]);

        $attributes = [
            'watermark_enabled' => (bool) $validated['enabled'],
            'watermark_position' => $validated['position'],
            'watermark_size' => (int) $validated['size'],
            'watermark_opacity' => (int) $validated['opacity'],
        ];

        if (($validated['image'] ?? null) instanceof UploadedFile) {
            if ($setting->watermark_path !== null) {
                Storage::disk('public')->delete($setting->watermark_path);
            }

            $attributes['watermark_path'] = $this->storeImage($validated['image']);
        }

        $setting->update($attributes);

        $this->forgetRenderedImages();

        return back()->with('success', __('Watermark settings saved.'));
    }

    /**
     * A stand-in photograph with the mark drawn on it, so the settings
     * screen can show what a client will actually see. Staff surfaces are
     * never watermarked, so without this an administrator has no way to
     * judge their own settings short of signing in as a client.
     *
     * Takes placement, scale and opacity from the *query string* rather
     * than from the saved row: the point is to answer "what would this
     * look like" while the form is still being adjusted. The artwork
     * itself has to be the stored one — an unsaved file lives in the
     * browser, not on this server — which is why the screen tells you to
     * save after choosing a new image.
     *
     * Drawn by the same WatermarkPainter that renders the real thing, so
     * the sample cannot flatter the settings.
     */
    public function watermarkSample(Request $request, WatermarkSample $sample): SymfonyResponse
    {
        $setting = BrandingSetting::query()->first();
        $markPath = $setting?->watermark_path;

        // Not 404 for "you have not uploaded one yet" — the screen asks for
        // this image before there is anything to draw, and a broken <img>
        // is a worse answer than none. It hides the sample instead.
        abort_if($markPath === null || ! Storage::disk('public')->exists($markPath), 404);

        $validated = $request->validate([
            'position' => ['required', Rule::in(WatermarkPosition::values())],
            'size' => ['required', 'integer', 'min:5', 'max:100'],
            'opacity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $image = $sample->render(
            Storage::disk('public')->path($markPath),
            WatermarkPosition::from($validated['position']),
            (int) $validated['size'],
            (int) $validated['opacity'],
        );

        return new SymfonyResponse($image->toString('image/png'), 200, [
            'Content-Type' => 'image/png',
            // Every request has different parameters and the artwork behind
            // it can be replaced at any moment; a cached sample would show
            // an administrator the settings they had a minute ago.
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /**
     * Drop the artwork and switch watermarking off with it — an "enabled"
     * that no image backs is not a state this screen can leave behind.
     */
    public function destroyWatermark(): RedirectResponse
    {
        $setting = BrandingSetting::query()->first();

        if ($setting === null) {
            return back();
        }

        if ($setting->watermark_path !== null) {
            Storage::disk('public')->delete($setting->watermark_path);
        }

        $setting->update([
            'watermark_path' => null,
            'watermark_enabled' => false,
        ]);

        $this->forgetRenderedImages();

        return back()->with('success', __('Watermark removed.'));
    }

    /**
     * The host caches every image it renders and never revisits it, so
     * without this a settings change would only reach files nobody has
     * looked at yet.
     *
     * Dispatched by *string* class name: the host's event class cannot be
     * constructed from here (this package builds with no host present),
     * and it carries no payload precisely so that it doesn't have to be.
     * With no host listening this is an inert no-op.
     */
    private function forgetRenderedImages(): void
    {
        Event::dispatch(new ImageRenderingChanged);
    }

    /**
     * The extension comes from the *content*, never from the uploaded
     * filename. This disk is web-served (public/storage is symlinked into
     * the document root and nginx serves it as a static file), and the
     * `image` rule only inspects the sniffed content — so a GIF whose
     * filename says ".html" passes validation and would then be stored,
     * and served back, as text/html: stored XSS on this app's own origin,
     * from any account that can reach this page. guessExtension() is
     * derived from the same sniffed mime type the validator just
     * accepted, so the two can no longer disagree.
     */
    private function storeImage(UploadedFile $upload): string
    {
        $extension = $upload->guessExtension() ?? 'bin';

        $path = $upload->storeAs('branding', Str::uuid().'.'.$extension, 'public');

        if ($path === false) {
            throw new RuntimeException('Could not store the uploaded image.');
        }

        return $path;
    }
}
