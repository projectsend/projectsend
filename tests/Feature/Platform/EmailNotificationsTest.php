<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\Notifications\AdminClientRegisteredNotification;
use App\Modules\Clients\Notifications\ClientAccountApprovedNotification;
use App\Modules\Clients\Notifications\ClientAccountDeniedNotification;
use App\Modules\Clients\Notifications\ClientAccountEditedNotification;
use App\Modules\Clients\Notifications\ClientWelcomeNotification;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Notifications\AdminClientUploadedNotification;
use App\Modules\Files\Notifications\FileSharedNotification;
use App\Modules\Groups\Models\Group;
use App\Modules\Groups\Models\MembershipRequest;
use App\Modules\Groups\Notifications\GroupMembershipApprovedNotification;
use App\Modules\Groups\Notifications\GroupMembershipDeniedNotification;
use App\Modules\Groups\Notifications\GroupMembershipRequestedNotification;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Notifications\TestEmailNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

function makeSharedFile(User $uploader, string $name = 'contract'): File
{
    return File::factory()->create([
        'uploaded_by' => $uploader->id,
        'name' => $name,
        'description' => null,
        'original_name' => "{$name}.pdf",
        'path' => "files/{$name}.pdf",
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);
}

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('assigning a file to a client sends a notification only when the setting is enabled', function () {
    $file = makeSharedFile($this->admin);
    $client = User::factory()->client()->create();

    Notification::fake();
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    Notification::assertNothingSent();

    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    Notification::fake();
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    Notification::assertSentTo($client, FileSharedNotification::class);
});

test('assigning a file to a group notifies every member', function () {
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    $file = makeSharedFile($this->admin);
    $memberA = User::factory()->client()->create();
    $memberB = User::factory()->client()->create();
    $group = Group::query()->create(['name' => 'Recipients', 'public' => false]);
    $group->members()->attach([$memberA->id, $memberB->id]);

    Notification::fake();
    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'group', 'id' => $group->id]);

    Notification::assertSentTo($memberA, FileSharedNotification::class);
    Notification::assertSentTo($memberB, FileSharedNotification::class);
});

test('sharing a folder with a client sends the folder variant of the notification', function () {
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    $folder = Folder::query()->create(['name' => 'Reports']);
    $client = User::factory()->client()->create();

    Notification::fake();
    $this->actingAs($this->admin)->post("/folders/{$folder->id}/assignments", ['type' => 'client', 'id' => $client->id]);

    Notification::assertSentTo($client, FileSharedNotification::class);
});

test('approving a client sends a notification only when the setting is enabled', function () {
    $pending = User::factory()->pendingClient()->create();

    Notification::fake();
    $this->actingAs($this->admin)->post("/account-requests/{$pending->id}/approve");
    Notification::assertNothingSent();

    $pending2 = User::factory()->pendingClient()->create();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    Notification::fake();
    $this->actingAs($this->admin)->post("/account-requests/{$pending2->id}/approve");
    Notification::assertSentTo($pending2, ClientAccountApprovedNotification::class);
});

test('denying a client sends an on-demand notification even after the account row is deleted', function () {
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    $pending = User::factory()->pendingClient()->create(['name' => 'Denied Person', 'email' => 'denied@example.com']);

    Notification::fake();
    $this->actingAs($this->admin)->delete("/account-requests/{$pending->id}");

    expect(User::query()->find($pending->id))->toBeNull();

    Notification::assertSentOnDemand(
        ClientAccountDeniedNotification::class,
        fn (ClientAccountDeniedNotification $notification, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'denied@example.com',
    );
});

test('denying a client sends nothing when the setting is disabled', function () {
    $pending = User::factory()->pendingClient()->create();

    Notification::fake();
    $this->actingAs($this->admin)->delete("/account-requests/{$pending->id}");
    Notification::assertNothingSent();
});

test('staff can view and update the email settings page', function () {
    $this->actingAs($this->admin);

    $this->get('/system/settings/email')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('system/settings/email')
            ->where('email_notifications_enabled', false)
            ->where('admin_notification_emails', []),
    );

    $this->patch('/system/settings/email', validEmailSettingsPayload([
        'admin_notification_emails' => ['one@example.com', 'two@example.com'],
    ]))->assertRedirect();

    expect(app(Settings::class)->get(Setting::EmailNotificationsEnabled))->toBeTrue()
        ->and(app(Settings::class)->get(Setting::AdminNotificationEmails))->toBe(['one@example.com', 'two@example.com']);
});

test('saving an empty admin notification recipient list is rejected', function () {
    $this->actingAs($this->admin);

    $this->patch('/system/settings/email', validEmailSettingsPayload([
        'admin_notification_emails' => [],
    ]))->assertSessionHasErrors('admin_notification_emails');

    expect(app(Settings::class)->get(Setting::AdminNotificationEmails))->toBe([]);
});

test('saving an admin notification recipient list with an invalid address is rejected', function () {
    $this->actingAs($this->admin);

    $this->patch('/system/settings/email', validEmailSettingsPayload([
        'admin_notification_emails' => ['not-an-email'],
    ]))->assertSessionHasErrors('admin_notification_emails.0');
});

test('clients cannot access the email settings page', function () {
    $this->actingAs(User::factory()->client()->create());

    $this->get('/system/settings/email')->assertRedirect(route('dashboard'));
    $this->patch('/system/settings/email', validEmailSettingsPayload())->assertForbidden();
});

test('the test email is reachable regardless of the notifications toggle', function () {
    $this->actingAs($this->admin);

    Notification::fake();
    $this->post('/system/settings/email/test', ['recipient' => 'someone-else@example.com'])->assertRedirect();

    Notification::assertSentOnDemand(
        TestEmailNotification::class,
        fn (TestEmailNotification $n, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'someone-else@example.com',
    );
});

test('sending a test email requires a valid recipient', function () {
    $this->actingAs($this->admin);

    $this->post('/system/settings/email/test', ['recipient' => 'not-an-email'])->assertSessionHasErrors('recipient');
    $this->post('/system/settings/email/test', [])->assertSessionHasErrors('recipient');
});

test('a successful test send records a success result for the settings page to display', function () {
    $this->actingAs($this->admin);

    $this->post('/system/settings/email/test', ['recipient' => 'someone-else@example.com']);

    $this->get('/system/settings/email')->assertInertia(
        // The flag, not the wording: the page colours the result by it,
        // and matching on "Success" would break the moment somebody reads
        // this screen in another language.
        fn (AssertableInertia $page) => $page->where('test_result.ok', true)
            ->where('test_result.message', fn (?string $message) => str_starts_with($message ?? '', 'Success')),
    );
});

test('a failed test send records the real error for the settings page to display', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

    $this->actingAs($this->admin);
    $this->post('/system/settings/email/test', ['recipient' => 'someone-else@example.com']);

    $this->get('/system/settings/email')->assertInertia(
        fn (AssertableInertia $page) => $page->where('test_result.ok', false)
            ->where('test_result.message', fn (?string $message) => str_starts_with($message ?? '', 'Failed to send')),
    );
});

test('a new client self-registration notifies every configured admin address', function () {
    app(Settings::class)->set(Setting::ClientsCanRegister, true);
    app(Settings::class)->set(Setting::AdminNotificationEmails, ['ops@example.com', 'lead@example.com']);

    Notification::fake();
    $this->post('/register', [
        'name' => 'Self Client',
        'email' => 'self@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);
    Notification::assertNothingSent();

    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    Notification::fake();
    $this->post('/register', [
        'name' => 'Second Self Client',
        'email' => 'second-self@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);

    Notification::assertSentOnDemand(
        AdminClientRegisteredNotification::class,
        fn (AdminClientRegisteredNotification $n, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'ops@example.com',
    );
    Notification::assertSentOnDemand(
        AdminClientRegisteredNotification::class,
        fn (AdminClientRegisteredNotification $n, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'lead@example.com',
    );
});

/**
 * Drives the full chunked-upload contract (session -> part -> complete)
 * as whichever user is currently `actingAs()` — the client portal's own
 * upload mechanism since /my-files/upload's old single-request endpoint
 * was removed in favor of the same uploads.* routes staff use.
 */
function chunkedClientUpload(string $filename): void
{
    $session = test()->postJson('/uploads', [
        'filename' => $filename,
        'size' => 11,
        'type' => 'application/pdf',
    ])->assertOk()->json('uploadId');

    $sign = test()->getJson("/uploads/{$session}/parts/1/sign")->assertOk()->json('url');
    test()->call('PUT', $sign, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], 'hello-world');
    test()->postJson("/uploads/{$session}/complete")->assertOk();
}

test('a client uploading a file notifies every configured admin address only when enabled', function () {
    $client = User::factory()->client()->create();
    RolePermission::query()->firstOrCreate(['role_id' => $client->role_id, 'permission' => Permission::Upload->value]);
    app(Settings::class)->set(Setting::AdminNotificationEmails, ['ops@example.com']);
    $this->actingAs($client);

    Notification::fake();
    chunkedClientUpload('report.pdf');
    Notification::assertNothingSent();

    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    Notification::fake();
    chunkedClientUpload('report2.pdf');

    Notification::assertSentOnDemand(
        AdminClientUploadedNotification::class,
        fn (AdminClientUploadedNotification $n, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'ops@example.com',
    );
});

test('a client without the upload permission cannot upload from the portal', function () {
    // The default Client role now includes upload (SystemRole::Client), so
    // a plain client factory has nothing to test the "without" path
    // against — a custom role that grants neither is needed instead.
    $role = Role::query()->create(['name' => 'No Upload', 'is_administrator' => false, 'is_system' => false]);
    $client = User::factory()->create(['type' => UserType::Client, 'role_id' => $role->id]);

    $this->actingAs($client)->postJson('/uploads', [
        'filename' => 'report.pdf',
        'size' => 11,
    ])->assertForbidden();
});

test('staff creating a client sends a welcome email only when enabled', function () {
    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'Fresh Client',
        'email' => 'fresh@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);
    $client = User::query()->where('email', 'fresh@example.com')->sole();

    Notification::fake();
    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'Another Client',
        'email' => 'another@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);
    Notification::assertNothingSent();

    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    Notification::fake();
    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'Third Client',
        'email' => 'third@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ]);
    $thirdClient = User::query()->where('email', 'third@example.com')->sole();

    Notification::assertSentTo($thirdClient, ClientWelcomeNotification::class);
});

test('editing a client sends a notification only when something actually changed', function () {
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);
    $client = User::factory()->client()->create(['name' => 'Original Name', 'email' => 'original@example.com']);

    Notification::fake();
    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => $client->name,
        'email' => $client->email,
        'active' => $client->active,
        'password' => '',
        'password_confirmation' => '',
    ]);
    Notification::assertNothingSent();

    Notification::fake();
    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => 'Renamed',
        'email' => $client->email,
        'active' => $client->active,
        'password' => '',
        'password_confirmation' => '',
    ]);
    Notification::assertSentTo($client->fresh(), ClientAccountEditedNotification::class);
});

test('editing a client sends nothing when the setting is disabled', function () {
    $client = User::factory()->client()->create(['name' => 'Original Name']);

    Notification::fake();
    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => 'Renamed',
        'email' => $client->email,
        'active' => $client->active,
        'password' => '',
        'password_confirmation' => '',
    ]);
    Notification::assertNothingSent();
});

test('requesting to join a group notifies every configured admin address only when enabled', function () {
    app(Settings::class)->set(Setting::ClientsCanSelectGroup, 'public');
    $group = Group::query()->create(['name' => 'Open Group', 'public' => true]);
    $client = User::factory()->client()->create();

    Notification::fake();
    $this->actingAs($client)->post('/my-groups', ['group_id' => $group->id]);
    Notification::assertNothingSent();

    MembershipRequest::query()->delete();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);
    app(Settings::class)->set(Setting::AdminNotificationEmails, ['ops@example.com']);

    Notification::fake();
    $this->actingAs($client)->post('/my-groups', ['group_id' => $group->id]);

    Notification::assertSentOnDemand(
        GroupMembershipRequestedNotification::class,
        fn (GroupMembershipRequestedNotification $n, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'ops@example.com',
    );
});

test('approving a membership request notifies the client only when enabled', function () {
    $group = Group::query()->create(['name' => 'Target']);
    $client = User::factory()->client()->create();
    $request = MembershipRequest::query()->create(['group_id' => $group->id, 'user_id' => $client->id]);

    Notification::fake();
    $this->actingAs($this->admin)->post("/membership-requests/{$request->id}/approve");
    Notification::assertNothingSent();

    $group2 = Group::query()->create(['name' => 'Target Two']);
    $request2 = MembershipRequest::query()->create(['group_id' => $group2->id, 'user_id' => $client->id]);
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    Notification::fake();
    $this->actingAs($this->admin)->post("/membership-requests/{$request2->id}/approve");
    Notification::assertSentTo($client, GroupMembershipApprovedNotification::class);
});

test('denying a membership request notifies the client only when enabled', function () {
    $group = Group::query()->create(['name' => 'Target']);
    $client = User::factory()->client()->create();
    $request = MembershipRequest::query()->create(['group_id' => $group->id, 'user_id' => $client->id]);

    Notification::fake();
    $this->actingAs($this->admin)->delete("/membership-requests/{$request->id}");
    Notification::assertNothingSent();

    $group2 = Group::query()->create(['name' => 'Target Two']);
    $request2 = MembershipRequest::query()->create(['group_id' => $group2->id, 'user_id' => $client->id]);
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);

    Notification::fake();
    $this->actingAs($this->admin)->delete("/membership-requests/{$request2->id}");
    Notification::assertSentTo($client, GroupMembershipDeniedNotification::class);
});
