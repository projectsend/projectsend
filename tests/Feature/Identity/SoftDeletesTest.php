<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function clientFile(User $uploader): File
{
    return File::factory()->create([
        'uploaded_by' => $uploader->id,
        'name' => 'doc',
        'original_name' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size' => 123,
    ]);
}

test('deleting an account through the ui is a soft delete', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($this->admin)->delete("/clients/{$client->id}");

    expect(User::query()->find($client->id))->toBeNull()
        ->and(User::withTrashed()->find($client->id))->not->toBeNull();
});

test('a soft-deleted account cannot log in and vanishes from lists and routes', function () {
    $client = User::factory()->client()->create();
    $client->delete();

    $this->post('/login', ['email' => $client->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->actingAs($this->admin);
    $this->get('/clients')->assertInertia(
        fn (AssertableInertia $page) => $page->has('clients', 0),
    );
    $this->get("/clients/{$client->id}")->assertNotFound();
});

test('a soft-deleted account keeps its email reserved until purged', function () {
    $client = User::factory()->client()->create(['email' => 'kept@example.com']);
    $client->delete();

    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'Replacement',
        'email' => 'kept@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertSessionHasErrors('email');

    $client->forceDelete();

    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'Replacement',
        'email' => 'kept@example.com',
        'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertSessionDoesntHaveErrors();
});

test('a soft-deleted account can be restored intact', function () {
    $client = User::factory()->client()->create();
    $client->delete();

    User::withTrashed()->findOrFail($client->id)->restore();

    $this->post('/login', ['email' => $client->email, 'password' => 'password']);
    $this->assertAuthenticated();
});

test('deleting a client with no files or folders needs no extra input', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($this->admin)->delete("/clients/{$client->id}")->assertRedirect('/clients');

    expect(User::query()->find($client->id))->toBeNull();
});

test('the index exposes each client\'s content counts and a shared reassignment candidate list', function () {
    $client = User::factory()->client()->create();
    clientFile($client);

    $this->actingAs($this->admin)->get('/clients')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('clients', fn ($clients) => collect($clients)->firstWhere('id', $client->id)['content'] === ['files' => 1, 'folders' => 0])
            ->has('reassign_candidates')
            ->where('reassign_candidates', fn ($candidates) => collect($candidates)->pluck('id')->contains($this->admin->id)),
    );
});

test('deleting a client that owns content is refused without a choice', function () {
    $client = User::factory()->client()->create();
    clientFile($client);

    $this->actingAs($this->admin)->delete("/clients/{$client->id}")->assertSessionHasErrors('content_action');

    expect(User::query()->find($client->id))->not->toBeNull();
});

test('cascade-deleting a client removes their own files and logs a summary', function () {
    $client = User::factory()->client()->create();
    $file = clientFile($client);
    $folder = Folder::query()->create(['name' => 'Mine', 'created_by' => $client->id]);

    $this->actingAs($this->admin)->delete("/clients/{$client->id}", ['content_action' => 'cascade_delete'])
        ->assertRedirect('/clients');

    expect(File::withTrashed()->findOrFail($file->id)->trashed())->toBeTrue()
        ->and(Folder::withTrashed()->findOrFail($folder->id)->trashed())->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::AccountContentCascadeDeleted)->exists())->toBeTrue();
});

test('reassigning a deleted client\'s content transfers ownership and logs a summary', function () {
    $client = User::factory()->client()->create();
    $target = User::factory()->client()->create();
    $file = clientFile($client);

    $this->actingAs($this->admin)->delete("/clients/{$client->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $target->id,
    ])->assertRedirect('/clients');

    expect($file->refresh()->uploaded_by)->toBe($target->id)
        ->and(ActivityLog::query()->where('action', Action::AccountContentReassigned)->exists())->toBeTrue();
});

test('a deleted client\'s content cannot be reassigned to itself or to an inactive account', function () {
    $client = User::factory()->client()->create();
    $inactive = User::factory()->client()->create(['active' => false]);
    clientFile($client);

    $this->actingAs($this->admin)->delete("/clients/{$client->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $client->id,
    ])->assertSessionHasErrors('reassign_to_id');

    $this->actingAs($this->admin)->delete("/clients/{$client->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $inactive->id,
    ])->assertSessionHasErrors('reassign_to_id');

    expect(User::query()->find($client->id))->not->toBeNull();
});

test('a denied registration request is removed outright so the email is free again', function () {
    $pending = User::factory()->pendingClient()->create(['email' => 'again@example.com']);

    $this->actingAs($this->admin)->delete("/account-requests/{$pending->id}");

    expect(User::withTrashed()->where('email', 'again@example.com')->exists())->toBeFalse();
});
