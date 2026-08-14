<?php

declare(strict_types=1);

namespace App\Modules\Clients;

use App\Models\User;
use App\Modules\Clients\Models\ClientCustomField;
use App\Modules\Clients\Models\ClientCustomFieldValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Validation\Rule;

/**
 * The client-facing counterpart to `ClientsController`'s custom-field
 * handling: which field definitions a client sees on the registration
 * form and/or their account page, whether any are locked (see
 * `ClientFieldEditability::EditableOnce`), and the validation/save logic
 * shared by both surfaces.
 *
 * Locked fields are never validated or written to, even if a submission
 * includes a value for one — the disabled input on the frontend is backed
 * by a real server-side guarantee, not just UI convention.
 */
class ClientPortalCustomFields
{
    /**
     * @return Collection<int, ClientCustomField>
     */
    public function fieldsFor(ClientFieldContext $context): Collection
    {
        return ClientCustomField::query()
            ->where('client_editability', '!=', ClientFieldEditability::Hidden->value)
            ->whereJsonContains('client_contexts', $context->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(ClientFieldContext $context, ?User $client): array
    {
        $values = $this->storedValues($client);
        $rows = [];

        foreach ($this->fieldsFor($context) as $field) {
            $rows[] = [
                'id' => $field->id,
                'label' => $field->label,
                'type' => $field->type->value,
                'options' => $field->options,
                'required' => $field->required,
                'locked' => $this->isLocked($field, $values),
            ];
        }

        return $rows;
    }

    /**
     * Keyed by field id — PHP normalizes the numeric string key back to an
     * int, same as every other numeric-keyed array in this class.
     *
     * @return array<int, string>
     */
    public function values(ClientFieldContext $context, ?User $client): array
    {
        if ($client === null) {
            return [];
        }

        $values = $this->storedValues($client);
        $rows = [];

        foreach ($this->fieldsFor($context) as $field) {
            $rows[$field->id] = $values->get($field->id, '');
        }

        return $rows;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(ClientFieldContext $context, ?User $client): array
    {
        $values = $this->storedValues($client);
        $rules = [];

        foreach ($this->fieldsFor($context) as $field) {
            if ($this->isLocked($field, $values)) {
                continue;
            }

            $key = "custom_field_values.{$field->id}";

            if ($field->type === ClientCustomFieldType::Checkbox) {
                // Unlike the admin-side rules, a required checkbox here
                // must actually be checked — "required" alone doesn't
                // enforce that (a '0' string satisfies it).
                $rules[$key] = $field->required ? ['accepted'] : ['nullable', 'boolean'];

                continue;
            }

            $rules[$key] = [$field->required ? 'required' : 'nullable', 'string', 'max:2000'];

            if ($field->type === ClientCustomFieldType::Select && is_array($field->options)) {
                $rules[$key][] = Rule::in($field->options);
            }
        }

        return $rules;
    }

    /**
     * @param  array<int, mixed>  $submitted  field id => submitted value
     */
    public function save(User $client, ClientFieldContext $context, array $submitted): void
    {
        $values = $this->storedValues($client);

        foreach ($this->fieldsFor($context) as $field) {
            if ($this->isLocked($field, $values)) {
                continue;
            }

            $value = $submitted[$field->id] ?? null;
            $stored = $field->type === ClientCustomFieldType::Checkbox
                ? ($value ? '1' : '0')
                : (is_string($value) ? $value : null);

            ClientCustomFieldValue::query()->updateOrCreate(
                ['client_custom_field_id' => $field->id, 'user_id' => $client->id],
                ['value' => $stored === '' ? null : $stored],
            );
        }
    }

    /**
     * @return BaseCollection<int, string>
     */
    private function storedValues(?User $client): BaseCollection
    {
        if ($client === null) {
            return collect();
        }

        return ClientCustomFieldValue::query()
            ->where('user_id', $client->id)
            ->pluck('value', 'client_custom_field_id');
    }

    /**
     * @param  BaseCollection<int, string>  $values
     */
    private function isLocked(ClientCustomField $field, BaseCollection $values): bool
    {
        return $field->client_editability === ClientFieldEditability::EditableOnce
            && filled($values->get($field->id));
    }
}
