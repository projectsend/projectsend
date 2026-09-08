<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * What a client is told about how often a file has been taken.
 *
 * "Did it arrive?" is the question, and on a hosted free account — where
 * a link is the whole of the sharing — the count is the only evidence
 * either way. But a download entry says somebody fetched the file, so a
 * count on a file shared with several clients tells each of them about
 * the others. Only the person who put the file there is entitled to it.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::Theme, 'default');
});

function downloadAt(File $file, ?User $actor, string $when, Action $action = Action::FileDownloaded): void
{
    ActivityLog::query()->create([
        'actor_id' => $actor?->id,
        'actor_name' => $actor?->name,
        'actor_type' => $actor?->type->value,
        'action' => $action,
        'subject_type' => $file->getMorphClass(),
        'subject_id' => $file->id,
        'created_at' => $when,
    ]);
}

function statsRow(User $client, string $name): array
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

test('a client is told how often their own file went out and when it last did', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Mine']);

    downloadAt($file, null, '2026-09-01 10:00:00', Action::ShareLinkDownloaded);
    downloadAt($file, null, '2026-09-06 18:30:00', Action::ShareLinkDownloaded);

    $downloads = statsRow($client, 'Mine')['downloads'];

    expect($downloads['count'])->toBe(2)
        ->and($downloads['last_at'])->toStartWith('2026-09-06T18:30:00');
});

test('every way a file can leave is counted, not just one of them', function () {
    // The same three actions DownloadAllowance counts. A number that left
    // out public-site downloads would quietly under-report exactly the
    // sharing this is meant to report on.
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Mine']);

    downloadAt($file, $this->admin, '2026-09-01 10:00:00', Action::FileDownloaded);
    downloadAt($file, null, '2026-09-02 10:00:00', Action::ShareLinkDownloaded);
    downloadAt($file, null, '2026-09-03 10:00:00', Action::PublicFileDownloaded);

    expect(statsRow($client, 'Mine')['downloads']['count'])->toBe(3);
});

test('an untouched file of their own says so, rather than saying nothing', function () {
    // Zero is an answer the customer came looking for. It has to be
    // distinguishable from "not yours to know", which is why the server
    // sends a zero here and a null below.
    $client = User::factory()->client()->create();
    File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Untouched']);

    expect(statsRow($client, 'Untouched')['downloads'])->toBe(['count' => 0, 'last_at' => null]);
});

test('a file shared with them carries no numbers at all', function () {
    // The disclosure this exists to prevent: a count on a file shared
    // with several people tells each of them about the others' activity.
    // Null, not zero — a zero would itself be a claim.
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Theirs']);
    shareFileWith($file, $client);
    shareFileWith($file, $other);

    downloadAt($file, $other, '2026-09-01 10:00:00');

    expect(statsRow($client, 'Theirs')['downloads'])->toBeNull();
});

test('nothing but a download is counted', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Mine']);

    downloadAt($file, $client, '2026-09-01 10:00:00', Action::FileUpdated);
    downloadAt($file, $client, '2026-09-02 10:00:00', Action::ShareLinkCreated);

    expect(statsRow($client, 'Mine')['downloads']['count'])->toBe(0);
});

test('one file\'s downloads never land on another\'s', function () {
    $client = User::factory()->client()->create();
    $one = File::factory()->create(['uploaded_by' => $client->id, 'name' => 'One']);
    File::factory()->create(['uploaded_by' => $client->id, 'name' => 'Two']);

    downloadAt($one, null, '2026-09-01 10:00:00', Action::ShareLinkDownloaded);

    expect(statsRow($client, 'One')['downloads']['count'])->toBe(1)
        ->and(statsRow($client, 'Two')['downloads']['count'])->toBe(0);
});

test('the listing costs one query for the counts however many rows it has', function () {
    $client = User::factory()->client()->create();

    foreach (range(1, 6) as $n) {
        $file = File::factory()->create(['uploaded_by' => $client->id, 'name' => "File {$n}"]);
        downloadAt($file, null, '2026-09-01 10:00:00', Action::ShareLinkDownloaded);
    }

    $queries = 0;
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'max(created_at)')) {
            $queries++;
        }
    });

    $this->actingAs($client)->get(route('my-files.index'))->assertOk();

    expect($queries)->toBe(1);
});

test('a client with no files of their own asks nothing at all', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Theirs']);
    shareFileWith($file, $client);

    $queries = 0;
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'max(created_at)')) {
            $queries++;
        }
    });

    $this->actingAs($client)->get(route('my-files.index'))->assertOk();

    expect($queries)->toBe(0);
});
