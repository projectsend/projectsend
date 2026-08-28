<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\Models\ClientCustomField;
use App\Modules\Clients\Models\ClientCustomFieldValue;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // Setup complete.
    User::factory()->create();
});

test('a field visible only on registration renders and saves there, and is absent from the account page', function () {
    app(Settings::class)->set(Setting::ClientsCanRegister, true);
    app(Settings::class)->set(Setting::ClientsAutoApprove, true);

    $field = ClientCustomField::query()->create([
        'name' => 'referral', 'label' => 'Referral source', 'type' => 'text',
        'client_editability' => 'editable', 'client_contexts' => ['registration'],
    ]);

    $this->get('/register')->assertInertia(
        fn (AssertableInertia $page) => $page->has('custom_fields', 1)->where('custom_fields.0.id', $field->id),
    );

    $this->post('/register', [
        'name' => 'New Client', 'email' => 'new@example.com', 'password' => 'super-secret-password',
        'password_confirmation' => 'super-secret-password',
        'custom_field_values' => [$field->id => 'Friend'],
    ])->assertRedirect(route('login'));

    $client = User::query()->where('email', 'new@example.com')->sole();
    expect(ClientCustomFieldValue::query()->where('client_custom_field_id', $field->id)->where('user_id', $client->id)->value('value'))
        ->toBe('Friend');

    $this->actingAs($client)->get('/settings/profile')->assertInertia(
        fn (AssertableInertia $page) => $page->has('custom_fields', 0),
    );
});

test('a field visible on the account page appears for a client but not for staff', function () {
    $admin = User::query()->sole();
    $client = User::factory()->client()->create();

    $field = ClientCustomField::query()->create([
        'name' => 'newsletter', 'label' => 'Newsletter opt-in', 'type' => 'checkbox',
        'client_editability' => 'editable', 'client_contexts' => ['account_edit'],
    ]);

    $this->actingAs($client)->get('/settings/profile')->assertInertia(
        fn (AssertableInertia $page) => $page->has('custom_fields', 1)->where('custom_fields.0.id', $field->id),
    );

    $this->actingAs($admin)->get('/settings/profile')->assertInertia(
        fn (AssertableInertia $page) => $page->has('custom_fields', 0),
    );

    $this->actingAs($client)->patch('/settings/profile', [
        'name' => $client->name, 'email' => $client->email,
        'custom_field_values' => [$field->id => '1'],
    ])->assertSessionDoesntHaveErrors();

    expect(ClientCustomFieldValue::query()->where('client_custom_field_id', $field->id)->where('user_id', $client->id)->value('value'))
        ->toBe('1');
});

test('an editable_once field locks after the client sets it once', function () {
    $client = User::factory()->client()->create();

    $field = ClientCustomField::query()->create([
        'name' => 'terms', 'label' => 'Accept terms', 'type' => 'checkbox', 'required' => true,
        'client_editability' => 'editable_once', 'client_contexts' => ['account_edit'],
    ]);

    $this->actingAs($client)->get('/settings/profile')->assertInertia(
        fn (AssertableInertia $page) => $page->where('custom_fields.0.locked', false),
    );

    $this->actingAs($client)->patch('/settings/profile', [
        'name' => $client->name, 'email' => $client->email,
        'custom_field_values' => [$field->id => '1'],
    ])->assertSessionDoesntHaveErrors();

    expect(ClientCustomFieldValue::query()->where('client_custom_field_id', $field->id)->where('user_id', $client->id)->value('value'))
        ->toBe('1');

    $this->actingAs($client)->get('/settings/profile')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('custom_fields.0.locked', true)
            ->where("custom_field_values.{$field->id}", '1'),
    );

    // A subsequent attempt to change a locked field is silently ignored.
    $this->actingAs($client)->patch('/settings/profile', [
        'name' => $client->name, 'email' => $client->email,
        'custom_field_values' => [$field->id => '0'],
    ])->assertSessionDoesntHaveErrors();

    expect(ClientCustomFieldValue::query()->where('client_custom_field_id', $field->id)->where('user_id', $client->id)->value('value'))
        ->toBe('1');
});

test('an editable_once checkbox is not locked by never having been ticked', function () {
    // Saving the form writes '0' for an unticked box, and filled('0') is
    // true — so the field locked itself on the first save of the page it
    // sits on, before the client had decided anything. A text field left
    // empty stores null and stays open, which is the behaviour this now
    // matches.
    $client = User::factory()->client()->create();

    $field = ClientCustomField::query()->create([
        'name' => 'newsletter', 'label' => 'Send me the newsletter', 'type' => 'checkbox',
        'required' => false, 'client_editability' => 'editable_once',
        'client_contexts' => ['account_edit'],
    ]);

    // A save that has nothing to do with the checkbox.
    $this->actingAs($client)->patch('/settings/profile', [
        'name' => 'Renamed', 'email' => $client->email,
    ])->assertSessionDoesntHaveErrors();

    $this->actingAs($client)->get('/settings/profile')->assertInertia(
        fn (AssertableInertia $page) => $page->where('custom_fields.0.locked', false),
    );

    // And the one decision they are entitled to still lands.
    $this->actingAs($client)->patch('/settings/profile', [
        'name' => 'Renamed', 'email' => $client->email,
        'custom_field_values' => [$field->id => '1'],
    ])->assertSessionDoesntHaveErrors();

    expect(ClientCustomFieldValue::query()
        ->where('client_custom_field_id', $field->id)->where('user_id', $client->id)->value('value'))->toBe('1');

    $this->actingAs($client)->get('/settings/profile')->assertInertia(
        fn (AssertableInertia $page) => $page->where('custom_fields.0.locked', true),
    );
});

test('an editable_once text field is unchanged by all this', function () {
    $client = User::factory()->client()->create();

    $field = ClientCustomField::query()->create([
        'name' => 'vat', 'label' => 'VAT number', 'type' => 'text',
        'required' => false, 'client_editability' => 'editable_once',
        'client_contexts' => ['account_edit'],
    ]);

    $this->actingAs($client)->patch('/settings/profile', [
        'name' => $client->name, 'email' => $client->email,
        'custom_field_values' => [$field->id => 'ATU12345678'],
    ])->assertSessionDoesntHaveErrors();

    $this->actingAs($client)->patch('/settings/profile', [
        'name' => $client->name, 'email' => $client->email,
        'custom_field_values' => [$field->id => 'changed'],
    ])->assertSessionDoesntHaveErrors();

    expect(ClientCustomFieldValue::query()
        ->where('client_custom_field_id', $field->id)->where('user_id', $client->id)->value('value'))->toBe('ATU12345678');
});

test('a required checkbox in a client context must actually be checked', function () {
    $client = User::factory()->client()->create();

    $field = ClientCustomField::query()->create([
        'name' => 'terms', 'label' => 'Accept terms', 'type' => 'checkbox', 'required' => true,
        'client_editability' => 'editable', 'client_contexts' => ['account_edit'],
    ]);

    $this->actingAs($client)->patch('/settings/profile', [
        'name' => $client->name, 'email' => $client->email,
        'custom_field_values' => [$field->id => '0'],
    ])->assertSessionHasErrors("custom_field_values.{$field->id}");

    $this->actingAs($client)->patch('/settings/profile', [
        'name' => $client->name, 'email' => $client->email,
    ])->assertSessionHasErrors("custom_field_values.{$field->id}");

    $this->actingAs($client)->patch('/settings/profile', [
        'name' => $client->name, 'email' => $client->email,
        'custom_field_values' => [$field->id => '1'],
    ])->assertSessionDoesntHaveErrors();
});
