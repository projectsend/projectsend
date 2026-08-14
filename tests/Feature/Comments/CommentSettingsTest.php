<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'staff_and_clients');
});

test('staff can see the comment settings and the vocabulary behind them', function () {
    $this->actingAs($this->admin)->get('/system/settings/comments')
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('system/settings/comments')
                ->where('comments_scope', 'all')
                ->where('comments_authors', 'staff_and_clients')
                // Sent as data so the two enums stay the only definition
                // of the vocabulary.
                ->count('scope_options', 5)
                ->count('author_options', 4)
        );
});

test('staff can change who may comment and on what', function () {
    $this->actingAs($this->admin)->patch('/system/settings/comments', [
        'comments_scope' => 'public_files',
        'comments_authors' => 'everyone',
        'public_comments_enabled' => true,
        'comments_guest_moderation' => false,
        'comments_edit_window_minutes' => 30,
    ])->assertRedirect();

    expect($this->settings->get(Setting::CommentsScope))->toBe('public_files')
        ->and($this->settings->get(Setting::CommentsAuthors))->toBe('everyone')
        ->and($this->settings->get(Setting::PublicCommentsEnabled))->toBeTrue()
        ->and($this->settings->get(Setting::CommentsGuestModeration))->toBeFalse()
        ->and($this->settings->get(Setting::CommentsEditWindowMinutes))->toBe(30);
});

test('a value outside either enum is rejected', function () {
    $this->actingAs($this->admin)->patch('/system/settings/comments', [
        'comments_scope' => 'everything',
        'comments_authors' => 'staff_and_clients',
        'public_comments_enabled' => false,
        'comments_guest_moderation' => true,
        'comments_edit_window_minutes' => 15,
    ])->assertSessionHasErrors('comments_scope');
});

test('clients cannot reach comment settings', function () {
    $client = User::factory()->client()->create();

    // The `staff` middleware sends a navigation home rather than 403ing,
    // the same as every other system settings section; a write gets the
    // blunt answer instead, since there is no page to land on.
    $this->actingAs($client)->get('/system/settings/comments')->assertRedirect(route('dashboard'));
    $this->actingAs($client)->patch('/system/settings/comments', [])->assertForbidden();
});

test('the per-file switch is only offered while the scope is "selected"', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->get("/files/{$file->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can_set_commentable', false));

    $this->settings->set(Setting::CommentsScope, 'selected');

    $this->actingAs($this->admin)->get("/files/{$file->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can_set_commentable', true));
});

test('the per-file switch cannot be set while the scope ignores it', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id, 'commentable' => false]);

    // The page hides the control under this scope; a request reaching the
    // endpoint directly must not be able to set it either.
    $this->actingAs($this->admin)->patch("/files/{$file->id}", [
        'name' => $file->name,
        'commentable' => true,
    ])->assertRedirect();

    expect($file->fresh()->commentable)->toBeFalse();

    $this->settings->set(Setting::CommentsScope, 'selected');

    $this->actingAs($this->admin)->patch("/files/{$file->id}", [
        'name' => $file->name,
        'commentable' => true,
    ])->assertRedirect();

    expect($file->fresh()->commentable)->toBeTrue();
});
