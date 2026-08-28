<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Api\Models\ApiRequestLog;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityOrigin;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->staff = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| What a request log row contains
|--------------------------------------------------------------------------
|
| Two privacy decisions are baked into the schema and have to stay that
| way: the route *pattern* rather than the resolved URI, and no IP.
|
*/

test('a request is recorded with its route pattern, not the resolved URI', function () {
    // A deliberately distinctive id: the natural one is 1, which also
    // appears in the "v1" of the path, so the absence assertion below
    // would pass for the wrong reason.
    $file = File::factory()->create(['id' => 987654, 'uploaded_by' => $this->staff->id]);
    $token = $this->staff->createToken('Zapier', [Permission::Upload->value])->plainTextToken;

    $this->withToken($token)->getJson("/api/v1/files/{$file->id}")->assertOk();

    $log = ApiRequestLog::query()->latest('id')->firstOrFail();

    // The id would turn every row into a record of which file was touched,
    // which is the audit log's job and already covered there.
    expect($log->route)->toBe('api/v1/files/{file}')
        ->and($log->route)->not->toContain('987654')
        ->and($log->method)->toBe('GET')
        ->and($log->status)->toBe(200);
});

test('no IP address is stored', function () {
    $token = $this->staff->createToken('Zapier', [Permission::Upload->value])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();

    // The token identifies the caller, which is the question this table
    // exists to answer; an IP would be a new personal-data surface.
    expect(array_keys(ApiRequestLog::query()->latest('id')->firstOrFail()->getAttributes()))
        ->not->toContain('ip_address');
});

test('the calling token is recorded by id and by name', function () {
    $created = $this->staff->createToken('Zapier', [Permission::Upload->value]);

    $this->withToken($created->plainTextToken)->getJson('/api/v1/me')->assertOk();

    $log = ApiRequestLog::query()->latest('id')->firstOrFail();

    expect($log->api_token_id)->toBe($created->accessToken->getKey())
        ->and($log->api_token_name)->toBe('Zapier')
        ->and($log->user_id)->toBe($this->staff->id);
});

test('failed and unauthenticated requests are recorded too', function () {
    // Error rates are half the point; only logging successes would make
    // the dashboard flatter to the eye and useless in an incident.
    $this->getJson('/api/v1/me')->assertUnauthorized();

    $log = ApiRequestLog::query()->latest('id')->firstOrFail();

    expect($log->status)->toBe(401)
        ->and($log->api_token_id)->toBeNull();
});

test('web requests are not recorded', function () {
    $this->actingAs($this->staff)->get('/dashboard');

    expect(ApiRequestLog::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Retention
|--------------------------------------------------------------------------
*/

test('the prune command respects the configured window', function () {
    $settings = app(Settings::class);
    $original = $settings->get(Setting::ApiRequestLogRetentionDays);

    try {
        $settings->set(Setting::ApiRequestLogRetentionDays, 7);

        ApiRequestLog::query()->create([
            'method' => 'GET', 'route' => 'api/v1/me', 'status' => 200,
            'duration_ms' => 5, 'created_at' => now()->subDays(30),
        ]);
        ApiRequestLog::query()->create([
            'method' => 'GET', 'route' => 'api/v1/me', 'status' => 200,
            'duration_ms' => 5, 'created_at' => now()->subDay(),
        ]);

        $this->artisan('projectsend:purge-api-request-logs')->assertSuccessful();

        expect(ApiRequestLog::query()->count())->toBe(1);
    } finally {
        $settings->set(Setting::ApiRequestLogRetentionDays, $original);
    }
});

test('a retention of zero keeps everything', function () {
    $settings = app(Settings::class);
    $original = $settings->get(Setting::ApiRequestLogRetentionDays);

    try {
        $settings->set(Setting::ApiRequestLogRetentionDays, 0);

        ApiRequestLog::query()->create([
            'method' => 'GET', 'route' => 'api/v1/me', 'status' => 200,
            'duration_ms' => 5, 'created_at' => now()->subYears(2),
        ]);

        $this->artisan('projectsend:purge-api-request-logs')->assertSuccessful();

        expect(ApiRequestLog::query()->count())->toBe(1);
    } finally {
        $settings->set(Setting::ApiRequestLogRetentionDays, $original);
    }
});

/*
|--------------------------------------------------------------------------
| Who sees whose usage
|--------------------------------------------------------------------------
|
| A token is a personal credential. Without a boundary the dashboard would
| show every staff member which integrations their colleagues run.
|
*/

test('the dashboard shows only the viewer own tokens by default', function () {
    $colleague = User::factory()->create();
    $colleague->createToken('Theirs', [Permission::Upload->value]);
    $this->staff->createToken('Mine', [Permission::Upload->value]);

    $props = $this->actingAs($this->staff)->get('/api')->assertOk()->viewData('page')['props'];

    expect(collect($props['tokens'])->pluck('name')->all())->toBe(['Mine']);
});

test('a viewer without view_actions_log cannot widen the scope', function () {
    $limited = staffWithPermissions([Permission::Upload->value]);
    $colleague = User::factory()->create();
    $colleague->createToken('Theirs', [Permission::Upload->value]);

    // Asking for everything is not a way to get everything.
    $props = $this->actingAs($limited)->get('/api?all=1')->assertOk()->viewData('page')['props'];

    expect($props['scope']['install_wide'])->toBeFalse()
        ->and($props['scope']['can_view_install_wide'])->toBeFalse()
        ->and(collect($props['tokens'])->pluck('name')->all())->not->toContain('Theirs');
});

test('a viewer with view_actions_log may see every token', function () {
    $auditor = staffWithPermissions([Permission::ViewActionsLog->value]);
    $colleague = User::factory()->create();
    $colleague->createToken('Theirs', [Permission::Upload->value]);

    $props = $this->actingAs($auditor)->get('/api?all=1')->assertOk()->viewData('page')['props'];

    expect($props['scope']['install_wide'])->toBeTrue()
        ->and(collect($props['tokens'])->pluck('name')->all())->toContain('Theirs');
});

test('request counts follow the same scope', function () {
    $colleague = User::factory()->create();

    ApiRequestLog::query()->create([
        'user_id' => $colleague->id, 'method' => 'GET', 'route' => 'api/v1/me',
        'status' => 200, 'duration_ms' => 5, 'created_at' => now(),
    ]);
    ApiRequestLog::query()->create([
        'user_id' => $this->staff->id, 'method' => 'GET', 'route' => 'api/v1/me',
        'status' => 200, 'duration_ms' => 5, 'created_at' => now(),
    ]);

    $props = $this->actingAs($this->staff)->get('/api')->assertOk()->viewData('page')['props'];

    expect($props['summary']['requests_7d'])->toBe(1);
});

/** An API-origin log entry about $file, shaped as ActivityLogger writes it. */
function apiActionOn(User $actor, File $file): void
{
    ActivityLog::query()->create([
        'actor_id' => $actor->id,
        'actor_name' => $actor->name,
        'actor_type' => $actor->type->value,
        'origin' => ActivityOrigin::Api,
        'api_token_name' => 'Zapier',
        'action' => Action::FileUploaded,
        'subject_type' => $file->getMorphClass(),
        'subject_id' => $file->id,
        'subject_name' => $file->name,
        'created_at' => now(),
    ]);
}

/** A client-scoped viewer holding view_actions_log, as Client Manager ships. */
function scopedApiLogReader(): array
{
    $role = Role::query()->create(['name' => 'Scoped log reader', 'client_scoped' => true]);
    RolePermission::query()->insert([
        ['role_id' => $role->id, 'permission' => Permission::ViewActionsLog->value],
        ['role_id' => $role->id, 'permission' => Permission::Upload->value],
    ]);

    $viewer = User::factory()->create(['role_id' => $role->id]);
    $client = User::factory()->client()->create();
    $viewer->assignedClients()->attach($client->id);

    return [$viewer, $client];
}

test('the recent actions feed narrows to what the viewer may read', function () {
    // Tokens are scoped by view_actions_log alone; the activity log is not.
    // A row carries the subject's name, so an unscoped feed reads out the
    // name of every file in the installation to a Client Manager who gets a
    // 403 on the files themselves — the same reasoning /activity, the
    // download history and the dashboard widget already act on.
    [$scoped, $client] = scopedApiLogReader();

    $theirs = File::factory()->create(['uploaded_by' => $this->staff->id, 'name' => 'Q3 delinquent accounts']);

    $mine = File::factory()->create(['uploaded_by' => $this->staff->id, 'name' => 'Statement']);
    shareFileWith($mine, $client);

    apiActionOn($this->staff, $theirs);
    apiActionOn($this->staff, $mine);

    $props = $this->actingAs($scoped)->get('/api?all=1')->assertOk()->viewData('page')['props'];

    expect($props['scope']['install_wide'])->toBeTrue()
        ->and(collect($props['recent_actions'])->pluck('replacements.subject')->all())->toBe(['Statement']);
});

test('a scoped viewer keeps their own actions, even about a file they cannot open', function () {
    // Guards against narrowing further than /activity does: their own audit
    // trail stays whole, which is the rule ActivityLogScope states.
    [$scoped] = scopedApiLogReader();

    $stranger = File::factory()->create(['uploaded_by' => $this->staff->id, 'name' => 'Not in my library']);
    apiActionOn($scoped, $stranger);

    $props = $this->actingAs($scoped)->get('/api?all=1')->assertOk()->viewData('page')['props'];

    expect(collect($props['recent_actions'])->pluck('replacements.subject')->all())
        ->toBe(['Not in my library']);
});

test('an unscoped viewer still sees every API action in the installation', function () {
    $auditor = staffWithPermissions([Permission::ViewActionsLog->value]);
    $file = File::factory()->create(['uploaded_by' => $this->staff->id, 'name' => 'Anything']);

    apiActionOn($this->staff, $file);

    $props = $this->actingAs($auditor)->get('/api?all=1')->assertOk()->viewData('page')['props'];

    expect(collect($props['recent_actions'])->pluck('replacements.subject')->all())->toBe(['Anything']);
});

test('clients cannot reach the dashboard', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/api')->assertRedirect();
});

/*
|--------------------------------------------------------------------------
| The widget
|--------------------------------------------------------------------------
*/

test('the dashboard widget reports the viewer own usage', function () {
    $this->staff->createToken('Mine', [Permission::Upload->value]);

    ApiRequestLog::query()->create([
        'user_id' => $this->staff->id, 'method' => 'GET', 'route' => 'api/v1/me',
        'status' => 200, 'duration_ms' => 5, 'created_at' => now(),
    ]);

    $props = $this->actingAs($this->staff)->get('/dashboard')->assertOk()->viewData('page')['props'];

    expect($props['api']['tokens'])->toBe(1)
        ->and($props['api']['requests_7d'])->toBe(1);
});

test('the widget key is accepted by the layout endpoint', function () {
    // The Widgets dialog round-trips the full layout, so a key missing
    // from the controller's allowlist 422s the whole save — which is
    // exactly how the expired_files widget silently broke once.
    $this->actingAs($this->staff)->put('/dashboard/widgets', [
        'columns' => 2,
        'widgets' => [
            ['widget_key' => 'api', 'enabled' => true, 'column_index' => 1, 'position' => 4],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();
});
