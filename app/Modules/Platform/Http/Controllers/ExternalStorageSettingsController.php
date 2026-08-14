<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\ExternalStorageSettings;
use Aws\S3\S3Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Community-only (Capability::StorageConfigure, enforced entirely via the
 * `capability:storage.configure` route middleware — every field on this
 * page is gated, unlike email settings' sender-identity fields, so there's
 * no field-level split to do inside the controller). Cloud never reaches
 * this controller at all.
 */
class ExternalStorageSettingsController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly ExternalStorageConfigApplier $configApplier,
    ) {}

    public function edit(Request $request): Response
    {
        $settings = ExternalStorageSettings::current();

        return Inertia::render('system/settings/storage', [
            'active' => $settings->active,
            // Never name a top-level Inertia prop "key" — Inertia's React
            // renderer spreads page props onto the component via
            // `{ key: <internal-remount-key>, ...props }`, and a prop
            // literally named "key" in that spread silently overrides
            // React's own reconciliation key, so the value never reaches
            // the component as an actual prop (React always strips `key`).
            'access_key' => $settings->key ?? '',
            'has_secret' => $settings->secret !== null && $settings->secret !== '',
            'bucket' => $settings->bucket ?? '',
            'region' => $settings->region ?? '',
            'endpoint' => $settings->endpoint ?? '',
            'use_path_style' => $settings->use_path_style,
            'root' => $settings->root ?? '',
            'test_result' => $request->session()->get('storage_test_result'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
            'access_key' => ['required', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'bucket' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:255'],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'use_path_style' => ['required', 'boolean'],
            'root' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = ExternalStorageSettings::current();

        $settings->fill([
            'active' => $validated['active'],
            'key' => $validated['access_key'],
            'bucket' => $validated['bucket'],
            'region' => $validated['region'],
            'endpoint' => $validated['endpoint'] ?? null,
            'use_path_style' => $validated['use_path_style'],
            'root' => $validated['root'] ?? null,
        ]);

        // A blank secret keeps whatever is already stored — the field is
        // never round-tripped to the browser (only `has_secret` is).
        if (is_string($validated['secret'] ?? null) && $validated['secret'] !== '') {
            $settings->secret = $validated['secret'];
        }

        $settings->save();

        $this->configApplier->flush();
        $this->configApplier->apply();

        // The long-running queue worker cached the old config at boot;
        // BuildZipDownloadJob and future uploads need this to pick up the
        // new backend without a manual container restart.
        Artisan::call('queue:restart');

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'storage']);

        return back()->with('success', __('Storage settings saved.'));
    }

    /**
     * Verifies the submitted (or, if the secret field was left blank, the
     * already-stored) credentials can actually reach the bucket, mirroring
     * v1's connection test — this exists specifically to catch a typo'd
     * key/bucket/region before switching uploads over to it.
     */
    public function testConnection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'access_key' => ['required', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'bucket' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:255'],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'use_path_style' => ['nullable', 'boolean'],
        ]);

        $settings = ExternalStorageSettings::current();
        $secret = (is_string($validated['secret'] ?? null) && $validated['secret'] !== '')
            ? $validated['secret']
            : $settings->secret;

        try {
            $config = [
                'version' => 'latest',
                'region' => $validated['region'],
                'credentials' => [
                    'key' => $validated['access_key'],
                    'secret' => (string) $secret,
                ],
                'use_path_style_endpoint' => (bool) ($validated['use_path_style'] ?? false),
            ];

            if (is_string($validated['endpoint'] ?? null) && $validated['endpoint'] !== '') {
                $config['endpoint'] = $validated['endpoint'];
            }

            (new S3Client($config))->headBucket(['Bucket' => $validated['bucket']]);

            $result = __('Success: connected to bucket ":bucket".', ['bucket' => $validated['bucket']]);
        } catch (Throwable $e) {
            $result = __('Failed to connect: :error', ['error' => $e->getMessage()]);
        }

        return back()->with('storage_test_result', $result);
    }
}
