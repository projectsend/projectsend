<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Http\Controllers\ZipDownloadsController;
use App\Modules\Files\Jobs\BuildZipDownloadJob;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ZipDownload;
use App\Modules\Files\Queue\StalledZipBuilds;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
 * `downloads.zip` is granted by both editions and subtracted by a hosted
 * plan through PROJECTSEND_CAPABILITIES_DISABLED. What that has to mean:
 * the three routes are gone rather than hidden, a build already queued
 * does no work, and nothing already built is touched.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::MaxZipDownloadSizeMb, 2048);
});

function withoutZipDownloads(): void
{
    // CapabilityRegistry is bound, not a singleton, so the next resolve
    // reads this.
    config(['projectsend.capabilities_disabled' => 'downloads.zip']);
}

function zipCapabilityFile(User $as): File
{
    test()->actingAs($as)->post('/files', [
        'file' => UploadedFile::fake()->create('report.pdf', 4, 'application/pdf'),
        'name' => '',
        'description' => '',
    ]);

    return File::query()->latest('id')->firstOrFail();
}

test('staff get 404 on all three zip routes when the capability is withheld', function () {
    $file = zipCapabilityFile($this->admin);
    $ready = $this->actingAs($this->admin)->postJson('/zip-downloads', ['file_ids' => [$file->id]])->assertOk();
    $id = $ready->json('id');

    withoutZipDownloads();

    $this->actingAs($this->admin)->postJson('/zip-downloads', ['file_ids' => [$file->id]])->assertNotFound();
    $this->actingAs($this->admin)->getJson("/zip-downloads/{$id}")->assertNotFound();
    $this->actingAs($this->admin)->get("/zip-downloads/{$id}/download")->assertNotFound();

    // Refused at the door: no row was written for the second request.
    expect(ZipDownload::query()->count())->toBe(1);
});

test('clients get 404 on all three zip routes when the capability is withheld', function () {
    $client = User::factory()->client()->create();
    $file = zipCapabilityFile($this->admin);
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    $id = $this->actingAs($client)->postJson('/zip-downloads', ['file_ids' => [$file->id]])->assertOk()->json('id');

    withoutZipDownloads();

    $this->actingAs($client)->postJson('/zip-downloads', ['file_ids' => [$file->id]])->assertNotFound();
    $this->actingAs($client)->getJson("/zip-downloads/{$id}")->assertNotFound();
    $this->actingAs($client)->get("/zip-downloads/{$id}/download")->assertNotFound();
});

test('the zip routes are open on both editions by default', function (Edition $edition) {
    config(['projectsend.edition' => $edition]);
    $file = zipCapabilityFile($this->admin);

    $this->actingAs($this->admin)->postJson('/zip-downloads', ['file_ids' => [$file->id]])->assertOk();
})->with([Edition::Community, Edition::Cloud]);

test('a build queued before the capability was withheld is refused and ends failed', function () {
    $row = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'file_ids' => [zipCapabilityFile($this->admin)->id],
        'status' => ZipDownload::STATUS_PENDING,
    ]);

    withoutZipDownloads();

    (new BuildZipDownloadJob($row->id))->handle();

    $row->refresh();
    expect($row->status)->toBe(ZipDownload::STATUS_FAILED)
        ->and($row->error)->toBe('Zip downloads are not available on this site.')
        // Never stamped, so it never looked like a build in hand.
        ->and($row->started_at)->toBeNull()
        ->and($row->path)->toBeNull();

    Storage::disk('files')->assertMissing("zips/{$row->id}.zip");
});

test('an archive already built is left on disk when the capability is withheld', function () {
    // A downgrade never deletes data. The archive stays until
    // PurgeZipDownloadsCommand ages it out like any other.
    $file = zipCapabilityFile($this->admin);
    $row = ZipDownload::query()->findOrFail(
        $this->actingAs($this->admin)->postJson('/zip-downloads', ['file_ids' => [$file->id]])->json('id'),
    );

    withoutZipDownloads();
    Artisan::call('projectsend:purge-zip-downloads');

    expect($row->refresh()->status)->toBe(ZipDownload::STATUS_READY);
    Storage::disk('files')->assertExists($row->path);
});

test('rows left waiting after the capability is withheld raise no worker banner', function () {
    $row = ZipDownload::query()->create([
        'requested_by' => $this->admin->id,
        'status' => ZipDownload::STATUS_PENDING,
    ]);
    $row->forceFill(['created_at' => now()->subMinutes(30)])->save();

    expect(app(StalledZipBuilds::class)->oldestUnstarted())->not->toBeNull();

    withoutZipDownloads();

    expect(app(StalledZipBuilds::class)->oldestUnstarted())->toBeNull();
});

test('the key comes and goes in the status document', function () {
    Artisan::call('projectsend:status', ['--json' => true]);
    expect(json_decode(Artisan::output(), true)['capabilities'])->toContain('downloads.zip');

    withoutZipDownloads();

    Artisan::call('projectsend:status', ['--json' => true]);
    expect(json_decode(Artisan::output(), true)['capabilities'])->not->toContain('downloads.zip');
});

test('the key comes and goes in the shared Inertia props', function () {
    $this->actingAs($this->admin)->get('/files')
        ->assertInertia(fn ($page) => $page->where('capabilities', fn ($keys) => collect($keys)->contains('downloads.zip')));

    withoutZipDownloads();

    $this->actingAs($this->admin)->get('/files')
        ->assertInertia(fn ($page) => $page->where('capabilities', fn ($keys) => ! collect($keys)->contains('downloads.zip')));
});

/*
 * The guard. Any route, web or API, core's or a package's, that ends in
 * ZipDownloadsController — or in a controller that queues a zip build —
 * must carry the capability. A new zip route that forgets it would build
 * archives on exactly the instances that were told not to.
 */
test('every route that reaches a zip build carries capability:downloads.zip', function () {
    $zipRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(function (RoutingRoute $route): bool {
            $controller = $route->getControllerClass();

            if ($controller === null || ! class_exists($controller)) {
                return false;
            }

            if ($controller === ZipDownloadsController::class) {
                return true;
            }

            // A dispatch, not a mention: several settings controllers
            // name the job in a comment.
            $source = (string) file_get_contents((string) (new ReflectionClass($controller))->getFileName());

            return preg_match('/BuildZipDownloadJob::dispatch|new\s+BuildZipDownloadJob\b/', $source) === 1;
        });

    // Otherwise a refactor that renamed the controller would leave this
    // test green over nothing.
    expect($zipRoutes)->toHaveCount(3);

    foreach ($zipRoutes as $route) {
        expect(in_array('capability:downloads.zip', $route->gatherMiddleware(), true))
            ->toBeTrue("{$route->uri()} is missing capability:downloads.zip");
    }
});
