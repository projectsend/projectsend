<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * @return array<string, mixed>
 */
function publicPageProps(TestResponse $response): array
{
    $page = json_decode(json_encode($response->viewData('page')), true);

    return $page['props'];
}

function publicListingFile(array $overrides = []): File
{
    return File::factory()->create(array_merge([
        'uploaded_by' => User::factory()->create()->id,
        'name' => 'Report',
        'original_name' => 'report.pdf',
        'path' => '2026/08/'.Str::uuid()->toString().'.pdf',
        'mime_type' => 'application/pdf',
        'size' => 2048,
        'public' => true,
    ], $overrides));
}

/**
 * A real, thumbnailable public image on the faked "files" disk — unlike
 * publicListingFile()'s bare PDF row, this one has actual bytes GD can
 * decode, needed to exercise the thumbnail-generation path.
 */
function publicListingImageFile(User $uploader): File
{
    test()->actingAs($uploader)->post('/files', [
        'file' => UploadedFile::fake()->image('photo.jpg', 200, 100),
        'name' => '',
        'description' => '',
    ]);

    $file = File::query()->latest('id')->firstOrFail();
    $file->update(['public' => true]);

    return $file;
}

beforeEach(function () {
    Storage::fake('files');

    // EnsureSetupIsComplete redirects every guest request to /setup until
    // a staff account exists — an unrelated concern to these tests, but
    // one they need to satisfy just like every other feature test does.
    User::factory()->create();

    app(Settings::class)->set(Setting::PublicListingEnabled, true);
    app(Settings::class)->set(Setting::PublicListingSlug, 'public');
    app(Settings::class)->set(Setting::Theme, 'default');
});

test('the directory 404s when disabled, but a specific public group page and its downloads still work', function () {
    // PublicListingEnabled only gates the browsable directory (index()) —
    // a public group's own page/downloads are independent of it, exactly
    // like a share link isn't gated by any global toggle.
    app(Settings::class)->set(Setting::PublicListingEnabled, false);
    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $staff = User::factory()->create();
    $file = publicListingFile();
    $this->actingAs($staff)->post("/files/{$file->id}/assignments", ['type' => 'group', 'id' => $group->id]);
    auth()->logout();

    $this->get('/public')->assertNotFound();
    $this->get("/public/{$group->slug}")->assertOk();
    $this->get("/public/files/{$file->slug}/download")->assertOk();
});

test('the wrong base slug 404s even when enabled', function () {
    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);

    $this->get('/wrong-base')->assertNotFound();
    $this->get("/wrong-base/{$group->slug}")->assertNotFound();
});

test('the index lists public groups and standalone public files without leaking private ones', function () {
    $publicGroup = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $privateGroup = Group::query()->create(['name' => 'Closed Group', 'public' => false]);

    $standalone = publicListingFile(['name' => 'Standalone']);
    $privateOnly = publicListingFile(['name' => 'Private Only', 'public' => false]);

    // Assigned only to a private group but itself flagged public: still
    // shows on the front page (the file flag is independent of any group).
    $orphanOfPrivateGroup = publicListingFile(['name' => 'Orphan Of Private']);
    $this->actingAs(User::factory()->create())
        ->post("/files/{$orphanOfPrivateGroup->id}/assignments", ['type' => 'group', 'id' => $privateGroup->id]);

    $inPublicGroup = publicListingFile(['name' => 'In Public Group']);
    $this->actingAs(User::factory()->create())
        ->post("/files/{$inPublicGroup->id}/assignments", ['type' => 'group', 'id' => $publicGroup->id]);

    // actingAs() persists across requests within a test — logout so the
    // listing is viewed as a genuine guest, not still the staff member.
    auth()->logout();
    $response = $this->get('/public');

    $response->assertInertia(
        fn ($page) => $page
            ->component('public/themes/default/index')
            ->has('groups', 1)
            ->where('groups.0.name', 'Open Group')
            ->has('files', 2),
    );

    $names = collect(publicPageProps($response)['files'])->pluck('name');
    expect($names)->toContain('Standalone')
        ->toContain('Orphan Of Private')
        ->not->toContain('Private Only')
        ->not->toContain('In Public Group');

    expect((string) $response->getContent())->not->toContain('Closed Group');
});

test('a public group page lists its directly assigned and folder-subtree files, filtered to public ones', function () {
    $group = Group::query()->create(['name' => 'Design Team', 'public' => true]);
    $staff = User::factory()->create();

    $direct = publicListingFile(['name' => 'Direct']);
    $this->actingAs($staff)->post("/files/{$direct->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    $notPublic = publicListingFile(['name' => 'Not Public', 'public' => false]);
    $this->actingAs($staff)->post("/files/{$notPublic->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    $folder = Folder::query()->create(['name' => 'Shared Folder']);
    $this->actingAs($staff)->post("/folders/{$folder->id}/assignments", ['type' => 'group', 'id' => $group->id]);
    $viaFolder = publicListingFile(['name' => 'Via Folder', 'folder_id' => $folder->id]);

    $elsewhere = publicListingFile(['name' => 'Elsewhere']);

    // actingAs() persists across requests within a test — logout so the
    // group page is viewed as a genuine guest, not still the staff member.
    auth()->logout();
    $response = $this->get("/public/{$group->slug}");

    $response->assertInertia(
        fn ($page) => $page->component('public/themes/default/group')->where('group.name', 'Design Team'),
    );

    $names = collect(publicPageProps($response)['files'])->pluck('name');
    expect($names)->toContain('Direct')
        ->toContain('Via Folder')
        ->not->toContain('Not Public')
        ->not->toContain('Elsewhere');
});

test('selecting the compact theme renders the compact components', function () {
    app(Settings::class)->set(Setting::Theme, 'compact');

    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $file = publicListingFile();

    $this->get('/public')->assertInertia(fn ($page) => $page->component('public/themes/compact/index'));
    $this->get("/public/{$group->slug}")->assertInertia(fn ($page) => $page->component('public/themes/compact/group'));
    $this->get("/public/files/{$file->slug}")->assertInertia(fn ($page) => $page->component('public/themes/compact/file'));
});

test('selecting the drive theme renders the drive components with a mime_type per file', function () {
    app(Settings::class)->set(Setting::Theme, 'drive');

    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $file = publicListingFile();

    $indexResponse = $this->get('/public');
    $indexResponse->assertInertia(fn ($page) => $page->component('public/themes/drive/index'));
    expect(collect(publicPageProps($indexResponse)['files'])->firstWhere('name', $file->name)['mime_type'] ?? null)
        ->toBe('application/pdf');

    $this->get("/public/{$group->slug}")->assertInertia(fn ($page) => $page->component('public/themes/drive/group'));
    $this->get("/public/files/{$file->slug}")->assertInertia(fn ($page) => $page->component('public/themes/drive/file'));
});

test('an unknown or unavailable stored theme falls back to default rather than a broken page', function () {
    app(Settings::class)->set(Setting::Theme, 'does-not-exist');

    $this->get('/public')->assertInertia(fn ($page) => $page->component('public/themes/default/index'));
});

test('selecting the gallery theme renders the gallery components', function () {
    app(Settings::class)->set(Setting::Theme, 'gallery');

    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $file = publicListingFile();

    $this->get('/public')->assertInertia(fn ($page) => $page->component('public/themes/gallery/index'));
    $this->get("/public/{$group->slug}")->assertInertia(fn ($page) => $page->component('public/themes/gallery/group'));
    $this->get("/public/files/{$file->slug}")->assertInertia(fn ($page) => $page->component('public/themes/gallery/file'));
});

test('a private group 404s even with its correct slug', function () {
    $group = Group::query()->create(['name' => 'Closed Group', 'public' => false]);

    $this->get("/public/{$group->slug}")->assertNotFound();
});

test('download serves a public file and 404s a non-public one regardless of group state', function () {
    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $staff = User::factory()->create();

    $public = publicListingFile(['name' => 'Downloadable']);
    $this->actingAs($staff)->post("/files/{$public->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    $notPublic = publicListingFile(['name' => 'Not Downloadable', 'public' => false]);
    $this->actingAs($staff)->post("/files/{$notPublic->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    $this->get("/public/files/{$public->slug}/download")
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.$public->path);

    expect(ActivityLog::query()->where('action', Action::PublicFileDownloaded)->where('subject_name', 'Downloadable')->exists())->toBeTrue();

    $this->get("/public/files/{$notPublic->slug}/download")->assertNotFound();
});

test('an expired public file 404s on its detail, thumbnail, and download routes, and drops out of the standalone listing', function () {
    $expired = publicListingFile(['name' => 'Expired', 'expires_at' => now()->subDay()]);

    $this->get(route('public.file', ['public', $expired->slug]))->assertNotFound();
    $this->get(route('public.thumbnail', ['public', $expired->slug]))->assertNotFound();
    $this->get(route('public.download', ['public', $expired->slug]))->assertNotFound();

    $this->get('/public')->assertInertia(
        fn ($page) => $page->where('files', fn ($files) => collect($files)->pluck('name')->doesntContain('Expired')),
    );
});

test('an expired file also drops out of its public group\'s page', function () {
    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $staff = User::factory()->create();

    $expired = publicListingFile(['name' => 'Expired In Group', 'expires_at' => now()->subDay()]);
    $this->actingAs($staff)->post("/files/{$expired->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    $this->get("/public/{$group->slug}")->assertInertia(
        fn ($page) => $page->has('files', 0),
    );
});

test('the file listing rows on the directory and group pages link to a details page', function () {
    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $staff = User::factory()->create();

    // Standalone (no group) — appears on the front directory.
    $standalone = publicListingFile(['name' => 'Standalone']);

    // Assigned to the public group — appears on its page instead.
    $inGroup = publicListingFile(['name' => 'In Group']);
    $this->actingAs($staff)->post("/files/{$inGroup->id}/assignments", ['type' => 'group', 'id' => $group->id]);
    auth()->logout();

    $indexProps = publicPageProps($this->get('/public'));
    expect(collect($indexProps['files'])->firstWhere('name', 'Standalone')['url'] ?? null)
        ->toBe(route('public.file', ['public', $standalone->slug]));

    $groupProps = publicPageProps($this->get("/public/{$group->slug}"));
    expect(collect($groupProps['files'])->firstWhere('name', 'In Group')['url'] ?? null)
        ->toBe(route('public.file', ['public', $inGroup->slug]));
});

test('a public file\'s details page shows a thumbnail url only when the mime type supports it', function () {
    $staff = User::factory()->create();
    $image = publicListingImageFile($staff);
    $pdf = publicListingFile();
    auth()->logout();

    $imageResponse = $this->get(route('public.file', ['public', $image->slug]));
    $imageResponse->assertInertia(
        fn ($page) => $page->component('public/themes/default/file')
            ->where('file.name', $image->name)
            ->where('thumbnail_url', route('public.thumbnail', ['public', $image->slug])),
    );

    $pdfResponse = $this->get(route('public.file', ['public', $pdf->slug]));
    $pdfResponse->assertInertia(fn ($page) => $page->where('thumbnail_url', null));
});

test('the directory and group listings carry a thumbnail_url per file too, not just the details page', function () {
    $staff = User::factory()->create();
    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);

    $image = publicListingImageFile($staff);
    $this->actingAs($staff)->post("/files/{$image->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    $pdf = publicListingFile();
    auth()->logout();

    $indexProps = publicPageProps($this->get('/public'));
    $pdfEntry = collect($indexProps['files'])->firstWhere('name', $pdf->name);
    expect($pdfEntry)->not->toBeNull()
        ->and($pdfEntry['thumbnail_url'])->toBeNull();

    $groupProps = publicPageProps($this->get("/public/{$group->slug}"));
    expect(collect($groupProps['files'])->firstWhere('name', $image->name)['thumbnail_url'] ?? null)
        ->toBe(route('public.thumbnail', ['public', $image->slug]));
});

test('a non-public file\'s details page 404s', function () {
    $file = publicListingFile(['public' => false]);

    $this->get(route('public.file', ['public', $file->slug]))->assertNotFound();
});

test('the public thumbnail route generates and serves a thumbnail for a public image, and 404s otherwise', function () {
    $staff = User::factory()->create();
    $image = publicListingImageFile($staff);
    $pdf = publicListingFile();
    $privateImage = publicListingImageFile($staff);
    $privateImage->update(['public' => false]);
    auth()->logout();

    $this->get(route('public.thumbnail', ['public', $image->slug]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
    // The external variant, and only that one: a public visitor is never
    // staff, and caching their thumbnail where a staff request would look
    // for it would hand the staff file manager whatever a public listener
    // drew on it (the cloud-modules watermark, today).
    expect(Storage::disk('files')->exists("thumbnails/external/{$image->id}.jpg"))->toBeTrue()
        ->and(Storage::disk('files')->exists("thumbnails/{$image->id}.jpg"))->toBeFalse();

    $this->get(route('public.thumbnail', ['public', $pdf->slug]))->assertNotFound();
    $this->get(route('public.thumbnail', ['public', $privateImage->slug]))->assertNotFound();
});

test('existing literal routes are unaffected by the new catch-all public routes', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
    $this->actingAs(User::factory()->create())->get('/files')->assertOk();
});

test('a public file carries its categories on the directory, a group page, and its own details page', function () {
    // The /categories screen tells admins that everyone who can reach a
    // file sees the labels on it. That is only true if every guest-facing
    // surface actually sends them — this is the test that keeps it true.
    $category = Category::query()->create(['name' => 'Tenders', 'color' => 'blue']);
    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $staff = User::factory()->create();

    $standalone = publicListingFile(['name' => 'Standalone']);
    $standalone->categories()->attach($category->id);

    $inGroup = publicListingFile(['name' => 'In Group']);
    $inGroup->categories()->attach($category->id);
    $this->actingAs($staff)->post("/files/{$inGroup->id}/assignments", ['type' => 'group', 'id' => $group->id]);
    auth()->logout();

    $expected = [['id' => $category->id, 'name' => 'Tenders', 'color' => 'blue']];

    $indexProps = publicPageProps($this->get('/public'));
    expect(collect($indexProps['files'])->firstWhere('name', 'Standalone')['categories'] ?? null)->toBe($expected);

    $groupProps = publicPageProps($this->get("/public/{$group->slug}"));
    expect(collect($groupProps['files'])->firstWhere('name', 'In Group')['categories'] ?? null)->toBe($expected);

    $this->get(route('public.file', ['public', $standalone->slug]))
        ->assertInertia(fn ($page) => $page->where('file.categories', $expected));
});

test('an uncategorised public file sends an empty list, not a missing key', function () {
    $file = publicListingFile(['name' => 'Bare']);

    $props = publicPageProps($this->get('/public'));
    expect(collect($props['files'])->firstWhere('name', 'Bare')['categories'] ?? null)->toBe([]);

    $this->get(route('public.file', ['public', $file->slug]))
        ->assertInertia(fn ($page) => $page->where('file.categories', []));
});

test('the public listings load categories in one query instead of one per row', function () {
    $category = Category::query()->create(['name' => 'Tenders']);
    foreach (range(1, 5) as $i) {
        publicListingFile(['name' => "File {$i}"])->categories()->attach($category->id);
    }

    DB::enableQueryLog();
    $this->get('/public')->assertOk();
    $categoryQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'category_file'))
        ->count();
    DB::disableQueryLog();

    expect($categoryQueries)->toBe(1);
});
