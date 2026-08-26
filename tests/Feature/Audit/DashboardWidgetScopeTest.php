<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * The dashboard's staff widgets against the two boundaries the rest of
 * the application applies: ActivityLogScope for the log, and
 * StaffLibraryScope for files and clients.
 *
 * The viewer throughout is a stock Client Manager — the seeded role that
 * ships with view_actions_log and view_statistics and is client-scoped,
 * so none of this needs a custom role to reproduce.
 */
beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    $this->manager = User::factory()->role(SystemRole::ClientManager)->create();
    $this->mine = User::factory()->client()->create(['name' => 'My Own Client']);
    $this->manager->assignedClients()->sync([$this->mine->id]);

    $this->stranger = User::factory()->client()->create(['name' => 'Stranger Client Ltd']);
});

/** @return array<string, mixed> */
function dashboardProps(User $viewer): array
{
    $props = [];

    test()->actingAs($viewer)->get('/dashboard')->assertInertia(function (AssertableInertia $page) use (&$props) {
        $props = $page->toArray()['props'];
    });

    return $props;
}

/** @return list<string> */
function activityNames(array $entries): array
{
    return collect($entries)->flatMap(fn (array $entry): array => array_values($entry['replacements'] ?? []))->all();
}

it('keeps the recent activity widget inside the same scope as the activity page', function () {
    $secret = uploadNamedFile($this->admin, 'Q4-payroll-secret');
    shareFileWith($secret, $this->stranger);
    $this->actingAs($this->stranger)->get("/files/{$secret->id}/download")->assertOk();

    $ours = uploadNamedFile($this->admin, 'brochure');
    shareFileWith($ours, $this->mine);

    // The premise: the file itself is out of reach.
    $this->actingAs($this->manager)->get("/files/{$secret->id}/download")->assertForbidden();

    $names = activityNames(dashboardProps($this->manager)['recent'] ?? []);

    expect($names)->not->toContain('Q4-payroll-secret')
        ->and($names)->not->toContain('Stranger Client Ltd')
        ->and($names)->toContain('brochure');
});

it('keeps the largest files widget inside the viewer library', function () {
    $secret = uploadNamedFile($this->admin, 'Q4-payroll-secret');
    $secret->forceFill(['size' => 999_999_999])->save();

    $ours = uploadNamedFile($this->admin, 'brochure');
    shareFileWith($ours, $this->mine);

    $names = collect(dashboardProps($this->manager)['largest_files'] ?? [])->pluck('name')->all();

    expect($names)->not->toContain('Q4-payroll-secret')
        ->and($names)->toContain('brochure');
});

it('keeps the expired files widget, and its count, inside the viewer library', function () {
    $secret = uploadNamedFile($this->admin, 'expired-merger-plan');
    $secret->forceFill(['expires_at' => now()->subDay()])->save();

    $ours = uploadNamedFile($this->manager, 'expired-brochure');
    $ours->forceFill(['expires_at' => now()->subDay()])->save();

    $widget = dashboardProps($this->manager)['expired_files'] ?? [];
    $names = collect($widget['files'] ?? [])->pluck('name')->all();

    expect($names)->not->toContain('expired-merger-plan')
        ->and($names)->toContain('expired-brochure')
        ->and($widget['count'] ?? null)->toBe(1);
});

/**
 * StaffLibraryScope reaches an assigned client's content through
 * File::scopeVisibleToClient, which ends in notExpired() — so an expired
 * file belonging to an assigned client is not in a scoped viewer's
 * library, and the file listing does not show it either. The widget now
 * agrees with the listing instead of being the one place that didn't.
 */
it('agrees with the file listing about an assigned client expired file', function () {
    $theirs = uploadNamedFile($this->admin, 'expired-client-file');
    shareFileWith($theirs, $this->mine);
    $theirs->forceFill(['expires_at' => now()->subDay()])->save();

    $listing = $this->actingAs($this->manager)->get('/files');
    $widget = dashboardProps($this->manager)['expired_files'] ?? [];

    expect(str_contains($listing->getContent(), 'expired-client-file'))->toBeFalse()
        ->and(collect($widget['files'] ?? [])->pluck('name')->all())->not->toContain('expired-client-file')
        ->and($widget['count'] ?? null)->toBe(0);
});

it('names only the viewer own clients in the storage widget', function () {
    foreach ([[$this->mine, 'mine'], [$this->stranger, 'theirs']] as [$client, $label]) {
        $file = uploadNamedFile($this->admin, "upload-{$label}");
        $file->forceFill(['uploaded_by' => $client->id, 'size' => 5_000_000])->save();
    }

    $names = collect(dashboardProps($this->manager)['top_clients_by_storage'] ?? [])->pluck('name')->all();

    expect($names)->not->toContain('Stranger Client Ltd')
        ->and($names)->toContain('My Own Client');
});

it('leaves an unscoped staff dashboard installation-wide', function () {
    $staff = User::factory()->role(SystemRole::AccountManager)->create();

    $secret = uploadNamedFile($this->admin, 'Q4-payroll-secret');
    $secret->forceFill(['size' => 999_999_999, 'expires_at' => now()->subDay()])->save();
    $secret->forceFill(['uploaded_by' => $this->stranger->id])->save();

    expect($staff->isClientScoped())->toBeFalse();

    $props = dashboardProps($staff);

    expect(activityNames($props['recent'] ?? []))->toContain('Q4-payroll-secret')
        ->and(collect($props['largest_files'] ?? [])->pluck('name')->all())->toContain('Q4-payroll-secret')
        ->and(collect($props['expired_files']['files'] ?? [])->pluck('name')->all())->toContain('Q4-payroll-secret')
        ->and(collect($props['top_clients_by_storage'] ?? [])->pluck('name')->all())->toContain('Stranger Client Ltd');
});

it('still shows a scoped viewer their own actions', function () {
    $own = uploadNamedFile($this->manager, 'my-own-upload');

    expect(activityNames(dashboardProps($this->manager)['recent'] ?? []))->toContain('my-own-upload')
        ->and(collect(dashboardProps($this->manager)['largest_files'] ?? [])->pluck('name')->all())
        ->toContain('my-own-upload')
        ->and($own->uploaded_by)->toBe($this->manager->id);
});
