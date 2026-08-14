<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Clients\ClientFieldEditability;
use App\Modules\Clients\Models\ClientCustomField;
use App\Modules\Clients\Models\ClientCustomFieldValue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('custom fields are created, edited, and deleted, with a unique auto-generated key and an audit trail', function () {
    $response = $this->actingAs($this->admin)->post('/client-custom-fields', [
        'label' => 'Department',
        'type' => 'text',
        'required' => false,
    ]);

    $field = ClientCustomField::query()->where('label', 'Department')->sole();
    $response->assertRedirect(route('client-custom-fields.edit', $field));
    expect($field->name)->toBe('department')
        ->and(ActivityLog::query()->where('action', Action::ClientCustomFieldCreated)->exists())->toBeTrue();

    // A second field with the same label collides on the auto-derived key.
    $this->actingAs($this->admin)->post('/client-custom-fields', [
        'label' => 'Department',
        'type' => 'text',
        'required' => false,
    ]);
    expect(ClientCustomField::query()->where('label', 'Department')->pluck('name')->all())->toBe(['department', 'department_2']);

    $this->actingAs($this->admin)->patch("/client-custom-fields/{$field->id}", [
        'label' => 'Team',
        'type' => 'text',
        'required' => true,
    ])->assertRedirect();

    expect($field->refresh()->label)->toBe('Team')
        ->and($field->required)->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::ClientCustomFieldUpdated)->exists())->toBeTrue();

    $this->actingAs($this->admin)->delete("/client-custom-fields/{$field->id}")->assertRedirect('/client-custom-fields');
    expect(ClientCustomField::query()->find($field->id))->toBeNull()
        ->and(ActivityLog::query()->where('action', Action::ClientCustomFieldDeleted)->exists())->toBeTrue();
});

test('managing custom fields requires the manage_custom_fields permission', function () {
    $none = staffWithPermissions([]);

    $this->actingAs($none)->get('/client-custom-fields')->assertForbidden();
    $this->actingAs($none)->get('/client-custom-fields/create')->assertForbidden();
    $this->actingAs($none)->post('/client-custom-fields', ['label' => 'X', 'type' => 'text', 'required' => false])->assertForbidden();

    $field = ClientCustomField::query()->create(['name' => 'x', 'label' => 'X', 'type' => 'text']);
    $this->actingAs($none)->get("/client-custom-fields/{$field->id}")->assertForbidden();
    $this->actingAs($none)->patch("/client-custom-fields/{$field->id}", ['label' => 'Y', 'type' => 'text', 'required' => false])->assertForbidden();
    $this->actingAs($none)->delete("/client-custom-fields/{$field->id}")->assertForbidden();

    $manager = staffWithPermissions(['manage_custom_fields']);
    $this->actingAs($manager)->get('/client-custom-fields')->assertOk();
});

test('deleting a field cascades its stored per-client values', function () {
    $field = ClientCustomField::query()->create(['name' => 'department', 'label' => 'Department', 'type' => 'text']);
    $client = User::factory()->client()->create();
    ClientCustomFieldValue::query()->create(['client_custom_field_id' => $field->id, 'user_id' => $client->id, 'value' => 'Sales']);

    $this->actingAs($this->admin)->delete("/client-custom-fields/{$field->id}");

    expect(ClientCustomFieldValue::query()->where('client_custom_field_id', $field->id)->exists())->toBeFalse();
});

test('a select field validates against its own options', function () {
    $field = ClientCustomField::query()->create([
        'name' => 'tier', 'label' => 'Tier', 'type' => 'select', 'options' => ['Gold', 'Silver'],
    ]);

    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'A Client', 'email' => 'a@example.com', 'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
        'custom_field_values' => [$field->id => 'Bronze'],
    ])->assertSessionHasErrors("custom_field_values.{$field->id}");

    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'A Client', 'email' => 'b@example.com', 'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
        'custom_field_values' => [$field->id => 'Gold'],
    ])->assertSessionDoesntHaveErrors();
});

test('a required field must be filled in when creating or updating a client', function () {
    $field = ClientCustomField::query()->create(['name' => 'department', 'label' => 'Department', 'type' => 'text', 'required' => true]);

    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'A Client', 'email' => 'a@example.com', 'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
    ])->assertSessionHasErrors("custom_field_values.{$field->id}");

    $client = User::factory()->client()->create();
    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => $client->name, 'email' => $client->email, 'active' => true,
    ])->assertSessionHasErrors("custom_field_values.{$field->id}");
});

test('client_contexts is required once client_editability is not hidden', function () {
    $this->actingAs($this->admin)->post('/client-custom-fields', [
        'label' => 'Newsletter', 'type' => 'checkbox', 'required' => false,
        'client_editability' => 'editable',
    ])->assertSessionHasErrors('client_contexts');

    $this->actingAs($this->admin)->post('/client-custom-fields', [
        'label' => 'Newsletter', 'type' => 'checkbox', 'required' => false,
        'client_editability' => 'editable', 'client_contexts' => ['registration'],
    ])->assertSessionDoesntHaveErrors();

    $field = ClientCustomField::query()->where('label', 'Newsletter')->sole();
    expect($field->client_editability)->toBe(ClientFieldEditability::Editable)
        ->and($field->client_contexts)->toBe(['registration']);
});

test('omitting client_editability defaults a field to hidden, unchanged from before this setting existed', function () {
    $this->actingAs($this->admin)->post('/client-custom-fields', [
        'label' => 'Department', 'type' => 'text', 'required' => false,
    ])->assertSessionDoesntHaveErrors();

    $field = ClientCustomField::query()->where('label', 'Department')->sole();
    expect($field->client_editability)->toBe(ClientFieldEditability::Hidden)
        ->and($field->client_contexts)->toBeNull();
});

test('custom field values persist and reload on the client edit form', function () {
    $text = ClientCustomField::query()->create(['name' => 'department', 'label' => 'Department', 'type' => 'text']);
    $checkbox = ClientCustomField::query()->create(['name' => 'vip', 'label' => 'VIP', 'type' => 'checkbox']);

    $this->actingAs($this->admin)->post('/clients', [
        'name' => 'A Client', 'email' => 'a@example.com', 'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
        'custom_field_values' => [$text->id => 'Sales', $checkbox->id => '1'],
    ]);

    $client = User::query()->where('email', 'a@example.com')->sole();

    $this->actingAs($this->admin)->get("/clients/{$client->id}")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where("custom_field_values.{$text->id}", 'Sales')
            ->where("custom_field_values.{$checkbox->id}", '1'),
    );

    $this->actingAs($this->admin)->patch("/clients/{$client->id}", [
        'name' => $client->name, 'email' => $client->email, 'active' => true,
        'custom_field_values' => [$text->id => 'Support', $checkbox->id => '0'],
    ]);

    expect(ClientCustomFieldValue::query()->where('client_custom_field_id', $text->id)->where('user_id', $client->id)->value('value'))
        ->toBe('Support');
});
