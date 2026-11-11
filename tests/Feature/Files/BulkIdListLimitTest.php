<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Http\Controllers\ZipDownloadsController;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\RolePermission;
use App\Support\ListSize;
use Illuminate\Support\Facades\DB;

/*
 * Bulk edit and bulk delete take a list of ids, each checked against the
 * policy. A selection now survives paging, so the list is bounded the way
 * a zip's is, rather than left to grow until the request falls over.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('a bulk delete or edit naming more files than the cap is refused', function () {
    $ids = range(1, ZipDownloadsController::MAX_FILES + 1);

    $this->actingAs($this->admin)->deleteJson(route('files.bulk-destroy'), ['file_ids' => $ids])
        ->assertStatus(422)->assertJsonValidationErrors('file_ids');
    $this->actingAs($this->admin)->patchJson(route('files.bulk-update'), ['file_ids' => $ids])
        ->assertStatus(422)->assertJsonValidationErrors('file_ids');
});

test('a list at the cap is still accepted', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $ids = [$file->id, ...range(1_000_000, 1_000_000 + ZipDownloadsController::MAX_FILES - 2)];

    $this->actingAs($this->admin)->delete(route('files.bulk-destroy'), ['file_ids' => $ids])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(File::query()->find($file->id))->toBeNull();
});

/*
 * Refusing a long list is not enough if the refusal comes late. Laravel
 * checks every item of a list even after the list's own `max` has failed:
 * a hundred thousand ids meant a hundred thousand `exists` queries, or a
 * `distinct` check comparing every id with every other, before the error.
 * An error on the list alone, and no category lookups, shows no item was
 * looked at.
 */

/** @return list<string> the tables a callback queried */
function tablesQueriedDuring(Closure $callback): array
{
    $tables = [];
    DB::listen(function ($query) use (&$tables): void {
        if (preg_match('/from [`"]?(\w+)/i', $query->sql, $m)) {
            $tables[] = $m[1];
        }
    });
    $callback();

    return $tables;
}

test('an over-long id list is refused before a single id is checked', function () {
    // Every id the same: the old `distinct` rule flagged each one.
    $ids = array_fill(0, ZipDownloadsController::MAX_FILES + 1, 1);

    $errors = $this->actingAs($this->admin)->deleteJson(route('files.bulk-destroy'), ['file_ids' => $ids])
        ->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toBe(['file_ids']);

    $errors = $this->actingAs($this->admin)->patchJson(route('files.bulk-update'), ['file_ids' => $ids])
        ->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toBe(['file_ids']);
});

test('an over-long category list is refused without looking a single one up', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);
    $categories = range(1_000_000, 1_000_000 + ListSize::CATEGORIES);

    $tables = tablesQueriedDuring(function () use ($file, $categories): void {
        $errors = $this->actingAs($this->admin)->patchJson(route('files.update', $file), ['name' => 'x', 'categories' => $categories])
            ->assertStatus(422)->json('errors');
        expect(array_keys($errors))->toBe(['categories']);

        $errors = $this->actingAs($this->admin)->patchJson(route('files.bulk-update'), ['file_ids' => [$file->id], 'add_category_ids' => $categories])
            ->assertStatus(422)->json('errors');
        expect(array_keys($errors))->toBe(['add_category_ids']);
    });

    expect($tables)->not->toContain('categories');
});

test('a client editing their own file is held to the same category limit', function () {
    $client = User::factory()->client()->create();
    RolePermission::query()->firstOrCreate(['role_id' => $client->role_id, 'permission' => 'edit_files']);
    app()->forgetScopedInstances();
    $client = $client->fresh();
    $file = File::factory()->create(['uploaded_by' => $client->id]);

    $tables = tablesQueriedDuring(function () use ($client, $file): void {
        $errors = $this->actingAs($client)->patchJson(route('my-files.update', $file), [
            'name' => 'x',
            'categories' => range(1_000_000, 1_000_000 + ListSize::CATEGORIES),
        ])->assertStatus(422)->json('errors');
        expect(array_keys($errors))->toBe(['categories']);
    });

    expect($tables)->not->toContain('categories');
});

test('repeated ids are harmless rather than an error', function () {
    $file = File::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->delete(route('files.bulk-destroy'), ['file_ids' => [$file->id, $file->id]])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', '1 file deleted.');
});

test('the notification and dashboard screens refuse an over-long list before checking its entries', function () {
    $entries = array_fill(0, 5000, ['type' => 'file_shared', 'email_enabled' => true]);

    $errors = $this->actingAs($this->admin)->putJson(route('notification-preferences.update'), ['preferences' => $entries])
        ->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toBe(['preferences']);

    $widgets = array_fill(0, 5000, ['widget_key' => 'x', 'enabled' => true, 'column_index' => 0, 'position' => 0]);

    $errors = $this->actingAs($this->admin)->putJson(route('dashboard.widgets.update'), ['columns' => 2, 'widgets' => $widgets])
        ->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toBe(['widgets']);
});
