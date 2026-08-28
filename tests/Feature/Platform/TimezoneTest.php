<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Localization\TimezoneRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    app(Settings::class)->set(Setting::Theme, 'default');

    // The settings cache survives the per-test DB rollback, so an
    // installation zone left over from a neighbouring test would decide
    // what "the default" means here. Same reason LocalizationTest pins
    // the locale settings in its own beforeEach.
    app(Settings::class)->set(Setting::Timezone, '');
});

function timezones(): TimezoneRegistry
{
    return app(TimezoneRegistry::class);
}

// ---------------------------------------------------------------- resolution

test('an account with no timezone of its own falls back to the installation setting', function () {
    app(Settings::class)->set(Setting::Timezone, 'Europe/Madrid');

    $user = User::factory()->create(['timezone' => null]);

    expect(timezones()->resolve($user))->toBe('Europe/Madrid');
});

test('an account timezone wins over the installation setting', function () {
    app(Settings::class)->set(Setting::Timezone, 'Europe/Madrid');

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    expect(timezones()->resolve($user))->toBe('America/Argentina/Buenos_Aires');
});

test('an anonymous visitor gets the installation setting', function () {
    app(Settings::class)->set(Setting::Timezone, 'Pacific/Auckland');

    expect(timezones()->resolve(null))->toBe('Pacific/Auckland');
});

test('an empty setting defers to APP_TIMEZONE', function () {
    config()->set('app.timezone', 'Asia/Tokyo');

    expect(timezones()->default())->toBe('Asia/Tokyo');
});

test('the last resort is UTC', function () {
    config()->set('app.timezone', 'Not/AZone');

    expect(timezones()->default())->toBe('UTC');
});

// A zone that was real when it was saved can stop existing under the
// host's next tzdata update. Degrading matters more than being right:
// throwing here would take down every page that prints a date.
test('a stored zone tzdata no longer knows degrades instead of throwing', function () {
    app(Settings::class)->set(Setting::Timezone, 'Europe/Madrid');

    $user = User::factory()->create(['timezone' => 'Mars/Olympus_Mons']);

    expect(timezones()->resolve($user))->toBe('Europe/Madrid');
});

test('an unusable installation setting degrades too', function () {
    app(Settings::class)->set(Setting::Timezone, 'Mars/Olympus_Mons');
    config()->set('app.timezone', 'UTC');

    expect(timezones()->resolve(null))->toBe('UTC');
});

test('the picker list is offered with a readable label and the offset it is on today', function () {
    $madrid = collect(timezones()->options())->firstWhere('value', 'Europe/Madrid');

    expect($madrid)->not->toBeNull()
        // The region stays in the label so one search box finds it by
        // either half.
        ->and($madrid['label'])->toBe('Europe / Madrid')
        ->and($madrid['offset'])->toMatch('/^UTC[+-]\d{2}:\d{2}$/');

    $buenosAires = collect(timezones()->options())->firstWhere('value', 'America/Argentina/Buenos_Aires');

    expect($buenosAires['label'])->toBe('America / Argentina / Buenos Aires')
        ->and($buenosAires['offset'])->toBe('UTC-03:00');
});

// ------------------------------------------------------------------ plumbing

test('the resolved zone and whether it was chosen are shared with every page', function () {
    app(Settings::class)->set(Setting::Timezone, 'Europe/Madrid');

    $user = User::factory()->create(['timezone' => null]);

    $this->actingAs($user)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('timezone', 'Europe/Madrid')
            // False is what tells the frontend detector nobody has been
            // asked yet.
            ->where('timezone_is_explicit', false),
    );

    $user->update(['timezone' => 'Asia/Tokyo']);

    $this->actingAs($user)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('timezone', 'Asia/Tokyo')
            ->where('timezone_is_explicit', true),
    );
});

// The profile form saves it with everything else, so the screen keeps one
// Save button; PUT /timezone below stays for the browser detection, which
// has no form around it.
test('the profile form saves the timezone alongside the rest of the profile', function () {
    $user = User::factory()->create(['timezone' => null]);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Renamed Person',
        'email' => $user->email,
        'timezone' => 'Asia/Tokyo',
    ])->assertRedirect();

    $user->refresh();

    expect($user->timezone)->toBe('Asia/Tokyo')
        ->and($user->name)->toBe('Renamed Person');
});

test('a profile save that omits the timezone leaves the stored one alone', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
    ])->assertRedirect();

    expect($user->fresh()->timezone)->toBe('Asia/Tokyo');
});

test('the profile form refuses a timezone the registry does not offer', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Madrid']);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'timezone' => 'Mars/Olympus_Mons',
    ])->assertSessionHasErrors('timezone');

    expect($user->fresh()->timezone)->toBe('Europe/Madrid');
});

test('a signed-in account can set its own timezone', function () {
    $user = User::factory()->create(['timezone' => null]);

    $this->actingAs($user)
        ->put('/timezone', ['timezone' => 'America/Argentina/Buenos_Aires'])
        ->assertRedirect();

    expect($user->fresh()->timezone)->toBe('America/Argentina/Buenos_Aires');
});

test('a client can set their own timezone too', function () {
    User::factory()->create();
    $client = User::factory()->create(['type' => UserType::Client, 'timezone' => null]);

    $this->actingAs($client)->put('/timezone', ['timezone' => 'Asia/Tokyo'])->assertRedirect();

    expect($client->fresh()->timezone)->toBe('Asia/Tokyo');
});

test('a timezone the registry does not offer is rejected', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Madrid']);

    $this->actingAs($user)
        ->put('/timezone', ['timezone' => 'Mars/Olympus_Mons'])
        ->assertSessionHasErrors('timezone');

    expect($user->fresh()->timezone)->toBe('Europe/Madrid');
});

test('staff can set the installation timezone from general settings', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/system/settings/general')->assertInertia(
        fn (AssertableInertia $page) => $page->where('timezone', 'UTC'),
    );

    $this->patch('/system/settings/general', [
        'site_name' => 'ProjectSend',
        'timezone' => 'Pacific/Auckland',
    ])->assertRedirect();

    expect(app(Settings::class)->get(Setting::Timezone))->toBe('Pacific/Auckland');
});

// -------------------------------------------------------- what it is applied to

test('the activity log filters on the viewer\'s calendar day, not the server\'s', function () {
    $staff = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    // Dated well past "now" so the entries every other part of the setup
    // logs (SetupCompleted, UserCreated) cannot land in the window and
    // make the counts below mean something else.
    app(ActivityLogger::class)->log(Action::FileDownloaded, $staff);

    $entry = ActivityLog::query()->latest('id')->firstOrFail();
    // 01:00 UTC on the 11th is 22:00 on the 10th in Buenos Aires, so
    // filtering to the 10th there has to find it — and filtering to the
    // 11th, which is what a UTC comparison would have matched, must not.
    $entry->forceFill(['created_at' => '2027-03-11 01:00:00'])->save();

    $this->actingAs($staff)
        ->get('/activity?from=2027-03-10&to=2027-03-10')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 1)->where('entries.0.id', $entry->id));

    $this->actingAs($staff)
        ->get('/activity?from=2027-03-11&to=2027-03-11')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 0));
});

test('a file expiry set as a date means the end of that day where it was set', function () {
    $staff = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $file = File::factory()->create(['uploaded_by' => $staff->id]);

    $this->actingAs($staff)->patch("/files/{$file->id}", [
        'name' => $file->name,
        'expires_at' => '2026-08-12',
    ])->assertRedirect();

    // 23:59:59 on the 12th in UTC-3 is 02:59:59 on the 13th in UTC.
    expect($file->fresh()->expires_at->toIso8601String())->toBe('2026-08-13T02:59:59+00:00');
});

test('a re-save from another timezone does not move an expiry nobody touched', function () {
    // The form is given the stored instant read back as a date in *this*
    // viewer's zone, and posts it again untouched with every other edit.
    // Deriving the instant from it unconditionally moved the expiry by the
    // difference between the two zones -- 19 hours here -- for the price of
    // a rename.
    $setter = User::factory()->create(['timezone' => 'Pacific/Auckland']);
    $other = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $file = File::factory()->create(['uploaded_by' => $setter->id]);

    $this->actingAs($setter)->patch("/files/{$file->id}", [
        'name' => $file->name,
        'expires_at' => '2026-09-12',
    ])->assertRedirect();

    $stored = $file->fresh()->expires_at->toIso8601String();

    // Both see the same calendar date, which is why the rename posts it back.
    $shown = $this->actingAs($other)->get("/files/{$file->id}")
        ->viewData('page')['props']['file']['expires_at'];
    expect($shown)->toBe('2026-09-12');

    $this->actingAs($other)->patch("/files/{$file->id}", [
        'name' => 'Renamed by somebody else',
        'expires_at' => $shown,
    ])->assertRedirect();

    expect($file->fresh()->name)->toBe('Renamed by somebody else')
        ->and($file->fresh()->expires_at->toIso8601String())->toBe($stored);
});

test('changing the date really does set it, in the zone of whoever changed it', function () {
    // The half that must not stop working: a genuine change is still read
    // as a day in the editor's own zone.
    $setter = User::factory()->create(['timezone' => 'Pacific/Auckland']);
    $other = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $file = File::factory()->create(['uploaded_by' => $setter->id]);

    $this->actingAs($setter)->patch("/files/{$file->id}", [
        'name' => $file->name,
        'expires_at' => '2026-09-12',
    ])->assertRedirect();

    $this->actingAs($other)->patch("/files/{$file->id}", [
        'name' => $file->name,
        'expires_at' => '2026-09-13',
    ])->assertRedirect();

    // 23:59:59 on the 13th in UTC-3.
    expect($file->fresh()->expires_at->toIso8601String())->toBe('2026-09-14T02:59:59+00:00');
});

test('clearing the date from another timezone still clears it', function () {
    $setter = User::factory()->create(['timezone' => 'Pacific/Auckland']);
    $other = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $file = File::factory()->create(['uploaded_by' => $setter->id]);

    $this->actingAs($setter)->patch("/files/{$file->id}", [
        'name' => $file->name,
        'expires_at' => '2026-09-12',
    ])->assertRedirect();

    $this->actingAs($other)->patch("/files/{$file->id}", [
        'name' => $file->name,
        'expires_at' => null,
    ])->assertRedirect();

    expect($file->fresh()->expires_at)->toBeNull();
});

test('an expiry date comes back out of the form as the date that went in', function () {
    $staff = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $file = File::factory()->create(['uploaded_by' => $staff->id]);

    $this->actingAs($staff)->patch("/files/{$file->id}", [
        'name' => $file->name,
        'expires_at' => '2026-08-12',
    ]);

    // The bug this pins down: read back in UTC the stored instant is the
    // 13th, and the picker would reopen on the wrong day.
    $this->actingAs($staff)->get("/files/{$file->id}")->assertInertia(
        fn (AssertableInertia $page) => $page->where('file.expires_at', '2026-08-12'),
    );
});
