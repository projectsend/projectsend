<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Files\Models\ZipDownload;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Six routes put a file's bytes on the wire and they authorize four
 * different ways — the whole reason DownloadAllowance is an object every
 * one of them consults rather than a rule in the policy. There is a test
 * here per route, because the failure mode this guards against is one of
 * them quietly not asking.
 */
beforeEach(function () {
    Storage::fake('files');

    $this->uploader = User::factory()->create();
    $this->client = User::factory()->create(['type' => UserType::Client]);
});

function limitedFile(array $overrides = []): File
{
    $file = File::factory()->create(array_merge([
        'uploaded_by' => test()->uploader->id,
        'name' => 'Report',
        'original_name' => 'report.pdf',
        'path' => '2026/08/'.Str::uuid()->toString().'.pdf',
        'mime_type' => 'application/pdf',
        'size' => 12,
        'download_limit' => 1,
    ], $overrides));

    Storage::disk('files')->put($file->path, 'pdf bytes...');

    return $file;
}

/** Spend one download of $file as $actor, without going through a route. */
function spend(File $file, ?User $actor, Action $action = Action::FileDownloaded): void
{
    ActivityLog::query()->create([
        'actor_id' => $actor?->id,
        'actor_name' => $actor?->name,
        'actor_type' => $actor?->type->value,
        'action' => $action,
        'subject_type' => $file->getMorphClass(),
        'subject_id' => $file->id,
        'created_at' => now(),
    ]);
}

test('a direct download is refused once the limit is spent', function () {
    $file = limitedFile();
    shareFileWith($file, $this->client);

    $this->actingAs($this->client)->get("/files/{$file->id}/download")->assertOk();
    $this->actingAs($this->client)->get("/files/{$file->id}/download")->assertForbidden();
});

test('the file stays visible to the client whose limit is spent', function () {
    // The difference from expiry, and the reason the rule is not in a
    // visibility scope: they can still see it, they just cannot take it.
    $file = limitedFile();
    shareFileWith($file, $this->client);
    spend($file, $this->client);

    $this->actingAs($this->client)->get("/files/{$file->id}/download")->assertForbidden();

    expect(File::query()->whereKey($file->id)->visibleToClient($this->client)->exists())->toBeTrue();
});

test('the uploader is never refused their own file', function () {
    $file = limitedFile();
    spend($file, $this->client);

    $this->actingAs($this->uploader)->get("/files/{$file->id}/download")->assertOk();
});

test('the API download honours the limit, because it is the same controller', function () {
    // One token per test on purpose: within a single test the auth guard
    // resolves its user once and keeps it, so swapping the Authorization
    // header mid-test does not swap who the request is from — every
    // later assertion would silently be about the first token's user.
    $other = User::factory()->create();
    $token = $other->createToken('t', ['upload', 'edit_files', 'edit_others_files'])->plainTextToken;

    $file = limitedFile(['uploaded_by' => $this->uploader->id]);

    // The one download this file allows…
    $this->withToken($token)->get("/api/v1/files/{$file->id}/download")->assertOk();

    // …and it is spent, over the API exactly as over the web route.
    $this->withToken($token)->getJson("/api/v1/files/{$file->id}/download")->assertForbidden();
});

test('the API keeps the uploader exempt from their own file\'s limit', function () {
    $uploader = User::factory()->create();
    $token = $uploader->createToken('t', ['upload', 'edit_files', 'edit_others_files'])->plainTextToken;

    $file = limitedFile(['uploaded_by' => $uploader->id]);

    // Three times over a limit of one, and every one of them logged.
    $this->withToken($token)->get("/api/v1/files/{$file->id}/download")->assertOk();
    $this->withToken($token)->get("/api/v1/files/{$file->id}/download")->assertOk();
    $this->withToken($token)->get("/api/v1/files/{$file->id}/download")->assertOk();

    expect($file->downloads()->count())->toBe(3);
});

test('preview is refused once the limit is spent, but never spends it', function () {
    // preview() serves the original bytes for images unless something
    // asks for a rendering, so an unguarded preview would be a way
    // around every limit on the install.
    $file = limitedFile(['mime_type' => 'image/jpeg', 'original_name' => 'photo.jpg', 'download_limit' => 2]);
    shareFileWith($file, $this->client);

    $this->actingAs($this->client)->get("/files/{$file->id}/preview")->assertOk();
    $this->actingAs($this->client)->get("/files/{$file->id}/preview")->assertOk();

    // Two previews, and the allowance is untouched.
    $this->actingAs($this->client)->get("/files/{$file->id}/download")->assertOk();
    $this->actingAs($this->client)->get("/files/{$file->id}/download")->assertOk();

    // Now it is spent — and the preview closes with it.
    $this->actingAs($this->client)->get("/files/{$file->id}/preview")->assertForbidden();
});

test('a share link refuses a file whose own limit is spent, without spending the link', function () {
    $file = limitedFile();
    $link = ShareLink::query()->create([
        'shareable_type' => $file->getMorphClass(),
        'shareable_id' => $file->id,
        'token' => Str::random(40),
        'created_by' => $this->uploader->id,
    ]);

    spend($file, null, Action::PublicFileDownloaded);

    $this->get("/s/{$link->token}/download")->assertRedirect(route('share.show', $link->token));

    // The link's own counter must not move for a download it never made.
    expect($link->fresh()->downloads_count)->toBe(0);
});

test('the share page says the limit was reached when it is the file that is spent', function () {
    $file = limitedFile();
    $link = ShareLink::query()->create([
        'shareable_type' => $file->getMorphClass(),
        'shareable_id' => $file->id,
        'token' => Str::random(40),
        'created_by' => $this->uploader->id,
    ]);

    spend($file, null, Action::ShareLinkDownloaded);

    $this->get("/s/{$link->token}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('share/show')->where('status', 'limit_reached'));
});

test('an anonymous public download is refused once the file is spent', function () {
    app(Settings::class)->set(Setting::PublicListingEnabled, true);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');

    $file = limitedFile(['public' => true]);
    spend($file, null, Action::PublicFileDownloaded);

    // 403, not 404: the file is genuinely there and genuinely public.
    $this->get("/public/files/{$file->slug}/download")->assertForbidden();
});

test('a zip leaves out files whose limit is spent and says which', function () {
    $folder = makeFolder('Shared');

    $available = limitedFile(['name' => 'Available', 'original_name' => 'available.pdf', 'download_limit' => null]);
    $spent = limitedFile(['name' => 'Spent', 'original_name' => 'spent.pdf']);

    $available->update(['folder_id' => $folder->id]);
    $spent->update(['folder_id' => $folder->id]);
    spend($spent, $this->uploader);

    // A different staff member, so the uploader exemption is not what is
    // being measured.
    $staff = staffWithPermissions(['upload', 'edit_files', 'edit_others_files']);

    $response = $this->actingAs($staff)
        ->postJson('/zip-downloads', ['folder_ids' => [$folder->id]])
        ->assertOk();

    $zip = ZipDownload::query()->findOrFail($response->json('id'));

    expect($zip->file_count)->toBe(1)
        ->and($zip->skipped_files)->toHaveCount(1)
        ->and($zip->skipped_files[0]['name'])->toBe('Spent');

    $this->actingAs($staff)->getJson("/zip-downloads/{$zip->id}")
        ->assertOk()
        ->assertJsonPath('skipped_files.0.name', 'Spent');
});

test('refusing every selected file says so rather than claiming they are missing', function () {
    $file = limitedFile();
    shareFileWith($file, $this->client);
    spend($file, $this->client);

    $this->actingAs($this->client)
        ->postJson('/zip-downloads', ['file_ids' => [$file->id]])
        ->assertStatus(422)
        ->assertSee('reached their download limit', false);
});

test('re-fetching one prepared zip does not count as downloading everything again', function () {
    $file = limitedFile(['download_limit' => null]);
    shareFileWith($file, $this->client);

    $response = $this->actingAs($this->client)
        ->postJson('/zip-downloads', ['file_ids' => [$file->id]])
        ->assertOk();

    $zip = ZipDownload::query()->findOrFail($response->json('id'));

    $this->actingAs($this->client)->get("/zip-downloads/{$zip->id}/download")->assertOk();
    $this->actingAs($this->client)->get("/zip-downloads/{$zip->id}/download")->assertOk();

    // One delivery, one download — not two.
    expect($file->downloads()->count())->toBe(1)
        ->and($zip->fresh()->delivered_at)->not->toBeNull();
});
