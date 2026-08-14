<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Identity\Permissions\Permission;

beforeEach(function () {
    $this->admin = User::factory()->create();
    confirmPassword($this->admin);
});

test('minting a token requires a fresh password confirmation', function () {
    // actingAs() swaps the user but keeps the session, so the beforeEach
    // confirmation above would otherwise carry over and this would assert
    // nothing. An unconfirmed session must not be able to mint a
    // credential that outlives it.
    $this->flushSession();

    $unconfirmed = User::factory()->create();

    $this->actingAs($unconfirmed)->post('/settings/api-tokens', [
        'name' => 'From a stolen session',
        'abilities' => [Permission::Upload->value],
        'expires_in_days' => 30,
    ])->assertRedirect(route('password.confirm'));

    expect($unconfirmed->tokens()->count())->toBe(0);
});

test('the token page lists only abilities the issuer actually holds', function () {
    $limited = staffWithPermissions([Permission::Upload->value, Permission::EditFiles->value]);

    $this->actingAs($limited)->get('/settings/api-tokens/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/api-tokens/create')
            ->where('available_abilities', function ($groups) {
                $keys = collect($groups)->flatMap(fn ($group) => collect($group['abilities'])->pluck('key'))->all();

                return $keys === [Permission::Upload->value, Permission::EditFiles->value];
            }));
});

test('a token cannot be granted an ability its issuer lacks', function () {
    $limited = staffWithPermissions([Permission::Upload->value]);
    confirmPassword($limited);

    $this->actingAs($limited)->post('/settings/api-tokens', [
        'name' => 'Escalation attempt',
        'abilities' => [Permission::DeleteOthersFiles->value],
        'expires_in_days' => 30,
    ])->assertSessionHasErrors('abilities.0');

    expect($limited->tokens()->count())->toBe(0);
});

test('the plaintext token is returned once and never stored', function () {
    $response = $this->actingAs($this->admin)->post('/settings/api-tokens', [
        'name' => 'Zapier',
        'abilities' => [Permission::Upload->value],
        'expires_in_days' => 30,
    ]);

    $created = $response->assertRedirect()->getSession()->get('created_api_token');
    expect($created['plain_text'])->toBeString()->not->toBeEmpty();

    // What lands in the database is a hash of the secret, not the secret.
    $stored = $this->admin->tokens()->firstOrFail();
    expect($stored->token)->not->toContain($created['plain_text'])
        ->and($stored->token)->toBe(hash('sha256', explode('|', $created['plain_text'])[1]));

    // It survives exactly one request — the redirect target that displays
    // it — and is gone on the next load. That is the whole "shown once"
    // guarantee: there is no second chance to read it out of the app.
    $this->actingAs($this->admin)->get('/settings/api-tokens')
        ->assertInertia(fn ($page) => $page->where('created_token.plain_text', $created['plain_text']));

    $this->actingAs($this->admin)->get('/settings/api-tokens')
        ->assertInertia(fn ($page) => $page->where('created_token', null));
});

test('an expiry is required unless never_expires is chosen explicitly', function () {
    $this->actingAs($this->admin)->post('/settings/api-tokens', [
        'name' => 'No expiry given',
        'abilities' => [Permission::Upload->value],
    ])->assertSessionHasErrors('expires_in_days');

    $this->actingAs($this->admin)->post('/settings/api-tokens', [
        'name' => 'Deliberately eternal',
        'abilities' => [Permission::Upload->value],
        'never_expires' => true,
    ])->assertSessionHasNoErrors();

    expect($this->admin->tokens()->firstOrFail()->expires_at)->toBeNull();
});

test('the expiry ceiling is enforced', function () {
    $this->actingAs($this->admin)->post('/settings/api-tokens', [
        'name' => 'Too long',
        'abilities' => [Permission::Upload->value],
        'expires_in_days' => (int) config('api.tokens.max_days') + 1,
    ])->assertSessionHasErrors('expires_in_days');
});

test('creating and revoking a token are both audited', function () {
    $this->actingAs($this->admin)->post('/settings/api-tokens', [
        'name' => 'Audited',
        'abilities' => [Permission::Upload->value],
        'expires_in_days' => 10,
    ]);

    $token = $this->admin->tokens()->firstOrFail();

    $this->actingAs($this->admin)->delete("/settings/api-tokens/{$token->getKey()}")->assertRedirect();

    expect(ActivityLog::query()->where('action', Action::ApiTokenCreated)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', Action::ApiTokenRevoked)->exists())->toBeTrue()
        ->and($this->admin->tokens()->count())->toBe(0);
});

test('one staff member cannot revoke another staff member is token', function () {
    $other = User::factory()->create();
    $token = $other->createToken('Theirs', [Permission::Upload->value]);

    $this->actingAs($this->admin)
        ->delete('/settings/api-tokens/'.$token->accessToken->getKey())
        ->assertRedirect();

    // Silently a no-op rather than an error, but the token must survive.
    expect($other->tokens()->count())->toBe(1);
});

test('clients cannot reach the token page at all', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get('/settings/api-tokens')->assertRedirect();
});
