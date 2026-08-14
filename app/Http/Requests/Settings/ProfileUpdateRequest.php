<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Modules\Clients\ClientFieldContext;
use App\Modules\Clients\ClientPortalCustomFields;
use App\Support\Rules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
        if ($user?->isClient() === true) {
            $rules = [
                ...$rules,
                ...app(ClientPortalCustomFields::class)->rules(ClientFieldContext::AccountEdit, $user),
            ];
        }

        return $rules;
    }
}
