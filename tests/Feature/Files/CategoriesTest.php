<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\Category;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function makeFile(User $uploader, string $name = 'doc'): File
{
    return File::factory()->create([
        'uploaded_by' => $uploader->id,
        'name' => $name,
        'original_name' => $name.'.pdf',
        'mime_type' => 'application/pdf',
        'size' => 123,
    ]);
}

/** A staff user whose role has exactly the given permission keys. */
function staffWith(array $permissions): User
{
    $role = Role::query()->create(['name' => 'Role '.Str::random(6)]);
    foreach ($permissions as $permission) {
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

test('categories are created, renamed, and deleted from their own pages, with a unique name and an audit trail', function () {
    $this->actingAs($this->admin);

    $this->get('/categories/create')->assertOk();

    $this->post('/categories', ['name' => 'Invoices'])->assertRedirect();
    $category = Category::query()->sole();
    expect($category->name)->toBe('Invoices')
        ->and(ActivityLog::query()->where('action', Action::CategoryCreated)->exists())->toBeTrue();

    // Redirects to the category's own edit page, like groups and clients.
    $this->post('/categories', ['name' => 'Contracts'])->assertRedirect(route('categories.edit', Category::query()->where('name', 'Contracts')->sole()));

    // Unique name.
    $this->post('/categories', ['name' => 'Invoices'])->assertSessionHasErrors('name');

    $this->get("/categories/{$category->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('categories/edit')
        ->where('category.name', 'Invoices'));

    $this->patch("/categories/{$category->id}", ['name' => 'Receipts'])->assertRedirect();
    expect($category->refresh()->name)->toBe('Receipts');

    $this->delete("/categories/{$category->id}")->assertRedirect('/categories');
    expect(Category::query()->where('id', $category->id)->exists())->toBeFalse();
});

test('a category defaults to gray, accepts a valid color, and rejects an invalid one', function () {
    $this->actingAs($this->admin);

    $this->get('/categories/create')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('colors', ['gray', 'red', 'orange', 'yellow', 'green', 'blue', 'purple', 'pink']));

    $this->post('/categories', ['name' => 'No Color Given'])->assertRedirect();
    expect(Category::query()->where('name', 'No Color Given')->sole()->color)->toBe('gray');

    $this->post('/categories', ['name' => 'Blue One', 'color' => 'blue'])->assertRedirect();
    $blue = Category::query()->where('name', 'Blue One')->sole();
    expect($blue->color)->toBe('blue');

    $this->post('/categories', ['name' => 'Bad Color', 'color' => 'not-a-real-color'])->assertSessionHasErrors('color');
    expect(Category::query()->where('name', 'Bad Color')->exists())->toBeFalse();

    $this->get("/categories/{$blue->id}")->assertInertia(fn (AssertableInertia $page) => $page->where('category.color', 'blue'));

    $this->patch("/categories/{$blue->id}", ['name' => 'Blue One', 'color' => 'purple'])->assertRedirect();
    expect($blue->refresh()->color)->toBe('purple');

    $this->patch("/categories/{$blue->id}", ['name' => 'Blue One', 'color' => 'not-a-real-color'])->assertSessionHasErrors('color');
});

test('each category action requires its own permission', function () {
    $category = Category::query()->create(['name' => 'Docs']);

    // No category permissions at all: even the management page is refused.
    $none = staffWith(['upload']);
    $this->actingAs($none)->get('/categories')->assertForbidden();
    $this->actingAs($none)->get('/categories/create')->assertForbidden();
    $this->actingAs($none)->post('/categories', ['name' => 'X'])->assertForbidden();
    $this->actingAs($none)->get("/categories/{$category->id}")->assertForbidden();
    $this->actingAs($none)->patch("/categories/{$category->id}", ['name' => 'Y'])->assertForbidden();
    $this->actingAs($none)->delete("/categories/{$category->id}")->assertForbidden();

    // Holding one category permission opens the list and its own page, but only that action.
    $creator = staffWith(['create_categories']);
    $this->actingAs($creator)->get('/categories')->assertOk();
    $this->actingAs($creator)->get('/categories/create')->assertOk();
    $this->actingAs($creator)->get("/categories/{$category->id}")->assertForbidden();
    $this->actingAs($creator)->patch("/categories/{$category->id}", ['name' => 'Y'])->assertForbidden();

    $editor = staffWith(['edit_categories']);
    $this->actingAs($editor)->get('/categories/create')->assertForbidden();
    $this->actingAs($editor)->get("/categories/{$category->id}")->assertOk();
    $this->actingAs($editor)->patch("/categories/{$category->id}", ['name' => 'Renamed'])->assertRedirect();
});

test('the categories list exposes can_delete and a category can be deleted straight from it', function () {
    $category = Category::query()->create(['name' => 'Docs']);

    $this->actingAs($this->admin)->get('/categories')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('categories/index')
        ->where('can_delete', true));

    // delete_categories alone is enough, without edit_categories.
    $deleter = staffWith(['delete_categories']);
    $this->actingAs($deleter)->get('/categories')->assertInertia(fn (AssertableInertia $page) => $page->where('can_delete', true));
    $this->actingAs($deleter)->delete("/categories/{$category->id}")->assertRedirect('/categories');
    expect(Category::query()->where('id', $category->id)->exists())->toBeFalse();
});

test('deleting a category detaches it from files but never deletes the files', function () {
    $this->actingAs($this->admin);
    $category = Category::query()->create(['name' => 'Docs']);
    $file = makeFile($this->admin);
    $file->categories()->attach($category->id);

    $this->delete("/categories/{$category->id}")->assertRedirect();

    expect(Category::query()->count())->toBe(0)
        ->and(File::query()->find($file->id))->not->toBeNull()
        ->and($file->categories()->count())->toBe(0);
});

test('assigning categories to a file via update requires set_file_categories', function () {
    $category = Category::query()->create(['name' => 'Docs']);

    // edit_files but NOT set_file_categories: categories are left untouched.
    $noCats = staffWith(['upload', 'edit_files']);
    $file = makeFile($noCats);
    $this->actingAs($noCats)->patch("/files/{$file->id}", ['name' => 'Renamed', 'categories' => [$category->id]])
        ->assertRedirect();
    expect($file->categories()->count())->toBe(0);

    // With the permission, the pivot syncs (add then remove).
    $withCats = staffWith(['upload', 'edit_files', 'set_file_categories']);
    $file2 = makeFile($withCats);
    $this->actingAs($withCats)->patch("/files/{$file2->id}", ['name' => 'Tagged', 'categories' => [$category->id]]);
    expect($file2->categories()->pluck('categories.id')->all())->toBe([$category->id]);

    $this->actingAs($withCats)->patch("/files/{$file2->id}", ['name' => 'Tagged', 'categories' => []]);
    expect($file2->categories()->count())->toBe(0);
});

test('the files index filters to a flat list by category', function () {
    $this->actingAs($this->admin);
    $category = Category::query()->create(['name' => 'Docs']);

    $tagged = makeFile($this->admin, 'tagged');
    $tagged->categories()->attach($category->id);
    makeFile($this->admin, 'untagged');

    $this->get("/files?category={$category->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('searching', true)          // flat mode
        ->where('breadcrumb', [])
        ->has('files', 1)
        ->where('files.0.name', 'tagged')
        ->where('files.0.categories.0.name', 'Docs'));
});

test('the category filter still respects the staff library scope', function () {
    $clientA = User::factory()->client()->create();
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([$clientA->id]);

    $category = Category::query()->create(['name' => 'Shared']);

    // One file shared with the manager's client, one unrelated — both tagged.
    $mine = makeFile($this->admin, 'in-scope');
    $mine->categories()->attach($category->id);
    $mine->assignments()->create(['assignable_type' => (new User)->getMorphClass(), 'assignable_id' => $clientA->id]);

    $other = makeFile($this->admin, 'out-of-scope');
    $other->categories()->attach($category->id);

    $this->actingAs($manager)->get("/files?category={$category->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->has('files', 1)
        ->where('files.0.name', 'in-scope'));
});

test('category badges (with color) reach the client portal', function () {
    $client = User::factory()->client()->create();
    $category = Category::query()->create(['name' => 'Contracts', 'color' => 'blue']);

    $file = makeFile($this->admin, 'agreement');
    $file->categories()->attach($category->id);
    $file->assignments()->create(['assignable_type' => (new User)->getMorphClass(), 'assignable_id' => $client->id]);

    $this->actingAs($client)->get('/my-files')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('files.0.name', 'agreement')
        ->where('files.0.categories.0.name', 'Contracts')
        ->where('files.0.categories.0.color', 'blue'));
});
