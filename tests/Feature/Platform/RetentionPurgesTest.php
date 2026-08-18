<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Platform\Capabilities\Edition;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/**
 * The two tables that grow on their own — failed queue jobs and read
 * notifications — and the daily purges that now keep them from growing
 * forever on an installation nobody tends.
 */
beforeEach(function () {
    config()->set('projectsend.edition', Edition::Community);
    $this->admin = User::factory()->create();

    // Settings outlive the per-test rollback in the cache, so both
    // windows are stated rather than assumed.
    $settings = app(Settings::class);
    $settings->set(Setting::FailedJobRetentionDays, 30);
    $settings->set(Setting::NotificationRetentionDays, 90);
});

function insertFailedJob(string $failedAt): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'Something went wrong.',
        'failed_at' => $failedAt,
    ]);

    return $uuid;
}

test('it deletes failed jobs past the window and keeps the recent ones', function () {
    $old = insertFailedJob(now()->subDays(45)->toDateTimeString());
    $recent = insertFailedJob(now()->subDays(3)->toDateTimeString());

    $this->artisan('projectsend:purge-failed-jobs')->assertSuccessful();

    expect(DB::table('failed_jobs')->where('uuid', $old)->exists())->toBeFalse()
        ->and(DB::table('failed_jobs')->where('uuid', $recent)->exists())->toBeTrue();
});

test('a retention of zero keeps every failed job, however old', function () {
    app(Settings::class)->set(Setting::FailedJobRetentionDays, 0);
    $old = insertFailedJob(now()->subYears(3)->toDateTimeString());

    $this->artisan('projectsend:purge-failed-jobs')->assertSuccessful();

    expect(DB::table('failed_jobs')->where('uuid', $old)->exists())->toBeTrue();
});

test('it deletes read notifications past the window', function () {
    $old = InAppNotification::query()->create([
        'user_id' => $this->admin->id,
        'type' => 'file_shared',
        'read_at' => now()->subDays(100),
        'created_at' => now()->subDays(100),
    ]);
    $recent = InAppNotification::query()->create([
        'user_id' => $this->admin->id,
        'type' => 'file_shared',
        'read_at' => now()->subDay(),
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('projectsend:purge-notifications')->assertSuccessful();

    expect(InAppNotification::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(InAppNotification::query()->whereKey($recent->id)->exists())->toBeTrue();
});

test('an unread notification survives at any age', function () {
    $ancient = InAppNotification::query()->create([
        'user_id' => $this->admin->id,
        'type' => 'file_shared',
        'read_at' => null,
        'created_at' => now()->subYears(2),
    ]);

    $this->artisan('projectsend:purge-notifications')->assertSuccessful();

    expect(InAppNotification::query()->whereKey($ancient->id)->exists())->toBeTrue();
});

test('a retention of zero keeps read notifications too', function () {
    app(Settings::class)->set(Setting::NotificationRetentionDays, 0);
    $old = InAppNotification::query()->create([
        'user_id' => $this->admin->id,
        'type' => 'file_shared',
        'read_at' => now()->subYears(2),
        'created_at' => now()->subYears(2),
    ]);

    $this->artisan('projectsend:purge-notifications')->assertSuccessful();

    expect(InAppNotification::query()->whereKey($old->id)->exists())->toBeTrue();
});

test('both windows are set from the scheduler screen', function () {
    $this->actingAs($this->admin)->get('/system/settings/scheduler')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('retention.failed_jobs', 30)
            ->where('retention.notifications', 90),
    );

    $this->actingAs($this->admin)
        ->patch('/system/settings/scheduler/retention', ['failed_jobs' => 7, 'notifications' => 0])
        ->assertRedirect();

    $settings = app(Settings::class);
    expect($settings->get(Setting::FailedJobRetentionDays))->toBe(7)
        ->and($settings->get(Setting::NotificationRetentionDays))->toBe(0);
});

test('a negative window is refused rather than stored', function () {
    $this->actingAs($this->admin)
        ->patch('/system/settings/scheduler/retention', ['failed_jobs' => -1, 'notifications' => 90])
        ->assertSessionHasErrors('failed_jobs');

    expect(app(Settings::class)->get(Setting::FailedJobRetentionDays))->toBe(30);
});

test('the cloud edition has no scheduler screen to set them on', function () {
    config()->set('projectsend.edition', Edition::Cloud);

    $this->actingAs($this->admin)
        ->patch('/system/settings/scheduler/retention', ['failed_jobs' => 7, 'notifications' => 7])
        ->assertNotFound();
});
