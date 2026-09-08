<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Files\Sharing\CreateShareLink;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * Which public URLs a client is shown in their own portal.
 *
 * The rule is narrow on purpose: a link this client created, on a file
 * this client uploaded. Both halves. A link somebody else minted on a
 * file shared *with* them is that person's decision about who may reach
 * the file, and showing the recipient the URL would quietly turn "you may
 * download this" into "you may pass this on to anyone".
 *
 * Asserted on the rendered props rather than on the resolver alone,
 * because a theme reading `share_url` off the row is trusting that the
 * narrowing already happened.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::Theme, 'default');
});

function rowFor(User $client, string $name): array
{
    $row = null;

    test()->actingAs($client)->get(route('my-files.index'))->assertInertia(
        function (AssertableInertia $page) use ($name, &$row) {
            $row = collect($page->toArray()['props']['files'])->firstWhere('name', $name);
        },
    );

    expect($row)->not->toBeNull("No row named {$name} in the portal listing.");

    return $row;
}

test('a client is shown the link on a file they uploaded', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Mine']);
    $link = app(CreateShareLink::class)->for($file, $client);

    expect(rowFor($client, 'Mine')['share_url'])->toBe(route('share.show', $link->token));
});

test('a client is not shown a staff link on a file shared with them', function () {
    // The disclosure this whole class exists to prevent.
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Theirs']);
    shareFileWith($file, $client);
    app(CreateShareLink::class)->for($file, $this->admin);

    expect(rowFor($client, 'Theirs')['share_url'])->toBeNull();
});

test('a client is not shown a staff link on a file they uploaded themselves', function () {
    // The case that isolates the second half of the rule: the file *is*
    // theirs, so ownership alone would let this through. A link staff
    // minted is staff's decision about who may reach the file — it may
    // exist for a reason the customer is not part of, and on the shared
    // instance it may sit beside the one link they were promised.
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Mine, staff link']);
    app(CreateShareLink::class)->for($file, $this->admin);

    expect(rowFor($client, 'Mine, staff link')['share_url'])->toBeNull();
});

test('a staff link never displaces the client\'s own', function () {
    // Ordering is by id, so a staff link minted first would be the one
    // an unfiltered lookup returned.
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Both links']);
    app(CreateShareLink::class)->for($file, $this->admin);
    $own = app(CreateShareLink::class)->for($file, $client);

    expect(rowFor($client, 'Both links')['share_url'])->toBe(route('share.show', $own->token));
});

test('a client is not shown their own link on a file that is no longer theirs', function () {
    // Both halves of the rule, not either: a link they minted before the
    // file was reassigned is not a link to a file they still own.
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Reassigned']);
    app(CreateShareLink::class)->for($file, $client);

    $file->update(['uploaded_by' => $this->admin->id]);
    shareFileWith($file, $client);

    expect(rowFor($client, 'Reassigned')['share_url'])->toBeNull();
});

test('one client is never shown another client\'s link', function () {
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();

    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Shared with both']);
    shareFileWith($file, $client);
    shareFileWith($file, $other);
    app(CreateShareLink::class)->for($file, $other);

    expect(rowFor($client, 'Shared with both')['share_url'])->toBeNull();
});

test('a file with no link says so rather than inventing one', function () {
    $client = User::factory()->client()->create();
    File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Unlinked']);

    expect(rowFor($client, 'Unlinked')['share_url'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| A link that would not work
|--------------------------------------------------------------------------
|
| The only thing a client can do with this is copy it. A URL that answers
| "this link has expired" is worse than no URL at all.
*/

test('an expired link is left out', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Expired']);
    app(CreateShareLink::class)->for($file, $client, expiresAt: now()->subDay());

    expect(rowFor($client, 'Expired')['share_url'])->toBeNull();
});

test('a spent link is left out', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Spent']);
    $link = app(CreateShareLink::class)->for($file, $client, maxDownloads: 1);
    $link->update(['downloads_count' => 1]);

    expect(rowFor($client, 'Spent')['share_url'])->toBeNull();
});

test('a live link is still shown when a dead one sits beside it', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Two links']);
    app(CreateShareLink::class)->for($file, $client, expiresAt: now()->subDay());
    $live = app(CreateShareLink::class)->for($file, $client);

    expect(rowFor($client, 'Two links')['share_url'])->toBe(route('share.show', $live->token));
});

test('the listing costs one query for the links however many rows it has', function () {
    $client = User::factory()->client()->create();

    foreach (range(1, 5) as $n) {
        $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => "File {$n}"]);
        app(CreateShareLink::class)->for($file, $client);
    }

    $queries = 0;
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'share_links')) {
            $queries++;
        }
    });

    $this->actingAs($client)->get(route('my-files.index'))->assertOk();

    expect($queries)->toBe(1);
});

test('a client with no files of their own asks nothing at all', function () {
    // The empty case has no ids to look up, so it must not run a query
    // with an empty IN clause on every listing.
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Theirs']);
    shareFileWith($file, $client);

    $queries = 0;
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'share_links')) {
            $queries++;
        }
    });

    $this->actingAs($client)->get(route('my-files.index'))->assertOk();

    expect($queries)->toBe(0);
});

test('the link the client is shown really works', function () {
    // The end of the chain: what is rendered is a URL a stranger can
    // fetch. Everything above tests who is told; this tests that being
    // told is worth something.
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Fetchable']);
    app(CreateShareLink::class)->for($file, $client);

    $url = rowFor($client, 'Fetchable')['share_url'];

    $this->post(route('logout'));

    $this->get($url)->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->component('share/show')->where('status', 'active'),
    );

    expect(ShareLink::query()->count())->toBe(1);
});
