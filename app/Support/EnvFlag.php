<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reading an on/off environment variable strictly.
 *
 * `env()` recognises the words `true` and `false` and hands back
 * everything else as the string it was — and every non-empty string is
 * truthy in PHP. So the obvious `(bool) env(...)` reads `no`, `off` and
 * a typo as **on**:
 *
 *     SOMETHING=no     -> on
 *     SOMETHING=off    -> on
 *     SOMETHING=enabld -> on
 *
 * For most settings that is a shrug — somebody notices the feature is on
 * and fixes the line. It stops being a shrug when the wrong answer is the
 * unsafe one, which is every flag that switches a protection *off* or a
 * disclosure *on*. Those have to fail the other way, so this lists what
 * counts as yes and reads everything else — including anything it does
 * not recognise — as no.
 *
 * The list is deliberately short. Nothing an operator might have meant as
 * "no" is in it, which is the point; a value typed as `enabled` turns
 * nothing on and is a configuration mistake to be found rather than
 * guessed at.
 */
final class EnvFlag
{
    private const TRUE_VALUES = ['1', 'true'];

    /**
     * @param  mixed  $value  whatever `env()` returned
     */
    public static function isTrue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return is_string($value)
            && in_array(strtolower(trim($value)), self::TRUE_VALUES, true);
    }
}
