<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Versions\FileVersionLinks;
use App\Modules\Files\Versions\FileVersions;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * The rule everything hangs off: a version link is disclosed only to a
 * viewer who can independently see BOTH files.
 *
 * These assert the rendered props rather than the presenter in isolation,
 * because a theme reading `version` off the row is trusting that the
 * filtering already happened.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->versions = app(FileVersions::class);
    app(Settings::class)->set(Setting::Theme, 'default');
});

test('a client who can see both files is told about the link', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev D']);

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    $this->actingAs($client)->get(route('my-files.index'))->assertInertia(
        function (AssertableInertia $page) {
            $files = collect($page->toArray()['props']['files']);

            expect($files->firstWhere('name', 'Rev C')['version']['next']['name'])->toBe('Rev D')
                ->and($files->firstWhere('name', 'Rev D')['version']['previous']['name'])->toBe('Rev C');
        },
    );
});

test('a client is never told about a counterpart that is expired for them', function () {
    $client = User::factory()->client()->create();

    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'Rev D',
        'expires_at' => now()->subDay(),
    ]);

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    // Rev D is past its expiry, so the client cannot reach it — and must
    // not be told it exists either.
    $this->actingAs($client)->get(route('my-files.index'))->assertInertia(
        function (AssertableInertia $page) {
            $files = collect($page->toArray()['props']['files']);

            expect($files->pluck('name')->all())->toBe(['Rev C'])
                ->and($files->firstWhere('name', 'Rev C')['version']['next'])->toBeNull();
        },
    );
});

test('staff still see the link to a file the client cannot reach', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create([
        'uploaded_by' => $this->admin->id,
        'name' => 'Rev D',
        'expires_at' => now()->subDay(),
    ]);

    $this->versions->link($revision, $original, $this->admin);

    // Expiry hides a file from clients, never from staff — so the two
    // audiences legitimately get different answers from the same rule.
    $this->actingAs($this->admin)->get(route('files.edit', $original))->assertInertia(
        fn (AssertableInertia $page) => $page->where('file.version.next.name', 'Rev D'),
    );
});

test('the staff library sends the version links on every row', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev D']);

    $this->versions->link($revision, $original, $this->admin);

    $this->actingAs($this->admin)->get(route('files.index'))->assertInertia(
        function (AssertableInertia $page) {
            $files = collect($page->toArray()['props']['files']);

            expect($files->firstWhere('name', 'Rev C')['version']['next']['name'])->toBe('Rev D')
                ->and($files->firstWhere('name', 'Rev D')['version']['previous']['name'])->toBe('Rev C');
        },
    );
});

test('the details panel reports the links and where sharing really lives', function () {
    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev D']);

    $this->versions->link($revision, $original, $this->admin);

    $response = $this->actingAs($this->admin)->getJson(route('files.details', $revision));

    $response->assertOk();

    expect($response->json('version.previous.name'))->toBe('Rev C')
        ->and($response->json('sharing_root.name'))->toBe('Rev C');
});

test('the details panel reports no sharing root for an ordinary file', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)->getJson(route('files.details', $file));

    expect($response->json('sharing_root'))->toBeNull()
        ->and($response->json('version.previous'))->toBeNull()
        ->and($response->json('version.next'))->toBeNull();
});

test('every portal theme receives the version prop on its file rows', function (string $theme) {
    app(Settings::class)->set(Setting::Theme, $theme);

    $client = User::factory()->client()->create();
    $original = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev C']);
    $revision = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Rev D']);

    shareFileWith($original, $client);
    $this->versions->link($revision, $original, $this->admin);

    $this->actingAs($client)->get(route('my-files.index'))->assertInertia(
        fn (AssertableInertia $page) => $page->component("portal/themes/{$theme}/my-files")
            ->has('files.0.version'),
    );
})->with(['default', 'compact', 'drive', 'gallery']);

test('resolving version links for a page of files does not scale with the row count', function () {
    $client = User::factory()->client()->create();

    // 10 linked pairs: a per-row implementation would issue queries in
    // proportion to this, a batched one would not.
    foreach (range(1, 10) as $i) {
        $original = File::factory()->create(['uploaded_by' => $this->admin->id]);
        $revision = File::factory()->create(['uploaded_by' => $this->admin->id]);
        shareFileWith($original, $client);
        $this->versions->link($revision, $original, $this->admin);
    }

    $links = app(FileVersionLinks::class);
    $files = File::query()->limit(20)->get();

    DB::enableQueryLog();
    $links->forMany($files, $client);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Two for the candidates, one for the visibility filter, plus whatever
    // visibleToClient itself needs — the point is that it is a constant,
    // not that it is exactly three.
    expect($queries)->toBeLessThan(10);
});
