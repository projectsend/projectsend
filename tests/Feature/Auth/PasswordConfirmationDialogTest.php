<?php

use App\Models\User;
use App\Modules\Identity\AuthSource;

/*
 * password.confirm used to answer every request with a redirect to the
 * confirm-password screen. For a write that throws the submission away:
 * the redirect cannot carry a POST body, so the user came back to an
 * empty form and the action never ran. An Inertia request now gets a 423
 * the browser turns into a dialog over the page, and the same request is
 * sent again once the password is proved.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('an inertia write without a fresh confirmation is refused in place, not redirected', function () {
    $this->actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('/settings/two-factor')
        ->assertStatus(423)
        ->assertHeader('X-Password-Confirmation', 'required')
        ->assertJson(['has_local_password' => true]);

    // Refused, not half-done: the action did not run.
    expect($this->user->refresh()->two_factor_secret)->toBeNull();
});

test('the refusal tells the dialog when there is no password to type', function () {
    $user = User::factory()->create(['auth_source' => AuthSource::Social]);

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('/settings/two-factor')
        ->assertStatus(423)
        ->assertJson(['has_local_password' => false]);
});

test('a plain form post is still redirected to the confirm-password screen', function () {
    $this->actingAs($this->user)
        ->post('/settings/two-factor')
        ->assertRedirect(route('password.confirm'));
});

test('the dialog confirms over json, and the replayed request goes through', function () {
    $this->actingAs($this->user)
        ->postJson('/confirm-password', ['password' => 'password'])
        ->assertNoContent();

    $this->actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('/settings/two-factor')
        ->assertRedirect();

    expect($this->user->refresh()->two_factor_secret)->not->toBeNull();
});

test('a wrong password in the dialog is a validation error, and confirms nothing', function () {
    $this->actingAs($this->user)
        ->postJson('/confirm-password', ['password' => 'wrong-password'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    $this->actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('/settings/two-factor')
        ->assertStatus(423);
});
