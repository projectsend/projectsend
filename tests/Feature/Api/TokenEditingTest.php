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

test('the index lists tokens and the create page holds the form', function () {
    $this->admin->createToken('Zapier', [Permission::Upload->value]);

    $this->actingAs($this->admin)->get('/settings/api-tokens')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/api-tokens/index')
            ->where('tokens.0.name', 'Zapier')
            // The form's data belongs to the create screen now.
            ->missing('available_abilities'));

    $this->actingAs($this->admin)->get('/settings/api-tokens/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/api-tokens/create')->has('available_abilities'));
});

test('a token can be renamed, re-scoped and given a new expiry', function () {
    $created = $this->admin->createToken('Old name', [Permission::Upload->value], now()->addDays(5));

    $this->actingAs($this->admin)->patch("/settings/api-tokens/{$created->accessToken->getKey()}", [
        'name' => 'New name',
        'abilities' => [Permission::Upload->value, Permission::EditFiles->value],
        'expires_in_days' => 60,
    ])->assertRedirect(route('api-tokens.index'));

    $token = $created->accessToken->refresh();

    expect($token->name)->toBe('New name')
        ->and($token->abilities)->toBe([Permission::Upload->value, Permission::EditFiles->value])
        // Counted from now, not from the original issue date.
        ->and(now()->diffInDays($token->expires_at))->toBeGreaterThan(50);
});

test('editing never changes the secret', function () {
    $created = $this->admin->createToken('Zapier', [Permission::Upload->value], now()->addDays(5));
    $hashBefore = $created->accessToken->token;

    $this->actingAs($this->admin)->patch("/settings/api-tokens/{$created->accessToken->getKey()}", [
        'name' => 'Renamed',
        'abilities' => [Permission::Upload->value],
        'expires_in_days' => 30,
    ])->assertRedirect();

    // The whole point of editing rather than recreating: whatever holds the
    // secret keeps working.
    expect($created->accessToken->refresh()->token)->toBe($hashBefore);
});

test('the edit page never exposes the secret', function () {
    $created = $this->admin->createToken('Zapier', [Permission::Upload->value]);

    $body = $this->actingAs($this->admin)
        ->get("/settings/api-tokens/{$created->accessToken->getKey()}/edit")
        ->assertOk()
        ->getContent();

    expect($body)->not->toContain(explode('|', $created->plainTextToken)[1])
        ->and($body)->not->toContain($created->accessToken->token);
});

test('editing cannot grant an ability the owner lacks', function () {
    $limited = staffWithPermissions([Permission::Upload->value]);
    confirmPassword($limited);
    $created = $limited->createToken('Mine', [Permission::Upload->value], now()->addDays(5));

    $this->actingAs($limited)->patch("/settings/api-tokens/{$created->accessToken->getKey()}", [
        'name' => 'Mine',
        'abilities' => [Permission::DeleteOthersFiles->value],
        'expires_in_days' => 30,
    ])->assertSessionHasErrors('abilities.0');

    expect($created->accessToken->refresh()->abilities)->toBe([Permission::Upload->value]);
});

test('editing requires a fresh password confirmation', function () {
    $this->flushSession();
    $unconfirmed = User::factory()->create();
    $created = $unconfirmed->createToken('Theirs', [Permission::Upload->value], now()->addDays(5));

    $this->actingAs($unconfirmed)->patch("/settings/api-tokens/{$created->accessToken->getKey()}", [
        'name' => 'Escalated',
        'abilities' => [Permission::Upload->value],
        'expires_in_days' => 30,
    ])->assertRedirect(route('password.confirm'));

    expect($created->accessToken->refresh()->name)->toBe('Theirs');
});

test('one staff member cannot read or edit another is token', function () {
    $other = User::factory()->create();
    $created = $other->createToken('Theirs', [Permission::Upload->value], now()->addDays(5));
    $id = $created->accessToken->getKey();

    // 404 rather than 403, so token ids cannot be probed for existence.
    $this->actingAs($this->admin)->get("/settings/api-tokens/{$id}/edit")->assertNotFound();

    $this->actingAs($this->admin)->patch("/settings/api-tokens/{$id}", [
        'name' => 'Hijacked',
        'abilities' => [Permission::Upload->value],
        'expires_in_days' => 30,
    ])->assertNotFound();

    expect($created->accessToken->refresh()->name)->toBe('Theirs');
});

test('an ability change is audited with what moved', function () {
    $created = $this->admin->createToken('Zapier', [Permission::Upload->value], now()->addDays(5));

    $this->actingAs($this->admin)->patch("/settings/api-tokens/{$created->accessToken->getKey()}", [
        'name' => 'Zapier',
        'abilities' => [Permission::EditFiles->value],
        'expires_in_days' => 30,
    ])->assertRedirect();

    $entry = ActivityLog::query()->where('action', Action::ApiTokenUpdated)->latest('id')->firstOrFail();

    expect($entry->context['abilities_added'])->toBe([Permission::EditFiles->value])
        ->and($entry->context['abilities_removed'])->toBe([Permission::Upload->value]);
});

test('abilities that no longer apply are surfaced before they are dropped', function () {
    // A token holding something the API no longer reads: it does nothing
    // today, and saving would silently remove it, so the edit screen says so.
    $created = $this->admin->createToken('Legacy', [Permission::Upload->value, 'view_news'], now()->addDays(5));

    $this->actingAs($this->admin)->get("/settings/api-tokens/{$created->accessToken->getKey()}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('token.retired_abilities', ['view_news'])
            ->where('token.abilities', [Permission::Upload->value]));
});
