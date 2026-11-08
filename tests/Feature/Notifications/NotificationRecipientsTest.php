<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\CommentVisibility;
use App\Modules\Comments\FileComments;
use App\Modules\Files\Models\File;
use App\Modules\Files\Notifications\FileSharedNotification;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Notifications\Jobs\SendNotificationDigest;
use App\Modules\Notifications\NotificationTypeRegistry;
use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create();
    app(Settings::class)->set(Setting::EmailNotificationsEnabled, true);
});

test('nobody who cannot sign in is told a file was shared with their group', function () {
    Notification::fake();
    $group = Group::query()->create(['name' => 'Partners']);
    $active = User::factory()->client()->create();
    $pending = User::factory()->client()->create(['active' => false]);
    $expired = User::factory()->client()->create(['expires_at' => now()->subDay()]);
    $group->members()->sync([$active->id, $pending->id, $expired->id]);

    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'name' => 'Q3-acquisition-terms.pdf']);

    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'group', 'id' => $group->id])
        ->assertSessionHasNoErrors();

    expect(InAppNotification::query()->where('type', 'file_shared')->pluck('user_id')->all())->toBe([$active->id]);
    Notification::assertSentTo($active, FileSharedNotification::class);
    Notification::assertNothingSentTo($pending);
    Notification::assertNothingSentTo($expired);
});

test('a digest is not sent to an account deactivated while it waited', function () {
    Notification::fake();
    Queue::fake();
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->post("/files/{$file->id}/assignments", ['type' => 'client', 'id' => $client->id]);
    expect(PendingNotification::query()->count())->toBe(1);

    $client->forceFill(['active' => false])->save();
    (new SendNotificationDigest($client->id, 'file_shared'))->handle(app(NotificationTypeRegistry::class));

    Notification::assertNothingSentTo($client);
});

test('a comment notification does not name a client the recipient may not see', function () {
    Queue::fake();
    $settings = app(Settings::class);
    $settings->set(Setting::CommentsScope, 'all');
    $settings->set(Setting::CommentsAuthors, 'staff_and_clients');

    $role = Role::query()->create(['name' => 'Reps '.Str::random(6), 'client_scoped' => true]);
    foreach (['upload', 'edit_files'] as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }
    $mine = User::factory()->client()->create(['name' => 'Mine']);
    $rep = User::factory()->create(['role_id' => $role->id]);
    $rep->assignedClients()->sync([$mine->id]);

    $file = File::factory()->public()->create(['uploaded_by' => $this->admin->id]);
    shareFileWith($file, $mine);
    $stranger = User::factory()->client()->create(['name' => 'Stranger Corp']);

    $settings->set(Setting::PublicCommentsEnabled, true);

    app(FileComments::class)->post($file, $stranger, CommentVisibility::Everyone, 'Hello');

    $rows = InAppNotification::query()->where('type', 'file_comment.posted')->get()->keyBy('user_id');

    expect($rows[$rep->id]->data['authorName'])->toBe('A client')
        ->and($rows[$this->admin->id]->data['authorName'])->toBe('Stranger Corp');

    $pending = PendingNotification::query()->where('type', 'file_comment.posted')->get()->keyBy('user_id');
    expect($pending[$rep->id]->context['author_name'])->toBe('A client');
});
