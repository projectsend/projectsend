<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\DashboardWidgetPreference;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('a first-time user gets the documented default layout', function () {
    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('dashboard_columns', 2)
            ->where('widget_layout.counters', ['enabled' => true, 'column_index' => 0, 'position' => 0])
            ->where('widget_layout.transfers', ['enabled' => true, 'column_index' => 0, 'position' => 1])
            ->where('widget_layout.recent', ['enabled' => true, 'column_index' => 0, 'position' => 2])
            ->where('widget_layout.top_clients_by_storage', ['enabled' => true, 'column_index' => 1, 'position' => 0])
            ->where('widget_layout.largest_files', ['enabled' => true, 'column_index' => 1, 'position' => 1]),
    );
});

test('saving a layout persists it and is honored on the next load', function () {
    $this->actingAs($this->admin)->put('/dashboard/widgets', [
        'columns' => 2,
        'widgets' => [
            ['widget_key' => 'counters', 'enabled' => true, 'column_index' => 1, 'position' => 0],
            ['widget_key' => 'transfers', 'enabled' => false, 'column_index' => 0, 'position' => 0],
        ],
    ])->assertRedirect();

    expect($this->admin->fresh()->dashboard_columns)->toBe(2);

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('dashboard_columns', 2)
            ->where('widget_layout.counters', ['enabled' => true, 'column_index' => 1, 'position' => 0])
            ->where('transfers', null),
    );
});

test('saving accepts the expired_files widget key', function () {
    // Regression: expired_files was added to the frontend's widget list
    // after this endpoint's validation allowlist was written, so every
    // save (which always round-trips the full layout, including this
    // key) was silently rejected with a 422.
    $this->actingAs($this->admin)->put('/dashboard/widgets', [
        'columns' => 2,
        'widgets' => [
            ['widget_key' => 'expired_files', 'enabled' => false, 'column_index' => 0, 'position' => 3],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('dashboard_widget_preferences', [
        'user_id' => $this->admin->id,
        'widget_key' => 'expired_files',
        'enabled' => false,
    ]);
});

test('a widget preference for a key the viewer lacks permission for has no effect on read', function () {
    // Saved directly, bypassing the endpoint entirely — the read-side
    // permission check must hold regardless of how a row got there
    // (same "runtime gate must hold" property already proven for
    // external storage and mail transport).
    DashboardWidgetPreference::query()->create([
        'user_id' => $this->admin->id,
        'widget_key' => 'system',
        'enabled' => true,
        'column_index' => 0,
        'position' => 0,
    ]);

    $role = Role::query()->create(['name' => 'No System Info', 'is_administrator' => false, 'is_system' => false]);
    RolePermission::query()->insert(['role_id' => $role->id, 'permission' => 'edit_files']);
    $staff = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staff)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('system', null)
            ->missing('widget_layout.system'),
    );
});

test('disabling a widget hides it from the dashboard payload', function () {
    DashboardWidgetPreference::query()->create([
        'user_id' => $this->admin->id,
        'widget_key' => 'news',
        'enabled' => false,
        'column_index' => 2,
        'position' => 1,
    ]);

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('news', null)
            ->where('widget_layout.news.enabled', false),
    );
});

test('saving rejects an invalid column count', function () {
    $this->actingAs($this->admin)->put('/dashboard/widgets', [
        'columns' => 5,
        'widgets' => [],
    ])->assertSessionHasErrors(['columns']);
});

test('saving rejects an unknown widget key', function () {
    $this->actingAs($this->admin)->put('/dashboard/widgets', [
        'columns' => 3,
        'widgets' => [
            ['widget_key' => 'not_a_real_widget', 'enabled' => true, 'column_index' => 0, 'position' => 0],
        ],
    ])->assertSessionHasErrors(['widgets.0.widget_key']);
});

test('saving rejects a layout longer than the widget list', function () {
    // The allowlist checks each value, not how many there are, and the
    // loop wrote a row per element -- so one request could be made to
    // cost a query per entry with nothing to show for it. Sent as JSON:
    // a form-encoded array this large is truncated by max_input_vars long
    // before it reaches the rule under test.
    $widgets = [];

    for ($i = 0; $i < 3000; $i++) {
        $widgets[] = ['widget_key' => 'counters', 'enabled' => true, 'column_index' => 0, 'position' => $i];
    }

    DB::enableQueryLog();

    $this->actingAs($this->admin)
        ->putJson('/dashboard/widgets', ['columns' => 2, 'widgets' => $widgets])
        ->assertJsonValidationErrors(['widgets']);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Refused before the loop, so the cost is the session's own reads
    // rather than one write per element.
    expect($queries)->toBeLessThan(20)
        ->and(DashboardWidgetPreference::query()->count())->toBe(0);
});

test('saving rejects the same widget key twice', function () {
    // Rule::in passes on both, and updateOrCreate made the second a
    // pointless rewrite of the row the first had just created.
    $this->actingAs($this->admin)->putJson('/dashboard/widgets', [
        'columns' => 2,
        'widgets' => [
            ['widget_key' => 'counters', 'enabled' => true, 'column_index' => 0, 'position' => 0],
            ['widget_key' => 'counters', 'enabled' => false, 'column_index' => 1, 'position' => 1],
        ],
    ])->assertJsonValidationErrors(['widgets.0.widget_key']);

    expect(DashboardWidgetPreference::query()->count())->toBe(0);
});

test('a full layout of every widget still saves', function () {
    // The bound is the allowlist, so the largest legitimate layout -- one
    // entry per widget this screen knows -- has to pass.
    $keys = ['counters', 'transfers', 'top_clients_by_storage', 'largest_files',
        'recent', 'system', 'news', 'expired_files', 'api'];

    $widgets = [];

    foreach ($keys as $index => $key) {
        $widgets[] = ['widget_key' => $key, 'enabled' => true, 'column_index' => 0, 'position' => $index];
    }

    $this->actingAs($this->admin)
        ->put('/dashboard/widgets', ['columns' => 2, 'widgets' => $widgets])
        ->assertSessionHasNoErrors();

    expect(DashboardWidgetPreference::query()->where('user_id', $this->admin->id)->count())
        ->toBe(count($keys));
});
