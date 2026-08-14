<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Access\DownloadAllowance;
use App\Modules\Files\DownloadLimitScope;
use App\Modules\Files\Models\File;
use App\Modules\Identity\UserType;

/**
 * The rule itself, in isolation. The paths that consult it get their own
 * tests — the point of putting it in one object is that there is a
 * single place to be sure about, and six places that must ask.
 */
function recordDownload(File $file, ?User $actor, Action $action = Action::FileDownloaded): void
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

function allowance(): DownloadAllowance
{
    return app(DownloadAllowance::class);
}

it('leaves a file without a limit uncapped', function (): void {
    $file = File::factory()->create();
    $client = User::factory()->create(['type' => UserType::Client]);

    expect(allowance()->remaining($file, $client))->toBeNull()
        ->and(allowance()->allows($file, $client))->toBeTrue();
});

it('counts every download against a total limit, whoever made it', function (): void {
    $file = File::factory()->create(['download_limit' => 3, 'download_limit_scope' => DownloadLimitScope::Total]);
    $first = User::factory()->create(['type' => UserType::Client]);
    $second = User::factory()->create(['type' => UserType::Client]);

    recordDownload($file, $first);
    recordDownload($file, $second);

    expect(allowance()->remaining($file, $first))->toBe(1)
        ->and(allowance()->remaining($file, $second))->toBe(1);

    recordDownload($file, $second);

    expect(allowance()->allows($file, $first))->toBeFalse()
        ->and(allowance()->allows($file, $second))->toBeFalse();
});

it('gives each person their own allowance under a per-user limit', function (): void {
    $file = File::factory()->create(['download_limit' => 2, 'download_limit_scope' => DownloadLimitScope::PerUser]);
    $spent = User::factory()->create(['type' => UserType::Client]);
    $untouched = User::factory()->create(['type' => UserType::Client]);

    recordDownload($file, $spent);
    recordDownload($file, $spent);

    expect(allowance()->allows($file, $spent))->toBeFalse()
        ->and(allowance()->allows($file, $untouched))->toBeTrue()
        ->and(allowance()->remaining($file, $untouched))->toBe(2);
});

it('allows exactly the number set, not one fewer', function (): void {
    // v1 compared with >=, so a limit of 1 permits one download and
    // refuses the second. Getting this off by one would quietly halve
    // every imported limit.
    $file = File::factory()->create(['download_limit' => 1]);
    $client = User::factory()->create(['type' => UserType::Client]);

    expect(allowance()->allows($file, $client))->toBeTrue();

    recordDownload($file, $client);

    expect(allowance()->allows($file, $client))->toBeFalse();
});

it('exempts the uploader from being refused, but their downloads still fill a total limit', function (): void {
    $uploader = User::factory()->create();
    $file = File::factory()->create(['uploaded_by' => $uploader->id, 'download_limit' => 2]);
    $client = User::factory()->create(['type' => UserType::Client]);

    recordDownload($file, $uploader);
    recordDownload($file, $uploader);

    // The uploader is never refused their own file…
    expect(allowance()->allows($file, $uploader))->toBeTrue()
        ->and(allowance()->remaining($file, $uploader))->toBeNull();

    // …and under a Total limit their downloads are still downloads, so
    // they do come out of the shared pool. Under PerUser they would not
    // touch anyone else at all.
    expect(allowance()->allows($file, $client))->toBeFalse();
});

it('does not let one person eat another\'s per-user allowance', function (): void {
    $uploader = User::factory()->create();
    $file = File::factory()->create([
        'uploaded_by' => $uploader->id,
        'download_limit' => 1,
        'download_limit_scope' => DownloadLimitScope::PerUser,
    ]);
    $client = User::factory()->create(['type' => UserType::Client]);

    recordDownload($file, $uploader);
    recordDownload($file, $uploader);

    expect(allowance()->allows($file, $client))->toBeTrue();
});

it('falls back to the total for a visitor who is not signed in', function (): void {
    // "Per user" means nothing without a user. Rather than inventing one
    // from an IP address, an anonymous visitor is measured against the
    // whole file — so a per-user limit still caps a public file rather
    // than leaving it wide open.
    $file = File::factory()->create([
        'download_limit' => 2,
        'download_limit_scope' => DownloadLimitScope::PerUser,
    ]);

    expect(allowance()->allows($file, null))->toBeTrue();

    recordDownload($file, null, Action::PublicFileDownloaded);
    recordDownload($file, null, Action::PublicFileDownloaded);

    expect(allowance()->allows($file, null))->toBeFalse();
});

it('counts share link and public downloads, not just direct ones', function (): void {
    $file = File::factory()->create(['download_limit' => 3]);
    $client = User::factory()->create(['type' => UserType::Client]);

    recordDownload($file, null, Action::ShareLinkDownloaded);
    recordDownload($file, null, Action::PublicFileDownloaded);
    recordDownload($file, $client, Action::FileDownloaded);

    expect(allowance()->allows($file, $client))->toBeFalse();
});

it('does not count a preview as a download', function (): void {
    // Previews are refused once the limit is spent, but looking never
    // spends it.
    $file = File::factory()->create(['download_limit' => 1]);
    $client = User::factory()->create(['type' => UserType::Client]);

    recordDownload($file, $client, Action::FilePreviewed);

    expect(allowance()->allows($file, $client))->toBeTrue();
});

it('knows whether the installation uses limits at all', function (): void {
    expect(allowance()->isUsedAnywhere())->toBeFalse();

    File::factory()->create(['download_limit' => 5]);

    expect(allowance()->isUsedAnywhere())->toBeTrue();
});

it('adds no counts to a listing when nothing is limited', function (): void {
    File::factory()->count(2)->create();
    $client = User::factory()->create(['type' => UserType::Client]);

    $row = allowance()->withCounts(File::query(), $client)->first();

    expect($row->downloads_count ?? null)->toBeNull();
});

it('adds both the shared and the personal count when something is limited', function (): void {
    $file = File::factory()->create(['download_limit' => 5]);
    $mine = User::factory()->create(['type' => UserType::Client]);
    $theirs = User::factory()->create(['type' => UserType::Client]);

    recordDownload($file, $mine);
    recordDownload($file, $theirs);

    $row = allowance()->withCounts(File::query()->whereKey($file->id), $mine)->first();

    expect((int) $row->downloads_count)->toBe(2)
        ->and((int) $row->own_downloads_count)->toBe(1);
});
