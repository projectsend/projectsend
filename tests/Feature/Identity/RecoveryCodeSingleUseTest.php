<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\SignIn;
use App\Modules\Identity\TwoFactor\TwoFactorService;

/**
 * "Each code works exactly once" -- the promise in the docblock, and the
 * reason a printed sheet of recovery codes can be crossed off.
 *
 * Two independently loaded instances of the same row stand in for two
 * requests that both read the list before either writes it back. That is
 * what the old read-filter-write lost, and it is a thing SQLite can
 * demonstrate; lockForUpdate is a no-op there, so what these pin is the
 * re-read, not the lock.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->forceFill([
        'two_factor_secret' => 'SECRET',
        'two_factor_recovery_codes' => ['aaa-111', 'bbb-222', 'ccc-333'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->service = app(TwoFactorService::class);
});

function storedCodes(User $user): array
{
    /** @var list<string> $codes */
    $codes = User::query()->findOrFail($user->id)->two_factor_recovery_codes ?? [];

    return $codes;
}

test('spending two different codes at once does not put either back', function () {
    $first = User::query()->findOrFail($this->user->id);
    $second = User::query()->findOrFail($this->user->id);

    expect($this->service->consumeRecoveryCode($first, 'aaa-111'))->toBeTrue()
        ->and($this->service->consumeRecoveryCode($second, 'bbb-222'))->toBeTrue()
        ->and(storedCodes($this->user))->toBe(['ccc-333']);
});

test('the same code is not accepted twice', function () {
    $first = User::query()->findOrFail($this->user->id);
    $second = User::query()->findOrFail($this->user->id);

    expect($this->service->consumeRecoveryCode($first, 'aaa-111'))->toBeTrue()
        ->and($this->service->consumeRecoveryCode($second, 'aaa-111'))->toBeFalse()
        ->and(storedCodes($this->user))->toBe(['bbb-222', 'ccc-333']);
});

test('the caller instance is left holding what the database holds', function () {
    $instance = User::query()->findOrFail($this->user->id);

    $this->service->consumeRecoveryCode($instance, 'aaa-111');

    expect($instance->two_factor_recovery_codes)->toBe(['bbb-222', 'ccc-333'])
        ->and($instance->isDirty())->toBeFalse();
});

test('a code still works, once, in the ordinary case', function () {
    expect($this->service->consumeRecoveryCode($this->user, 'bbb-222'))->toBeTrue()
        ->and(storedCodes($this->user))->toBe(['aaa-111', 'ccc-333'])
        ->and($this->service->consumeRecoveryCode($this->user, 'bbb-222'))->toBeFalse();
});

test('a code nobody issued is refused, and an account with none at all', function () {
    expect($this->service->consumeRecoveryCode($this->user, 'zzz-999'))->toBeFalse()
        ->and(storedCodes($this->user))->toBe(['aaa-111', 'bbb-222', 'ccc-333']);

    $plain = User::factory()->create();

    expect($this->service->consumeRecoveryCode($plain, 'aaa-111'))->toBeFalse();
});

test('spending the last code empties the list', function () {
    $this->user->forceFill(['two_factor_recovery_codes' => ['only-one']])->save();

    expect($this->service->consumeRecoveryCode($this->user, 'only-one'))->toBeTrue()
        ->and(storedCodes($this->user))->toBe([]);
});

test('the challenge screen still signs somebody in with one, and spends it', function () {
    $this->withSession([
        SignIn::TWO_FACTOR_ID => $this->user->id,
        SignIn::TWO_FACTOR_REMEMBER => false,
    ])->post('/two-factor-challenge', ['recovery_code' => ' aaa-111 '])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($this->user);
    expect(storedCodes($this->user))->toBe(['bbb-222', 'ccc-333']);
});
