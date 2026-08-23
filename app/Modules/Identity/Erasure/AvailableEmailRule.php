<?php

declare(strict_types=1);

namespace App\Modules\Identity\Erasure;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * `unique:users,email` with an answer for the case that rule cannot
 * explain: the address is held by a soft-deleted account.
 *
 * The unique index on users.email spans trashed rows on purpose — an
 * email address is a login identity, and it must not become
 * re-registerable while the account holding it is merely pending erasure.
 * But the stock message ("has already been taken") then names a conflict
 * the person at the form cannot see or clear from any screen (#1648).
 * This rule keeps the refusal and explains it: when the address frees
 * itself, or — for accounts deleted before erasure scheduling existed —
 * which command frees it.
 *
 * Staff surfaces only. Public registration keeps the stock rule
 * deliberately: telling an anonymous visitor "this address belongs to a
 * deleted account" confirms the address had an account here, which is
 * exactly the disclosure the generic message avoids.
 */
class AvailableEmailRule implements ValidationRule
{
    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            // required/string/email own that refusal.
            return;
        }

        $holder = User::withTrashed()->where('email', $value)->first();

        if ($holder === null) {
            return;
        }

        if (! $holder->trashed()) {
            // A living account: the stock unique message said all there
            // is to say.
            $fail('validation.unique')->translate();

            return;
        }

        if ($holder->erase_after !== null) {
            $fail(__('This email address belongs to a deleted account that is scheduled for permanent erasure. The address becomes available on :date. To free it sooner, erase the account with the projectsend:erase-account console command.', [
                'date' => $holder->erase_after->toFormattedDateString(),
            ]));

            return;
        }

        // Deleted before erasure scheduling existed, so no purge will ever
        // reach it — only the operator command can free the address.
        $fail(__('This email address belongs to a deleted account that has no erasure scheduled. Run the projectsend:erase-account console command to erase it and free the address.'));
    }
}
