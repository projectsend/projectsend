<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\Social\ProviderLinks;
use App\Modules\Identity\Social\SocialAccount;
use App\Modules\Identity\Social\SocialProvider;

/*
 * A linked sign-in provider is a way in no password check reaches. Linking
 * needs a confirmed password, so whoever held an account for a moment
 * could link an identity of their own; the moments that end API tokens and
 * assistant connections end those links too.
 */
beforeEach(function () {
    $this->admin = User::factory()->role(SystemRole::SystemAdministrator)->create();
});

function linkProvider(User $user, string $subject = 'intruder-1'): SocialAccount
{
    return SocialAccount::query()->create([
        'user_id' => $user->id,
        'provider' => SocialProvider::cases()[0]->value,
        'provider_user_id' => $subject,
        'email' => 'intruder@example.test',
    ]);
}

test('changing your own password ends the provider links', function () {
    $user = User::factory()->create(['password' => 'Original-pass-1!']);
    linkProvider($user);

    $this->actingAs($user)->put('/settings/password', [
        'current_password' => 'Original-pass-1!',
        'password' => 'Brand-new-pass-9!',
        'password_confirmation' => 'Brand-new-pass-9!',
    ])->assertSessionHasNoErrors();

    expect(SocialAccount::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('a password replaced by staff ends the provider links', function () {
    $client = User::factory()->client()->create();
    linkProvider($client);
    $colleague = User::factory()->role(SystemRole::Uploader)->create();
    linkProvider($colleague, 'intruder-2');

    $this->actingAs($this->admin)->get('/dashboard');
    $token = $this->admin->createToken('t', app(\App\Modules\Api\Auth\TokenAbilities::class)->availableFor($this->admin))->plainTextToken;
    $this->withToken($token)->patchJson("/api/v1/users/{$colleague->id}", ['password' => 'Reset-by-admin-9!'])->assertOk();
    $this->withToken($token)->patchJson("/api/v1/clients/{$client->id}", ['password' => 'Reset-by-admin-9!'])->assertOk();

    expect(SocialAccount::query()->whereIn('user_id', [$client->id, $colleague->id])->count())->toBe(0);
});

test('converting an account ends its provider links', function () {
    $staff = User::factory()->role(SystemRole::Uploader)->create();
    linkProvider($staff);

    $this->actingAs($this->admin)->post("/users/convert/{$staff->id}", ['direction' => 'to_client'])->assertRedirect();

    expect(SocialAccount::query()->where('user_id', $staff->id)->count())->toBe(0);
});

test('an account with no password of its own keeps the links that are its way in', function () {
    $user = User::factory()->client()->create(['auth_source' => AuthSource::Social]);
    linkProvider($user);

    expect(app(ProviderLinks::class)->endAllFor($user))->toBe(0)
        ->and(SocialAccount::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('the provider staff are required to sign in with is kept', function () {
    $required = SocialProvider::cases()[0];
    $this->mock(\App\Modules\Identity\StaffSignInPolicy::class, fn ($mock) => $mock->shouldReceive('providerFor')->andReturn($required));
    $staff = User::factory()->role(SystemRole::Uploader)->create();
    linkProvider($staff);

    expect(app(ProviderLinks::class)->endAllFor($staff))->toBe(0)
        ->and(SocialAccount::query()->where('user_id', $staff->id)->count())->toBe(1);
});
