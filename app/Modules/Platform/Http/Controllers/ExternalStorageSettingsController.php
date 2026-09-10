<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\ExternalStorageSettings;
use App\Modules\Platform\Settings\StorageProvider;
use Aws\S3\S3Client;
use Closure;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
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
            'provider' => $settings->provider->value,
            'use_instance_role' => $settings->use_instance_role,
            // Never name a top-level Inertia prop "key" — Inertia's React
            // renderer spreads page props onto the component via
            // `{ key: <internal-remount-key>, ...props }`, and a prop
            // literally named "key" in that spread silently overrides
            // React's own reconciliation key, so the value never reaches
            // the component as an actual prop (React always strips `key`).
            'access_key' => $settings->key ?? '',
            'has_secret' => $settings->secret !== null && $settings->secret !== '',
            // Same treatment as the secret: never round-tripped, only
            // whether one is stored. A service account key file is more
            // sensitive than an access key, not less.
            'has_key_file' => $settings->key_file !== null && $settings->key_file !== '',
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
        $request->merge(['provider' => $request->input('provider', StorageProvider::S3->value)]);

        $validated = $request->validate([
            'active' => ['required', 'boolean'],
            // 'sometimes', not 'required': absent means S3, which is what
            // every payload written before this choice existed meant, and
            // stops a browser holding a stale bundle from failing to save
            // on a field it cannot see.
            'provider' => ['sometimes', Rule::enum(StorageProvider::class)],
            'bucket' => ['required', 'string', 'max:255'],
            'root' => ['nullable', 'string', 'max:255'],

            // 'sometimes' for the same reason as 'provider' above: absent
            // means false, which is what every payload written before this
            // choice existed meant.
            'use_instance_role' => ['sometimes', 'boolean'],

            // Required only for the provider that uses them, so switching
            // to GCS does not demand an AWS region that means nothing.
            'access_key' => [self::requiredForStaticS3($request), 'nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            // Still required when the machine's own role is doing the
            // authenticating: the credential chain resolves credentials,
            // not which region the bucket is in.
            'region' => ['required_if:provider,s3', 'nullable', 'string', 'max:255'],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'use_path_style' => ['required', 'boolean'],

            // Checked for shape here rather than left to fail at the first
            // upload: a key file is pasted, and a paste that lost its last
            // line is the likeliest way this goes wrong.
            'key_file' => ['nullable', 'string', self::serviceAccountKeyRule()],
        ]);

        $settings = ExternalStorageSettings::current();

        $useInstanceRole = (bool) ($validated['use_instance_role'] ?? false);

        $settings->fill([
            'active' => $validated['active'],
            'provider' => $validated['provider'],
            'use_instance_role' => $useInstanceRole,
            'key' => $useInstanceRole ? null : ($validated['access_key'] ?? null),
            'bucket' => $validated['bucket'],
            'region' => $validated['region'] ?? null,
            'endpoint' => $validated['endpoint'] ?? null,
            'use_path_style' => $validated['use_path_style'],
            'root' => $validated['root'] ?? null,
        ]);

        // A blank credential keeps whatever is already stored — neither
        // field is ever round-tripped to the browser (only the has_*
        // flags are), so blank means "unchanged", not "cleared".
        //
        // Except when the machine's own role takes over, which is the one
        // thing that does clear it. The whole point of the setting is that
        // no long-lived AWS credential is kept here, and a secret left
        // sitting in the row unused would still be in the next database
        // dump — and would silently come back the moment the box is
        // unticked.
        if ($useInstanceRole) {
            $settings->secret = null;
        } elseif (is_string($validated['secret'] ?? null) && $validated['secret'] !== '') {
            $settings->secret = $validated['secret'];
        }

        if (is_string($validated['key_file'] ?? null) && $validated['key_file'] !== '') {
            $settings->key_file = $validated['key_file'];
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
     * Verifies the submitted (or, where a credential field was left
     * blank, the already-stored) details can actually reach the bucket,
     * mirroring v1's connection test — this exists specifically to catch
     * a typo'd key/bucket/region before switching uploads over to it.
     */
    public function testConnection(Request $request): RedirectResponse
    {
        $request->merge(['provider' => $request->input('provider', StorageProvider::S3->value)]);

        $validated = $request->validate([
            'provider' => ['sometimes', Rule::enum(StorageProvider::class)],
            'bucket' => ['required', 'string', 'max:255'],
            'use_instance_role' => ['sometimes', 'boolean'],
            'access_key' => [self::requiredForStaticS3($request), 'nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'region' => ['required_if:provider,s3', 'nullable', 'string', 'max:255'],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'use_path_style' => ['nullable', 'boolean'],
            'key_file' => ['nullable', 'string', self::serviceAccountKeyRule()],
        ]);

        try {
            match (StorageProvider::from($validated['provider'])) {
                StorageProvider::S3 => $this->probeS3($validated),
                StorageProvider::Gcs => $this->probeGcs($validated),
            };

            $result = __('Success: connected to bucket ":bucket".', ['bucket' => $validated['bucket']]);
        } catch (Throwable $e) {
            $result = __('Failed to connect: :error', ['error' => $e->getMessage()]);
        }

        return back()->with('storage_test_result', $result);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function probeS3(array $validated): void
    {
        $config = [
            'version' => 'latest',
            'region' => $validated['region'],
            'use_path_style_endpoint' => (bool) ($validated['use_path_style'] ?? false),
        ];

        // Omitted entirely, not left blank: an S3Client handed a
        // 'credentials' array is told to use it, so an empty one fails
        // instead of falling through to the default credential provider
        // chain. This is what makes the button test the same thing the
        // uploads will do — see ExternalStorageConfigApplier::applyS3().
        if (! ($validated['use_instance_role'] ?? false)) {
            $config['credentials'] = [
                'key' => $validated['access_key'],
                'secret' => (string) $this->storedIfBlank($validated, 'secret'),
            ];
        }

        if (is_string($validated['endpoint'] ?? null) && $validated['endpoint'] !== '') {
            $config['endpoint'] = $validated['endpoint'];
        }

        (new S3Client($config))->headBucket(['Bucket' => $validated['bucket']]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function probeGcs(array $validated): void
    {
        $keyFile = json_decode((string) $this->storedIfBlank($validated, 'key_file'), true);

        if (! is_array($keyFile)) {
            throw new RuntimeException(__('No service account key has been saved yet.'));
        }

        $bucket = (new StorageClient(['keyFile' => $keyFile]))->bucket($validated['bucket']);

        // Listing one object rather than asking whether the bucket exists.
        // A least-privilege key — roles/storage.objectAdmin scoped to this
        // bucket, which is what the whole design rests on — can read and
        // write objects but cannot read the bucket's own metadata, so
        // $bucket->exists() reports failure for a key that works perfectly.
        // An empty bucket is a valid answer here, and returns no rows.
        iterator_to_array($bucket->objects(['maxResults' => 1]), false);
    }

    /**
     * An access key is only demanded of an S3 backend that is actually
     * going to authenticate with one. Shared by both the save and the
     * connection test so the two cannot disagree about what is required.
     */
    private static function requiredForStaticS3(Request $request): RequiredIf
    {
        // Rule::requiredIf(), not a closure rule: this has to fire when
        // the field is missing from the payload altogether, and a closure
        // rule is not implicit — it never runs on an absent attribute.
        return Rule::requiredIf(
            fn (): bool => $request->input('provider') === StorageProvider::S3->value
                && ! $request->boolean('use_instance_role')
        );
    }

    /**
     * A credential field left blank means "keep what is stored" on save,
     * so the connection test has to read it the same way — otherwise
     * testing an unchanged configuration would always fail.
     *
     * @param  array<string, mixed>  $validated
     */
    private function storedIfBlank(array $validated, string $field): ?string
    {
        $submitted = $validated[$field] ?? null;

        if (is_string($submitted) && $submitted !== '') {
            return $submitted;
        }

        return ExternalStorageSettings::current()->{$field};
    }

    /**
     * A pasted service account key, checked for the parts that have to be
     * there. Not a credential check — that is what Test connection is for.
     */
    private static function serviceAccountKeyRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $decoded = json_decode((string) $value, true);

            if (! is_array($decoded)) {
                $fail(__('That does not look like a service account key file: it is not valid JSON.'));

                return;
            }

            foreach (['client_email', 'private_key'] as $required) {
                if (! isset($decoded[$required]) || ! is_string($decoded[$required]) || $decoded[$required] === '') {
                    $fail(__('That service account key file is missing its :field.', ['field' => $required]));

                    return;
                }
            }
        };
    }
}
