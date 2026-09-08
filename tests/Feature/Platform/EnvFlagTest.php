<?php

declare(strict_types=1);

use App\Support\EnvFlag;

/**
 * An on/off environment variable, read strictly.
 *
 * `env()` recognises the words `true` and `false` and returns everything
 * else as the string it was — and every non-empty string is truthy in
 * PHP. So a plain `(bool)` cast reads `no`, `off` and a typo as *on*.
 * That is a shrug on most settings and not a shrug on the ones that
 * switch a protection off, which is what this is for.
 */
test('only an explicit yes is true', function (mixed $value, bool $expected) {
    expect(EnvFlag::isTrue($value))->toBe($expected);
})->with([
    'true' => [true, true],
    '"true"' => ['true', true],
    '"TRUE"' => ['TRUE', true],
    'padded' => ['  true  ', true],
    '"1"' => ['1', true],
    '1' => [1, true],

    'false' => [false, false],
    '"false"' => ['false', false],
    '"0"' => ['0', false],
    '0' => [0, false],
    'null' => [null, false],
    'empty' => ['', false],
    // The values a cast gets backwards, which is the whole reason for this.
    '"no"' => ['no', false],
    '"off"' => ['off', false],
    '"disabled"' => ['disabled', false],
    'a typo' => ['ture', false],
    // Nothing exotic counts either.
    'an array' => [['true'], false],
    'a float' => [1.0, false],
]);

test('a mistyped captcha flag leaves the captcha alone', function () {
    // The flag this exists for. `PROJECTSEND_CAPTCHA_DISABLED=no` used to
    // read as "yes, disabled" and take the bot protection off the login
    // and registration forms without anybody asking for that.
    expect(EnvFlag::isTrue('no'))->toBeFalse()
        ->and(config('projectsend.captcha.disabled'))->toBeFalse();
});
