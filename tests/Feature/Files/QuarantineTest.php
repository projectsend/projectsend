<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Files\Scanning\ScanVerdict;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    app(Settings::class)->set(Setting::VirusScanningEnabled, true);
    app(Settings::class)->set(Setting::VirusScannerAddress, 'tcp://scanner.test:3310');
    app(Settings::class)->set(Setting::VirusUnscannablePolicy, 'allow');
    app(Settings::class)->set(Setting::VirusScannerDownPolicy, 'allow');
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, false);
});

function quarantined(array $overrides = []): File
{
    $path = 'uploads/'.Str::uuid()->toString().'.pdf';
    Storage::disk('files')->put($path, 'some bytes');

    return File::factory()->create(array_merge([
        'path' => $path,
        'disk' => 'files',
        'size' => 10,
        'scan_status' => ScanStatus::Infected,
        'scan_note' => 'Eicar-Test-Signature',
        'scanned_at' => now(),
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Who may open it
|--------------------------------------------------------------------------
*/

test('the quarantine screen needs its own permission', function () {
    $role = Role::query()->create(['name' => 'No release '.Str::random(4)]);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => Permission::DeleteOthersFiles->value]);
    $staff = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staff)->get('/files/quarantine')->assertForbidden();

    // Deleting a file is not the same judgement as deciding the scanner
    // was wrong, which is why this permission exists separately.
    $file = quarantined();
    $this->actingAs($staff)->post("/files/{$file->id}/release", ['reason' => 'looks fine'])->assertForbidden();

    expect($file->refresh()->scan_status)->toBe(ScanStatus::Infected);
});

test('a client cannot reach it at all', function () {
    $client = User::factory()->client()->create();

    // EnsureStaff sends a client to their own dashboard rather than
    // answering 403 — what matters here is that the screen is not served.
    $this->actingAs($client)->get('/files/quarantine')->assertRedirect(route('dashboard'));
});

test('an administrator sees what is quarantined, and what got out first', function () {
    $uploader = User::factory()->client()->create(['name' => 'Cliente Uno']);
    $file = quarantined(['name' => 'Factura', 'uploaded_by' => $uploader->id, 'scan_was_available' => true]);

    $this->actingAs($this->admin)->get('/files/quarantine')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('files/quarantine')
            ->where('files.0.name', 'Factura')
            ->where('files.0.uploader', 'Cliente Uno')
            ->where('files.0.threat', 'Eicar-Test-Signature')
            ->where('files.0.was_available', true),
    );
});

/*
|--------------------------------------------------------------------------
| Releasing
|--------------------------------------------------------------------------
*/

test('releasing needs a reason, and records who gave it', function () {
    $file = quarantined();
    confirmPassword($this->admin);

    $this->actingAs($this->admin)->post("/files/{$file->id}/release", ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($file->refresh()->scan_status)->toBe(ScanStatus::Infected);

    $this->actingAs($this->admin)->post("/files/{$file->id}/release", ['reason' => 'False positive, reported upstream'])
        ->assertSessionHasNoErrors();

    $file->refresh();
    expect($file->scan_status)->toBe(ScanStatus::Released)
        ->and($file->released_by)->toBe($this->admin->id)
        ->and($file->released_at)->not->toBeNull();

    $entry = ActivityLog::query()->where('action', Action::FileReleased)->sole();
    expect($entry->actor_id)->toBe($this->admin->id)
        ->and($entry->context['reason'])->toBe('False positive, reported upstream');
});

test('a released file downloads again', function () {
    $file = quarantined(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->get("/files/{$file->id}/download")->assertStatus(423);

    confirmPassword($this->admin);
    $this->actingAs($this->admin)->post("/files/{$file->id}/release", ['reason' => 'Known false positive']);

    $this->actingAs($this->admin)->get("/files/{$file->id}/download")->assertOk();
});

test('releasing asks for the password first', function () {
    $file = quarantined();

    // No confirmPassword() here: the middleware should send the request
    // to the confirmation screen rather than release the file.
    $this->actingAs($this->admin)->post("/files/{$file->id}/release", ['reason' => 'sure'])
        ->assertRedirect(route('password.confirm'));

    expect($file->refresh()->scan_status)->toBe(ScanStatus::Infected);
});

test('a file that is not quarantined cannot be released', function () {
    $file = quarantined(['scan_status' => ScanStatus::Clean, 'scan_note' => null]);
    confirmPassword($this->admin);

    $this->actingAs($this->admin)->post("/files/{$file->id}/release", ['reason' => 'why not'])->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Who is told
|--------------------------------------------------------------------------
*/

test('a quarantined file tells the administrators and the uploader, and nobody else', function () {
    $uploader = User::factory()->client()->create();
    $bystander = User::factory()->client()->create();
    $file = quarantined(['scan_status' => ScanStatus::Pending, 'scan_note' => null, 'uploaded_by' => $uploader->id]);

    app(App\Modules\Files\Scanning\ScanPolicy::class)->record($file, ScanVerdict::infected('Eicar-Test-Signature'));

    $told = InAppNotification::query()->pluck('type', 'user_id');

    expect($told[$this->admin->id] ?? null)->toBe('file_quarantined')
        ->and($told[$uploader->id] ?? null)->toBe('upload_blocked')
        ->and($told->has($bystander->id))->toBeFalse();
});

test('a staff member who uploaded it is told once, as staff', function () {
    $file = quarantined(['scan_status' => ScanStatus::Pending, 'scan_note' => null, 'uploaded_by' => $this->admin->id]);

    app(App\Modules\Files\Scanning\ScanPolicy::class)->record($file, ScanVerdict::infected('Eicar-Test-Signature'));

    expect(InAppNotification::query()->where('user_id', $this->admin->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| A client-scoped staff member
|--------------------------------------------------------------------------
*/

test('a client-scoped staff member sees, releases and hears about only their own clients\' files', function () {
    $role = Role::query()->create(['name' => 'Reps '.Str::random(4), 'client_scoped' => true]);
    RolePermission::query()->create(['role_id' => $role->id, 'permission' => Permission::ReleaseQuarantinedFiles->value]);
    $rep = User::factory()->create(['role_id' => $role->id]);

    $mine = User::factory()->client()->create();
    $stranger = User::factory()->client()->create();
    $rep->assignedClients()->sync([$mine->id]);

    $ours = quarantined(['name' => 'Ours', 'uploaded_by' => $mine->id]);
    $theirs = quarantined(['name' => 'Theirs', 'uploaded_by' => $stranger->id]);

    // The permission alone showed every quarantined file on the
    // installation, and released one from a client this person could not
    // otherwise open.
    $this->actingAs($rep)->get('/files/quarantine')->assertInertia(
        fn (AssertableInertia $page) => $page->has('files', 1)->where('files.0.name', 'Ours'),
    );

    confirmPassword($rep);
    $this->actingAs($rep)->post("/files/{$theirs->id}/release", ['reason' => 'not mine'])->assertNotFound();
    expect($theirs->refresh()->scan_status)->toBe(ScanStatus::Infected);

    $this->actingAs($rep)->post("/files/{$ours->id}/release", ['reason' => 'false positive'])->assertSessionHasNoErrors();
    expect($ours->refresh()->scan_status)->toBe(ScanStatus::Released);

    $policy = app(App\Modules\Files\Scanning\ScanPolicy::class);
    $newTheirs = quarantined(['scan_status' => ScanStatus::Pending, 'scan_note' => null, 'uploaded_by' => $stranger->id]);
    $newOurs = quarantined(['scan_status' => ScanStatus::Pending, 'scan_note' => null, 'uploaded_by' => $mine->id]);
    $policy->record($newTheirs, ScanVerdict::infected('Eicar-Test-Signature'));
    $policy->record($newOurs, ScanVerdict::infected('Eicar-Test-Signature'));

    expect(InAppNotification::query()->where('user_id', $rep->id)->pluck('subject_id')->all())->toBe([$newOurs->id])
        // An unscoped administrator still hears about both.
        ->and(InAppNotification::query()->where('user_id', $this->admin->id)->count())->toBe(2);
});

