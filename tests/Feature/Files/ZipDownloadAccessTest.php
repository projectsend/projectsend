<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ZipDownload;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| A prepared archive is only handed to someone who may still see what is in it
|--------------------------------------------------------------------------
|
| An archive is built while its requester may see every file in it, and
| then waits up to a day to be fetched. Access can end in that time:
| unshared, expired, deleted. A single file's download asks the policy on
| every request, so the archive asks it again on every fetch too — the
| first and any later one — or it becomes the way around a revocation.
*/

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::MaxZipDownloadSizeMb, 2048);

    $this->client = User::factory()->client()->create();

    $this->actingAs($this->admin)->post('/files', [
        'file' => UploadedFile::fake()->create('report.pdf', 4, 'application/pdf'),
        'name' => '',
        'description' => '',
    ]);
    $this->file = File::query()->latest('id')->firstOrFail();
    $this->actingAs($this->admin)->post("/files/{$this->file->id}/assignments", ['type' => 'client', 'id' => $this->client->id]);
});

function zipFor(User $requester, File $file): ZipDownload
{
    $response = test()->actingAs($requester)->postJson('/zip-downloads', ['file_ids' => [$file->id]])->assertOk();

    return ZipDownload::query()->findOrFail($response->json('id'));
}

function unshareFromClient(File $file, User $client): void
{
    test()->actingAs(test()->admin)
        ->delete("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id])
        ->assertRedirect();
}

test('a client who may still see the file gets the archive', function () {
    $zip = zipFor($this->client, $this->file);

    $this->actingAs($this->client)->get("/zip-downloads/{$zip->id}/download")->assertOk();
});

test('an archive is refused once the file is unshared, before it was ever fetched', function () {
    $zip = zipFor($this->client, $this->file);

    unshareFromClient($this->file, $this->client);

    $this->actingAs($this->client)->get("/files/{$this->file->id}/download")->assertForbidden();
    $this->actingAs($this->client)->get("/zip-downloads/{$zip->id}/download")->assertForbidden();

    // Refused, so not delivered and not counted.
    expect($zip->fresh()->delivered_at)->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::FileDownloaded)->where('subject_id', $this->file->id)->exists())->toBeFalse();
});

test('fetching an archive again is refused once the file is unshared', function () {
    $zip = zipFor($this->client, $this->file);
    $this->actingAs($this->client)->get("/zip-downloads/{$zip->id}/download")->assertOk();

    unshareFromClient($this->file, $this->client);

    $this->actingAs($this->client)->get("/zip-downloads/{$zip->id}/download")->assertForbidden();
});

test('an archive is refused once a file in it expires for the client', function () {
    $zip = zipFor($this->client, $this->file);

    $this->file->update(['expires_at' => now()->subMinute()]);

    $this->actingAs($this->client)->get("/zip-downloads/{$zip->id}/download")->assertForbidden();
});

test('an archive is refused once a file in it is deleted', function () {
    $zip = zipFor($this->client, $this->file);

    $this->actingAs($this->admin)->delete("/files/{$this->file->id}")->assertRedirect();

    $this->actingAs($this->client)->get("/zip-downloads/{$zip->id}/download")->assertForbidden();
});

test('staff still get an archive holding a file that has expired', function () {
    // Expiry hides a file from clients, not from staff, so the rule is
    // the requester's access today, not a property of the file.
    $zip = zipFor($this->admin, $this->file);

    $this->file->update(['expires_at' => now()->subMinute()]);

    $this->actingAs($this->admin)->get("/zip-downloads/{$zip->id}/download")->assertOk();
});
