<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Clients\ClientCustomFieldType;
use App\Modules\Clients\Models\ClientCustomField;
use App\Modules\Clients\Models\ClientCustomFieldValue;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();
    $this->token = $this->admin->createToken('t', [
        Permission::ManageClients->value,
        Permission::CreateClients->value,
        Permission::EditClients->value,
        Permission::DeleteClients->value,
    ])->plainTextToken;
});

/*
|--------------------------------------------------------------------------
| Privacy
|--------------------------------------------------------------------------
|
| `users` is the most sensitive table here. These assert on the raw
| response body rather than on parsed fields, so a leak through a nested
| relation or a future column is caught too.
|
*/

test('no client response carries credentials', function () {
    $client = User::factory()->client()->create();

    $bodies = [
        $this->withToken($this->token)->getJson('/api/v1/clients')->getContent(),
        $this->withToken($this->token)->getJson("/api/v1/clients/{$client->id}")->getContent(),
    ];

    foreach ($bodies as $body) {
        foreach (['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'] as $forbidden) {
            expect($body)->not->toContain($forbidden);
        }
    }
});

test('a staff account is not reachable through the clients surface', function () {
    $staff = User::factory()->create();

    $this->withToken($this->token)->getJson("/api/v1/clients/{$staff->id}")->assertNotFound();
    $this->withToken($this->token)->patchJson("/api/v1/clients/{$staff->id}", ['name' => 'x'])->assertNotFound();
    $this->withToken($this->token)->deleteJson("/api/v1/clients/{$staff->id}")->assertNotFound();

    expect(User::query()->find($staff->id))->not->toBeNull();
});

test('the listing does not hand out every clients custom field data', function () {
    $field = ClientCustomField::query()->create([
        'name' => 'vat', 'label' => 'VAT number', 'type' => ClientCustomFieldType::Text,
        'required' => false, 'sort_order' => 1,
    ]);
    $client = User::factory()->client()->create();
    ClientCustomFieldValue::query()->create([
        'client_custom_field_id' => $field->id, 'user_id' => $client->id, 'value' => 'SECRET-VAT',
    ]);

    expect($this->withToken($this->token)->getJson('/api/v1/clients')->getContent())
        ->not->toContain('SECRET-VAT');

    // But reading one client does include them — the same data the edit
    // screen shows to anyone with edit_clients.
    $this->withToken($this->token)->getJson("/api/v1/clients/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.custom_fields.0.value', 'SECRET-VAT');
});

/*
|--------------------------------------------------------------------------
| CRUD
|--------------------------------------------------------------------------
*/

test('a PATCH leaves custom fields it does not name alone', function () {
    // The same rule the rest of update() states: "an absent key means
    // 'leave alone', not 'clear'". The custom-field pass was create()'s,
    // which writes every field there is.
    $name = ClientCustomField::query()->create([
        'name' => 'contact', 'label' => 'Contact', 'type' => ClientCustomFieldType::Text,
        'required' => false, 'sort_order' => 1,
    ]);
    $vat = ClientCustomField::query()->create([
        'name' => 'vat', 'label' => 'VAT number', 'type' => ClientCustomFieldType::Text,
        'required' => false, 'sort_order' => 2,
    ]);

    $client = User::factory()->client()->create();
    ClientCustomFieldValue::query()->create([
        'client_custom_field_id' => $name->id, 'user_id' => $client->id, 'value' => 'Alex',
    ]);
    ClientCustomFieldValue::query()->create([
        'client_custom_field_id' => $vat->id, 'user_id' => $client->id, 'value' => 'ATU12345678',
    ]);

    $this->withToken($this->token)->patchJson("/api/v1/clients/{$client->id}", [
        'custom_field_values' => [$name->id => 'Robin'],
    ])->assertOk();

    $values = ClientCustomFieldValue::query()->where('user_id', $client->id)
        ->pluck('value', 'client_custom_field_id');

    expect($values[$name->id])->toBe('Robin')
        ->and($values[$vat->id])->toBe('ATU12345678');
});

test('a PATCH can still clear a field by naming it', function () {
    $vat = ClientCustomField::query()->create([
        'name' => 'vat', 'label' => 'VAT number', 'type' => ClientCustomFieldType::Text,
        'required' => false, 'sort_order' => 1,
    ]);
    $client = User::factory()->client()->create();
    ClientCustomFieldValue::query()->create([
        'client_custom_field_id' => $vat->id, 'user_id' => $client->id, 'value' => 'ATU12345678',
    ]);

    $this->withToken($this->token)->patchJson("/api/v1/clients/{$client->id}", [
        'custom_field_values' => [$vat->id => ''],
    ])->assertOk();

    expect(ClientCustomFieldValue::query()
        ->where('user_id', $client->id)->where('client_custom_field_id', $vat->id)->value('value'))->toBeNull();
});

test('creating a client still records every field', function () {
    // create() is not a partial update, and a checkbox nobody ticked is a
    // recorded "no" rather than an absent row.
    $optIn = ClientCustomField::query()->create([
        'name' => 'newsletter', 'label' => 'Newsletter', 'type' => ClientCustomFieldType::Checkbox,
        'required' => false, 'sort_order' => 1,
    ]);

    $this->withToken($this->token)->postJson('/api/v1/clients', [
        'name' => 'Acme Ltd',
        'email' => 'crud-custom@example.com',
        'password' => 'super-secret-password',
    ])->assertCreated();

    $client = User::query()->where('email', 'crud-custom@example.com')->sole();

    expect(ClientCustomFieldValue::query()
        ->where('user_id', $client->id)->where('client_custom_field_id', $optIn->id)->value('value'))->toBe('0');
});

test('a client can be created', function () {
    $this->withToken($this->token)->postJson('/api/v1/clients', [
        'name' => 'Acme Ltd',
        'email' => 'billing@acme.test',
        'password' => 'a-sufficiently-long-password',
    ])->assertStatus(201)->assertJsonPath('data.email', 'billing@acme.test');

    $client = User::query()->where('email', 'billing@acme.test')->firstOrFail();

    expect($client->isClient())->toBeTrue()
        ->and($client->active)->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::UserCreated)->exists())->toBeTrue();
});

test('a required custom field is enforced on create', function () {
    ClientCustomField::query()->create([
        'name' => 'vat', 'label' => 'VAT number', 'type' => ClientCustomFieldType::Text,
        'required' => true, 'sort_order' => 1,
    ]);

    $this->withToken($this->token)->postJson('/api/v1/clients', [
        'name' => 'No VAT',
        'email' => 'novat@acme.test',
        'password' => 'a-sufficiently-long-password',
    ])->assertStatus(422);

    expect(User::query()->where('email', 'novat@acme.test')->exists())->toBeFalse();
});

test('a weak password is refused', function () {
    $this->withToken($this->token)->postJson('/api/v1/clients', [
        'name' => 'Weak',
        'email' => 'weak@acme.test',
        'password' => 'short',
    ])->assertStatus(422);
});

test('update changes only the fields sent', function () {
    $client = User::factory()->client()->create(['name' => 'Before', 'storage_quota_mb' => 500]);

    $this->withToken($this->token)->patchJson("/api/v1/clients/{$client->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $client->refresh();
    expect($client->name)->toBe('After')
        // Untouched by a PATCH that did not mention it — unlike the web
        // form, which always submits every field.
        ->and($client->storage_quota_mb)->toBe(500);
});

test('deactivating a client is recorded', function () {
    $client = User::factory()->client()->create(['active' => true]);

    $this->withToken($this->token)->patchJson("/api/v1/clients/{$client->id}", ['active' => false])->assertOk();

    expect($client->refresh()->active)->toBeFalse()
        ->and(ActivityLog::query()->where('action', Action::UserDeactivated)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Deletion
|--------------------------------------------------------------------------
*/

test('a client with no content deletes without ceremony', function () {
    $client = User::factory()->client()->create();

    $this->withToken($this->token)->deleteJson("/api/v1/clients/{$client->id}")->assertNoContent();

    expect(User::query()->find($client->id))->toBeNull();
});

test('deleting a client that owns files demands an explicit decision', function () {
    $client = User::factory()->client()->create();
    File::factory()->create(['uploaded_by' => $client->id]);

    // No default is possible: one would silently destroy the files, the
    // other would silently transfer them to somebody else.
    $this->withToken($this->token)->deleteJson("/api/v1/clients/{$client->id}")
        ->assertStatus(422)
        ->assertJsonPath('type', 'validation_failed');

    expect(User::query()->find($client->id))->not->toBeNull();
});

test('cascade_delete removes the clients content', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id]);

    $this->withToken($this->token)
        ->deleteJson("/api/v1/clients/{$client->id}", ['content_action' => 'cascade_delete'])
        ->assertNoContent();

    expect(User::query()->find($client->id))->toBeNull()
        ->and(File::query()->find($file->id))->toBeNull();
});

test('reassign moves the content to the named account', function () {
    $client = User::factory()->client()->create();
    $file = File::factory()->create(['uploaded_by' => $client->id]);

    $this->withToken($this->token)->deleteJson("/api/v1/clients/{$client->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $this->admin->id,
    ])->assertNoContent();

    expect(File::query()->find($file->id)?->uploaded_by)->toBe($this->admin->id);
});

test('a failure while disposing of a deleted client\'s content rolls the deletion back', function () {
    $client = User::factory()->client()->create();

    failAccountContentDisposal();

    $this->withToken($this->token)->deleteJson("/api/v1/clients/{$client->id}", [
        'content_action' => 'reassign',
        'reassign_to_id' => $this->admin->id,
    ])->assertStatus(500);

    // The soft-delete shares a transaction with the content step, so its
    // failure leaves the client intact rather than deleted-but-orphaning.
    expect(User::query()->whereKey($client->id)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::UserDeleted)->exists())->toBeFalse();
});

test('show reports what a delete would have to decide about', function () {
    $client = User::factory()->client()->create();
    File::factory()->count(2)->create(['uploaded_by' => $client->id]);

    $this->withToken($this->token)->getJson("/api/v1/clients/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.content.files', 2);
});

/*
|--------------------------------------------------------------------------
| Permissions
|--------------------------------------------------------------------------
*/

test('each route needs its own permission', function () {
    $client = User::factory()->client()->create();
    $readOnly = staffWithPermissions([Permission::ManageClients->value]);
    $token = $readOnly->createToken('t', [Permission::ManageClients->value])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/clients')->assertOk();
    $this->withToken($token)->postJson('/api/v1/clients', [])->assertForbidden();
    $this->withToken($token)->patchJson("/api/v1/clients/{$client->id}", [])->assertForbidden();
    $this->withToken($token)->deleteJson("/api/v1/clients/{$client->id}")->assertForbidden();
});

test('storage quota reporting distinguishes inherited from unlimited', function () {
    $client = User::factory()->client()->create(['storage_quota_mb' => 0]);

    $this->withToken($this->token)->getJson("/api/v1/clients/{$client->id}")
        ->assertOk()
        // 0 on the client means "inherit the site default", which is what
        // effective_quota_mb resolves — a caller should not have to know
        // that rule to display the number correctly.
        ->assertJsonPath('data.storage.quota_mb', 0)
        ->assertJsonStructure(['data' => ['storage' => ['effective_quota_mb', 'unlimited', 'used_mb']]]);
});

/*
|--------------------------------------------------------------------------
| Two-factor reset
|--------------------------------------------------------------------------
*/

test('a client\'s second factor can be removed', function () {
    $client = User::factory()->client()->create();
    enableTwoFactor($client);
    forgetRequestState();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/clients/{$client->id}/two-factor")
        ->assertNoContent();

    expect($client->refresh()->hasTwoFactorEnabled())->toBeFalse();

    expect(ActivityLog::query()->where('action', Action::TwoFactorReset)->sole()->subject_id)
        ->toBe($client->id);
});

test('removing a second factor needs edit_clients, not just manage_clients', function () {
    $token = $this->admin->createToken('narrow', [Permission::ManageClients->value])->plainTextToken;
    $client = User::factory()->client()->create();
    enableTwoFactor($client);
    forgetRequestState();

    $this->withToken($token)
        ->deleteJson("/api/v1/clients/{$client->id}/two-factor")
        ->assertForbidden();

    expect($client->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('a staff account is not addressable through the client two-factor route', function () {
    $staff = User::factory()->create();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/clients/{$staff->id}/two-factor")
        ->assertNotFound();
});

test('the listing reports whether a client has a second factor', function () {
    $client = User::factory()->client()->create();
    enableTwoFactor($client);
    forgetRequestState();

    $this->withToken($this->token)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->assertJsonPath('data.0.two_factor_enabled', true);
});
