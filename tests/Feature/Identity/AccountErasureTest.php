<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Erasure\AccountEraser;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('self-deletion schedules permanent erasure after the grace period', function () {
    // The grace period is a cached Setting (Cache::rememberForever
    // survives the per-test DB rollback) — reset explicitly rather than
    // assuming the code default is still what's cached.
    app(Settings::class)->set(Setting::AccountErasureGraceDays, 30);

    $client = User::factory()->client()->create();

    $this->actingAs($client)->delete('/settings/profile', ['password' => 'password']);

    $trashed = User::withTrashed()->findOrFail($client->id);
    expect($trashed->deleted_at)->not->toBeNull()
        ->and($trashed->erase_after?->isSameDay(now()->addDays(30)))->toBeTrue();
});

test('the purge command erases only accounts past their grace period', function () {
    $due = User::factory()->client()->create(['name' => 'Due Person']);
    $this->actingAs($due)->delete('/settings/profile', ['password' => 'password']);

    $notDue = User::factory()->client()->create();
    $this->actingAs($notDue)->delete('/settings/profile', ['password' => 'password']);

    // Admin-deleted accounts have no erase_after and are never purged.
    $adminDeleted = User::factory()->client()->create();
    $this->actingAs($this->admin)->delete("/clients/{$adminDeleted->id}");

    // Simulate the not-due one being deleted 10 days later.
    User::withTrashed()->whereKey($notDue->id)->update(['erase_after' => now()->addDays(40)]);

    $this->travel(31)->days();
    $this->artisan('projectsend:purge-erasures')->assertSuccessful();

    expect(User::withTrashed()->find($due->id))->toBeNull()
        ->and(User::withTrashed()->find($notDue->id))->not->toBeNull()
        ->and(User::withTrashed()->find($adminDeleted->id))->not->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::AccountErased)->count())->toBe(1);
});

test('erasure anonymizes every identifying snapshot in the activity log', function () {
    $client = User::factory()->client()->create(['name' => 'Erase Me']);

    // Generate entries where the person is actor and subject.
    $this->post('/login', ['email' => $client->email, 'password' => 'password']);
    $this->post('/logout');
    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => $client->name, 'email' => $client->email, 'active' => true,
    ]);
    $this->actingAs($client)->delete('/settings/profile', ['password' => 'password']);

    app(AccountEraser::class)->erase(User::withTrashed()->findOrFail($client->id));

    expect(ActivityLog::query()->where('actor_name', 'Erase Me')->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('subject_name', 'Erase Me')->exists())->toBeFalse();

    // The self-deletion entry carried the name in context: scrubbed too.
    $deleted = ActivityLog::query()->where('action', Action::UserDeleted)->sole();
    expect($deleted->context['name'] ?? null)->toBeNull();

    // Non-personal facts remain: entries, timestamps, account type.
    expect(ActivityLog::query()->where('action', Action::Login)->where('actor_type', 'client')->exists())->toBeTrue();

    // The log renders "(deleted account)" instead of an empty subject.
    $this->actingAs($this->admin)->get('/activity?action=user.updated')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('entries.0.replacements.subject', '(deleted account)'),
    );
});

test('the erase-account command performs immediate erasure by email', function () {
    $client = User::factory()->client()->create(['email' => 'gdpr@example.com', 'name' => 'GDPR Requester']);
    $this->post('/login', ['email' => 'gdpr@example.com', 'password' => 'password']);

    $this->artisan('projectsend:erase-account', ['email' => 'gdpr@example.com', '--force' => true])
        ->assertSuccessful();

    expect(User::withTrashed()->where('email', 'gdpr@example.com')->exists())->toBeFalse()
        ->and(ActivityLog::query()->where('actor_name', 'GDPR Requester')->exists())->toBeFalse();
});

test('the erase-account command fails cleanly for unknown emails', function () {
    $this->artisan('projectsend:erase-account', ['email' => 'nobody@example.com', '--force' => true])
        ->assertFailed();
});

test('erasure deletes the account\'s files by default (cascade)', function () {
    app(Settings::class)->set(Setting::AccountErasureContentAction, 'cascade_delete');

    $client = User::factory()->client()->create();
    $file = App\Modules\Files\Models\File::factory()->create(['uploaded_by' => $client->id]);

    app(AccountEraser::class)->erase($client);

    expect(App\Modules\Files\Models\File::find($file->id))->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::AccountContentCascadeDeleted->value)->exists())->toBeTrue();

    // The content-handling entry the erasure just wrote must not keep the
    // erased person's name — the same scrub that anonymizes the rest of the
    // log covers it.
    $entry = ActivityLog::query()->where('action', Action::AccountContentCascadeDeleted->value)->sole();
    expect($entry->context['name'] ?? null)->toBeNull();
});

test('erasure reassigns the account\'s files to the configured fallback', function () {
    $fallback = User::factory()->create(['name' => 'Inheritor', 'active' => true]);
    app(Settings::class)->set(Setting::AccountErasureContentAction, 'reassign');
    app(Settings::class)->set(Setting::AccountErasureReassignTo, $fallback->id);

    $client = User::factory()->client()->create();
    $file = App\Modules\Files\Models\File::factory()->create(['uploaded_by' => $client->id]);

    app(AccountEraser::class)->erase($client);

    $entry = ActivityLog::query()->where('action', Action::AccountContentReassigned->value)->sole();
    expect(App\Modules\Files\Models\File::find($file->id)?->uploaded_by)->toBe($fallback->id)
        // The erased person's name is scrubbed, but the inheritor's — a
        // still-active account — is a legitimate audit fact and stays.
        ->and($entry->context['name'] ?? null)->toBeNull()
        ->and($entry->context['target'] ?? null)->toBe('Inheritor');
});

test('reassign falls back to cascade when the target is no longer valid', function () {
    // Configured to reassign, but the chosen account has since been
    // deactivated — the safe choice is to delete, never to orphan.
    $gone = User::factory()->create(['active' => false]);
    app(Settings::class)->set(Setting::AccountErasureContentAction, 'reassign');
    app(Settings::class)->set(Setting::AccountErasureReassignTo, $gone->id);

    $client = User::factory()->client()->create();
    $file = App\Modules\Files\Models\File::factory()->create(['uploaded_by' => $client->id]);

    app(AccountEraser::class)->erase($client);

    expect(App\Modules\Files\Models\File::find($file->id))->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::AccountContentCascadeDeleted->value)->exists())->toBeTrue();
});
