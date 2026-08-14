<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\DashboardWidgetPreference;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
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
