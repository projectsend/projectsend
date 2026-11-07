<?php

declare(strict_types=1);

namespace App\Modules\Clients;

use App\Models\User;
use App\Modules\Clients\Models\ClientCustomField;
use App\Modules\Clients\Models\ClientCustomFieldValue;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Custom field values staff give a client: what is valid, and how it is
 * stored. One statement of it, asked by every surface that creates or
 * edits a client — the staff form, the REST API and the AI assistant.
 *
 * The assistant had its own way of making a client and none of this, so
 * a field an admin marked required was skipped there while the form and
 * the API both refused to go without it.
 *
 * The client-facing side (registration, the profile page) has its own
 * rules about which fields a client sees and may change; see
 * ClientPortalCustomFields.
 */
class ClientCustomFieldInput
{
    /**
     * Rules keyed `custom_field_values.{id}`.
     *
     * Checkboxes are never hard-required: "required" only drives the
     * asterisk on the form, not a forced check. $required false drops the
     * requirement altogether, for a partial update that may leave a field
     * alone because the stored value already satisfies it.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(bool $required = true): array
    {
        $rules = [];

        foreach (ClientCustomField::query()->get() as $field) {
            $key = "custom_field_values.{$field->id}";

            if ($field->type === ClientCustomFieldType::Checkbox) {
                $rules[$key] = ['nullable', 'boolean'];

                continue;
            }

            $rules[$key] = [$required && $field->required ? 'required' : 'nullable', 'string', 'max:2000'];

            if ($field->type === ClientCustomFieldType::Select && is_array($field->options)) {
                $rules[$key][] = Rule::in($field->options);
            }
        }

        return $rules;
    }

    /**
     * Every field, whether or not a value was given: a new client has none
     * yet, and a full form submission is not a partial update.
     *
     * @param  array<int, mixed>  $values  field id => submitted value
     */
    public function writeAll(User $client, array $values): void
    {
        $this->write($client, ClientCustomField::query()->get(), $values);
    }

    /**
     * Only the fields named. An absent key means "leave alone", not
     * "clear".
     *
     * @param  array<int, mixed>  $values  field id => submitted value
     */
    public function writeNamed(User $client, array $values): void
    {
        $this->write($client, ClientCustomField::query()->whereIn('id', array_keys($values))->get(), $values);
    }

    /**
     * @param  Collection<int, ClientCustomField>  $fields
     * @param  array<int, mixed>  $values
     */
    private function write(User $client, Collection $fields, array $values): void
    {
        foreach ($fields as $field) {
            $submitted = $values[$field->id] ?? null;
            $value = $field->type === ClientCustomFieldType::Checkbox
                ? ($submitted ? '1' : '0')
                : (is_string($submitted) ? $submitted : null);

            ClientCustomFieldValue::query()->updateOrCreate(
                ['client_custom_field_id' => $field->id, 'user_id' => $client->id],
                ['value' => $value === '' ? null : $value],
            );
        }
    }
}
