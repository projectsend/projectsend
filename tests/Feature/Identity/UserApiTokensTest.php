<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Api\Auth\ApiTokens;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * What the staff screens say about somebody else's API credentials: an
 * indicator in the list, and a read-only panel on the edit screen.
 *
 * Read-only is the load-bearing word — see ApiTokens. An administrator
 * seeing that an integration exists and what it may do is their
 * installation's security posture; renaming, re-scoping or revoking it
 * stays with the owner, and none of that is reachable from here.
 */
function staffWithToken(array $abilities = ['upload'], ?DateTimeInterface $expiresAt = null, string $name = 'CI deploy'): User
{
    $user = User::factory()->role(SystemRole::Uploader)->create();
    $user->createToken($name, $abilities, $expiresAt);

    return $user;
}

// The column answers two questions at once, so the payload carries both
// counts: who holds a live credential right now, and who has ever used the
// API at all.
test('the index reports both the live and the lifetime token count', function () {
    $adminUser = User::factory()->create();

    $withTokens = staffWithToken();
    $withTokens->createToken('Second', ['upload'], now()->addDays(30));

    $withoutTokens = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($adminUser)->get('/users')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('users', function ($users) use ($withTokens, $withoutTokens): bool {
            $rows = collect($users)->keyBy('id');

            return $rows[$withTokens->id]['api_tokens'] === ['total' => 2, 'active' => 2]
                && $rows[$withoutTokens->id]['api_tokens'] === ['total' => 0, 'active' => 0];
        }));
});

// The state the column exists to distinguish from "never used the API": an
// expired token is nobody's credential, but it does say this account has an
// integration history.
test('an account whose tokens have all expired reads as past, not never', function () {
    $adminUser = User::factory()->create();
    $user = staffWithToken(expiresAt: now()->subDay());

    $this->actingAs($adminUser)->get('/users')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('users', fn ($users) => collect($users)->firstWhere('id', $user->id)['api_tokens'] === [
            'total' => 1,
            'active' => 0,
        ]));
});

test('an account mixing live and expired tokens reports each separately', function () {
    $adminUser = User::factory()->create();

    $user = staffWithToken(expiresAt: now()->subDay());
    $user->createToken('Still good', ['upload'], now()->addDays(30));

    $this->actingAs($adminUser)->get('/users')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('users', fn ($users) => collect($users)->firstWhere('id', $user->id)['api_tokens'] === [
            'total' => 2,
            'active' => 1,
        ]));
});

// A page of 25 accounts must not become 25 queries.
test('the index counts every account\'s tokens in a single query', function () {
    $adminUser = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        staffWithToken();
    }

    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'personal_access_tokens')) {
            $queries++;
        }
    });

    $this->actingAs($adminUser)->get('/users')->assertOk();

    expect($queries)->toBe(1);
});

test('the edit screen carries every token with its permissions resolved to labels', function () {
    $adminUser = User::factory()->create();
    $user = staffWithToken(['upload', 'edit_files'], name: 'Zapier');

    $this->actingAs($adminUser)->get("/users/{$user->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('users/edit')
        ->has('api_tokens', 1)
        ->where('api_tokens.0.name', 'Zapier')
        ->where('api_tokens.0.active', true)
        ->where('api_tokens.0.abilities', fn ($abilities) => collect($abilities)->pluck('key')->all() === ['upload', 'edit_files']
            // Labels, not bare keys — "what can this integration do" should
            // not require reading the permission vocabulary.
            && collect($abilities)->every(fn (array $ability): bool => $ability['label'] !== $ability['key'])
            && collect($abilities)->every(fn (array $ability): bool => $ability['effective'] === true)));
});

// A token keeps the abilities it was issued with, but EnsureTokenCan
// re-checks the owner's live permissions on every request — so one the
// owner has since lost is carried and ignored, and the panel says so.
test('an ability the owner no longer holds is shown as ineffective', function () {
    $adminUser = User::factory()->create();
    // `delete_others_files` is consumed by an API route but is not in the
    // Uploader role's grant, which is exactly the "carried but ignored"
    // shape: a token issued before a demotion still lists it.
    $user = staffWithToken(['upload', 'delete_others_files']);

    $this->actingAs($adminUser)->get("/users/{$user->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('api_tokens.0.abilities', function ($abilities): bool {
            $byKey = collect($abilities)->keyBy('key');

            return $byKey['upload']['effective'] === true
                && $byKey['delete_others_files']['effective'] === false;
        }));
});

test('an account with no tokens gets an empty list rather than nothing', function () {
    $adminUser = User::factory()->create();
    $user = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($adminUser)->get("/users/{$user->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('api_tokens', 0));
});

// The whole point of the read-only stance: this screen must not become a
// second, unaudited way to revoke somebody's integration.
test('viewing another account\'s tokens does not let an administrator touch them', function () {
    $adminUser = User::factory()->create();
    $user = staffWithToken();
    $tokenId = $user->tokens()->sole()->getKey();

    $this->actingAs($adminUser)->delete(route('api-tokens.destroy', $tokenId))->assertRedirect();

    expect($user->tokens()->whereKey($tokenId)->exists())->toBeTrue();
});

test('a token never expires when it was issued without an expiry', function () {
    $user = staffWithToken();

    expect(ApiTokens::isActive($user->tokens()->sole()))->toBeTrue();
});
