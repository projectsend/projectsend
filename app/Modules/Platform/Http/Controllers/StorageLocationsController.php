<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Settings\StorageLocation;
use App\Modules\Platform\Settings\StorageProvider;
use Aws\S3\S3Client;
use Closure;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * Settings → Storage: the places uploaded files can be kept, which of
 * them new uploads go to by default, and whether the client-upload email
 * says which one a file went to.
 *
 * Community-only (Capability::StorageConfigure, enforced entirely via the
 * `capability:storage.configure` route middleware — every field on this
 * page is gated, unlike email settings' sender-identity fields, so there's
 * no field-level split to do inside the controller). Cloud never reaches
 * this controller at all.
 */
class StorageLocationsController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly ExternalStorageConfigApplier $configApplier,
        private readonly Settings $settings,
    ) {}

    public function edit(Request $request): Response
    {
        // Trashed files included: their bytes are still in the location
        // until the trash is emptied.
        $fileCounts = File::withTrashed()->toBase()
            ->selectRaw('disk, count(*) as aggregate')
            ->groupBy('disk')
            ->pluck('aggregate', 'disk');

        $locations = StorageLocation::query()->withCount('clients')->orderByDesc('is_default')->orderBy('id')->get()
            ->map(fn (StorageLocation $location): array => [
                'id' => $location->id,
                'name' => $location->name,
                'client_note' => $location->client_note ?? '',
                'local' => $location->isLocal(),
                'provider' => $location->provider,
                'is_default' => $location->is_default,
                'accepts_uploads' => $location->accepts_uploads,
                'configured' => $location->isConfigured(),
                'location' => $location->location(),
                'clients_count' => $location->clients_count,
                'files_count' => (int) ($fileCounts[$location->disk] ?? 0),
                'use_instance_role' => $location->use_instance_role,
                // Never name a top-level Inertia prop "key" — Inertia's React
                // renderer spreads page props onto the component via
                // `{ key: <internal-remount-key>, ...props }`, and a prop
                // literally named "key" in that spread silently overrides
                // React's own reconciliation key, so the value never reaches
                // the component as an actual prop (React always strips `key`).
                'access_key' => $location->key ?? '',
                'has_secret' => $location->secret !== null && $location->secret !== '',
                // Same treatment as the secret: never round-tripped, only
                // whether one is stored. A service account key file is more
                // sensitive than an access key, not less.
                'has_key_file' => $location->key_file !== null && $location->key_file !== '',
                'bucket' => $location->bucket ?? '',
                'region' => $location->region ?? '',
                'endpoint' => $location->endpoint ?? '',
                'use_path_style' => $location->use_path_style,
                'root' => $location->root ?? '',
            ])->all();

        return Inertia::render('system/settings/storage', [
            'locations' => $locations,
            'upload_notification_names_location' => $this->settings->get(Setting::UploadNotificationNamesLocation),
            'test_result' => $request->session()->get('storage_test_result'),
        ]);
    }

    /**
     * The default location and the notification option.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_location_id' => ['required', 'integer', Rule::exists('storage_locations', 'id')],
            'upload_notification_names_location' => ['required', 'boolean'],
        ]);

        $default = StorageLocation::query()->findOrFail((int) $validated['default_location_id']);

        // Where staff and every unassigned client upload to: a location
        // that cannot take an upload would fail every one of them.
        if (! $default->accepts_uploads || ! $default->isConfigured()) {
            throw ValidationException::withMessages([
                'default_location_id' => __('The default location has to be set up and accepting uploads.'),
            ]);
        }

        DB::transaction(function () use ($default): void {
            StorageLocation::query()->whereKeyNot($default->id)->update(['is_default' => false]);
            $default->forceFill(['is_default' => true])->save();
        });

        $this->settings->set(Setting::UploadNotificationNamesLocation, (bool) $validated['upload_notification_names_location']);

        return $this->saved();
    }

    public function store(Request $request): RedirectResponse
    {
        $location = new StorageLocation;
        // Fixed for the life of the row: files on this location are stamped
        // with it. Not derived from the id, so it is known before the insert.
        $location->disk = 'files_loc_'.Str::lower((string) Str::ulid());

        $this->fillBucket($location, $request);
        $location->save();

        return $this->saved();
    }

    public function updateLocation(Request $request, StorageLocation $location): RedirectResponse
    {
        if ($location->isLocal()) {
            $validated = $request->validate($this->descriptionRules());
            $location->fill([
                'name' => $validated['name'],
                'client_note' => $validated['client_note'] ?? null,
                'accepts_uploads' => (bool) $validated['accepts_uploads'],
            ]);
        } else {
            $this->fillBucket($location, $request);
        }

        if ($location->is_default && (! $location->accepts_uploads || ! $location->isConfigured())) {
            throw ValidationException::withMessages([
                'accepts_uploads' => __('This is the default location. Choose another default before turning off its uploads.'),
            ]);
        }

        $location->save();

        return $this->saved();
    }

    public function destroy(StorageLocation $location): RedirectResponse
    {
        abort_if($location->isLocal(), 422, __('The server\'s own storage cannot be removed.'));

        if ($location->is_default) {
            throw ValidationException::withMessages([
                'location' => __('This is the default location. Choose another default before removing it.'),
            ]);
        }

        // Removing it would leave every file stored here pointing at a disk
        // that no longer exists. Turning off its uploads is the way to stop
        // using it.
        if (File::withTrashed()->where('disk', $location->disk)->exists()) {
            throw ValidationException::withMessages([
                'location' => __('This location still holds files. Turn off its uploads instead.'),
            ]);
        }

        $location->delete();

        return $this->saved();
    }

    private function fillBucket(StorageLocation $location, Request $request): void
    {
        $request->merge(['provider' => $request->input('provider', StorageProvider::S3->value)]);

        $validated = $request->validate([
            ...$this->descriptionRules(),
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

        $useInstanceRole = (bool) ($validated['use_instance_role'] ?? false);

        $location->fill([
            'name' => $validated['name'],
            'client_note' => $validated['client_note'] ?? null,
            'accepts_uploads' => (bool) $validated['accepts_uploads'],
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
            $location->secret = null;
        } elseif (is_string($validated['secret'] ?? null) && $validated['secret'] !== '') {
            $location->secret = $validated['secret'];
        }

        if (is_string($validated['key_file'] ?? null) && $validated['key_file'] !== '') {
            $location->key_file = $validated['key_file'];
        }

        // A new bucket missing its credentials would be listed but never
        // registered, which reads as "saved" and works as nothing.
        if (! $location->isConfigured()) {
            throw ValidationException::withMessages([
                'secret' => __('Enter the credentials for this bucket.'),
            ]);
        }
    }

    /** @return array<string, list<string>> */
    private function descriptionRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'client_note' => ['nullable', 'string', 'max:255'],
            'accepts_uploads' => ['required', 'boolean'],
        ];
    }

    private function saved(): RedirectResponse
    {
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
            'location_id' => ['nullable', 'integer', Rule::exists('storage_locations', 'id')],
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

        $stored = isset($validated['location_id']) ? StorageLocation::query()->find((int) $validated['location_id']) : null;

        try {
            match (StorageProvider::from($validated['provider'])) {
                StorageProvider::S3 => $this->probeS3($validated, $stored),
                StorageProvider::Gcs => $this->probeGcs($validated, $stored),
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
    private function probeS3(array $validated, ?StorageLocation $stored): void
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
                'secret' => (string) $this->storedIfBlank($validated, 'secret', $stored),
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
    private function probeGcs(array $validated, ?StorageLocation $stored): void
    {
        $keyFile = json_decode((string) $this->storedIfBlank($validated, 'key_file', $stored), true);

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
     * testing an unchanged configuration would always fail. What is
     * stored is the location's named by `location_id`, if any.
     *
     * @param  array<string, mixed>  $validated
     */
    private function storedIfBlank(array $validated, string $field, ?StorageLocation $stored): ?string
    {
        $submitted = $validated[$field] ?? null;

        if (is_string($submitted) && $submitted !== '') {
            return $submitted;
        }

        return $stored?->{$field};
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
