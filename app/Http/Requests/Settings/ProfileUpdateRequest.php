<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Modules\Clients\ClientFieldContext;
use App\Modules\Clients\ClientPortalCustomFields;
use App\Modules\Identity\AuthSource;
use App\Support\Rules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Closure;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()?->id),
            ],

            // Changing this address is a credential change, not a detail:
            // it is where a password reset is sent, so whoever can change
            // it owns the account from the next reset onwards. A stolen
            // session used to be enough (GHSA-f32x-fgmp-q353) — temporary
            // access became permanent ownership with one PATCH.
            //
            // `exclude_if` rather than a flat rule, so the rest of the
            // screen keeps saving with nothing extra: a name, a timezone
            // or a custom field is not a credential and must not start
            // asking for a password. Only a *different* address does.
            //
            // The same rule destroy() one controller away has always
            // asked, for the same reason: both doors lead to owning the
            // account.
            'current_password' => [
                Rule::excludeIf(! $this->changesEmail()),
                'required',
                'current_password',
            ],

            // Saved with the rest of the profile so the screen keeps one
            // Save button. `timezone` is fillable, so ProfileController's
            // fill() picks it up with no special handling.
            //
            // `sometimes`, not `required`: the form always sends it, but a
            // caller that doesn't should leave the stored zone alone
            // rather than be rejected — and there is no "no timezone" to
            // clear it to.
            'timezone' => ['sometimes', ...Rules::timezone()],
        ];

        $user = $this->user();

        // An account whose credentials live in a directory or at an
        // identity provider holds a local password nobody knows — see
        // LdapProvisioner, which stores Str::password(64) exactly so it
        // can never be used. Asking such a person to confirm "your current
        // password" is a dead end dressed as a form error, and the address
        // is not theirs to change here in any case: it is what the
        // directory or the provider says it is, and a local edit would
        // either be overwritten or break the link.
        if ($this->changesEmail() && $user !== null && $user->auth_source !== AuthSource::Local) {
            $rules['email'][] = function (string $attribute, mixed $value, Closure $fail): void {
                $fail(__('Your email address comes from the directory or identity provider you sign in with, and cannot be changed here.'));
            };
        }

        if ($user?->isClient() === true) {
            $rules = [
                ...$rules,
                ...app(ClientPortalCustomFields::class)->rules(ClientFieldContext::AccountEdit, $user),
            ];
        }

        return $rules;
    }

    /**
     * Whether this request asks for an address other than the stored one.
     *
     * Compared lowercased and trimmed because the `lowercase` rule runs
     * beside this one rather than before it: without that, re-saving the
     * profile with the address typed in a different case would be read as
     * a change and demand a password for nothing.
     */
    private function changesEmail(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $submitted = $this->input('email');

        if (! is_string($submitted)) {
            return false;
        }

        return mb_strtolower(trim($submitted)) !== mb_strtolower(trim((string) $user->email));
    }
}
