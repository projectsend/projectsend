<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

/**
 * The management screen at /comments: searching and filtering everything,
 * and — the part that matters — not showing more than the person could
 * already read one file at a time.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->settings = app(Settings::class);

    $this->settings->set(Setting::CommentsScope, 'all');
    $this->settings->set(Setting::CommentsAuthors, 'everyone');
    $this->settings->set(Setting::PublicCommentsEnabled, true);
    $this->settings->set(Setting::CommentsGuestModeration, true);

    $this->file = File::factory()->public()->create(['uploaded_by' => $this->admin->id, 'name' => 'Quarterly report']);
});

/**
 * The bodies the screen returned, in order.
 *
 * @param  array<string, string>  $query
 * @return list<string>
 */
function bodiesOn(array $query = []): array
{
    $bodies = [];

    test()->actingAs(test()->admin)
        ->get('/comments'.($query === [] ? '' : '?'.http_build_query($query)))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$bodies): void {
            $bodies = array_column($page->toArray()['props']['entries'], 'body');
        });

    return $bodies;
}

test('a moderation screen is not a way around the visibility model', function () {
    $other = User::factory()->create();

    // Somebody else's private note. Invisible to staff on the file's own
    // thread, and listing every comment must not be the loophole that
    // finally shows it — "only me" is the strongest promise this feature
    // makes.
    FileComment::factory()->for($this->file)->onlyMe()->create(['author_id' => $other->id, 'body' => 'My private note']);
    FileComment::factory()->for($this->file)->everyone()->create(['author_id' => $other->id, 'body' => 'A public remark']);

    expect(bodiesOn())->toBe(['A public remark']);
});

test('a client-scoped moderator sees only their own clients\' conversations', function () {
    $mine = User::factory()->client()->create();
    $theirs = User::factory()->client()->create();

    shareFileWith($this->file, $mine);
    shareFileWith($this->file, $theirs);

    FileComment::factory()->for($this->file)->inThreadOf($mine)->create(['author_id' => $mine->id, 'body' => 'From my client']);
    FileComment::factory()->for($this->file)->inThreadOf($theirs)->create(['author_id' => $theirs->id, 'body' => 'From another client']);

    $role = Role::query()->create(['name' => 'Scoped moderator', 'client_scoped' => true]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => 'moderate_comments'],
        ['role_id' => $role->id, 'permission' => 'upload'],
    ]);
    $manager = User::factory()->create(['role_id' => $role->id]);
    $manager->assignedClients()->attach($mine->id);

    $bodies = [];
    $this->actingAs($manager)->get('/comments')->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$bodies): void {
            $bodies = array_column($page->toArray()['props']['entries'], 'body');
        });

    expect($bodies)->toContain('From my client')
        ->not->toContain('From another client');
});

test('the status filter separates what is held from what is published', function () {
    FileComment::factory()->for($this->file)->everyone()->create(['author_id' => $this->admin->id, 'body' => 'Live']);
    FileComment::factory()->for($this->file)->fromGuest()->pending()->create(['body' => 'Held']);

    expect(bodiesOn(['status' => 'pending']))->toBe(['Held'])
        ->and(bodiesOn(['status' => 'approved']))->toBe(['Live'])
        ->and(bodiesOn())->toHaveCount(2);
});

test('search covers what was said and who said it, since either is how you remember it', function () {
    $client = User::factory()->client()->create(['name' => 'Wanda Fields']);
    shareFileWith($this->file, $client);

    FileComment::factory()->for($this->file)->inThreadOf($client)->create(['author_id' => $client->id, 'body' => 'Where is the invoice?']);
    FileComment::factory()->for($this->file)->fromGuest('Passing visitor')->create(['body' => 'Nice work']);

    expect(bodiesOn(['search' => 'invoice']))->toBe(['Where is the invoice?'])
        // By the account behind it…
        ->and(bodiesOn(['search' => 'Wanda']))->toBe(['Where is the invoice?'])
        // …and by the name a visitor typed, which lives on the comment
        // rather than on any account.
        ->and(bodiesOn(['search' => 'Passing']))->toBe(['Nice work']);
});

test('the other filters narrow by file, audience and author', function () {
    $elsewhere = File::factory()->public()->create(['uploaded_by' => $this->admin->id, 'name' => 'Brand guidelines']);

    FileComment::factory()->for($this->file)->staffOnly()->create(['author_id' => $this->admin->id, 'body' => 'Internal']);
    FileComment::factory()->for($elsewhere)->everyone()->create(['author_id' => $this->admin->id, 'body' => 'On the other file']);
    FileComment::factory()->for($this->file)->fromGuest()->create(['body' => 'From a stranger']);

    expect(bodiesOn(['file' => 'Brand']))->toBe(['On the other file'])
        ->and(bodiesOn(['visibility' => 'staff_only']))->toBe(['Internal'])
        ->and(bodiesOn(['author_type' => 'guest']))->toBe(['From a stranger'])
        ->and(bodiesOn(['author_type' => 'staff']))->toHaveCount(2);
});

test('a filter value outside the vocabulary is rejected rather than ignored', function () {
    $this->actingAs($this->admin)->get('/comments?status=maybe')->assertSessionHasErrors('status');
    $this->actingAs($this->admin)->get('/comments?visibility=whoever')->assertSessionHasErrors('visibility');
});

test('the sidebar badge counts what is waiting, for whoever may act on it', function () {
    FileComment::factory()->for($this->file)->fromGuest()->pending()->create();
    FileComment::factory()->for($this->file)->fromGuest()->pending()->create();
    FileComment::factory()->for($this->file)->everyone()->create(['author_id' => $this->admin->id]);

    $this->actingAs($this->admin)->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pending.comments', 2));

    // No permission, no number — a badge asking somebody to approve
    // something they cannot approve is worse than no badge.
    $this->actingAs(staffWithPermissions(['upload']))->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('pending.comments'));
});
