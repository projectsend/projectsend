<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\ClientCustomFieldType;
use App\Modules\Clients\ClientFieldContext;
use App\Modules\Clients\ClientFieldEditability;
use App\Modules\Clients\Models\ClientCustomField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only definitions for custom fields shown on the staff-facing
 * client create/edit screens (not exposed in the client portal).
 * Configuration data: hard-deleted, cascading away each field's stored
 * per-client values.
 */
class ClientCustomFieldsController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = $validated['search'] ?? null;

        $fields = ClientCustomField::query()
            ->when($search, fn (Builder $query, string $term) => $query->where('label', 'like', "%{$term}%"))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ClientCustomField $field): array => $this->fieldRow($field));

        return Inertia::render('clients/custom-fields/index', [
            'fields' => $fields->all(),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clients/custom-fields/create', [
            'types' => array_map(fn (ClientCustomFieldType $type): string => $type->value, ClientCustomFieldType::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $field = ClientCustomField::query()->create([
            'name' => $this->uniqueName($validated['label']),
            'label' => $validated['label'],
            'type' => $validated['type'],
            'options' => $validated['options'] ?? null,
            'required' => $validated['required'],
            'sort_order' => (int) ClientCustomField::query()->max('sort_order') + 1,
            'client_editability' => $validated['client_editability'],
            'client_contexts' => $validated['client_editability'] === ClientFieldEditability::Hidden->value
                ? null
                : ($validated['client_contexts'] ?? []),
        ]);

        $this->activity->log(Action::ClientCustomFieldCreated, subject: $field, context: ['name' => $field->label]);

        return redirect()->route('client-custom-fields.edit', $field)->with('success', __('Custom field created.'));
    }

    public function edit(ClientCustomField $customField): Response
    {
        return Inertia::render('clients/custom-fields/edit', [
            'field' => $this->fieldRow($customField),
            'types' => array_map(fn (ClientCustomFieldType $type): string => $type->value, ClientCustomFieldType::cases()),
        ]);
    }

    public function update(Request $request, ClientCustomField $customField): RedirectResponse
    {
        $validated = $this->validated($request);

        $customField->update([
            'label' => $validated['label'],
            'type' => $validated['type'],
            'options' => $validated['options'] ?? null,
            'required' => $validated['required'],
            'client_editability' => $validated['client_editability'],
            'client_contexts' => $validated['client_editability'] === ClientFieldEditability::Hidden->value
                ? null
                : ($validated['client_contexts'] ?? []),
        ]);

        $this->activity->log(Action::ClientCustomFieldUpdated, subject: $customField, context: ['name' => $customField->label]);

        return back()->with('success', __('Custom field updated.'));
    }

    public function destroy(ClientCustomField $customField): RedirectResponse
    {
        $label = $customField->label;
        $customField->delete();

        $this->activity->log(Action::ClientCustomFieldDeleted, context: ['name' => $label]);

        return redirect()->route('client-custom-fields.index')->with('success', __('Custom field deleted.'));
    }

    /**
     * @return array{label: string, type: string, options: list<string>|null, required: bool, client_editability: string, client_contexts: list<string>|null}
     */
    private function validated(Request $request): array
    {
        // Normalized up front so "required_unless" below has a real value
        // to compare against even when the field is omitted entirely
        // (omitting it means "hidden", same as explicitly selecting it).
        $request->merge(['client_editability' => $request->input('client_editability') ?? ClientFieldEditability::Hidden->value]);

        return $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ClientCustomFieldType::class)],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255'],
            'required' => ['required', 'boolean'],
            'client_editability' => ['required', Rule::enum(ClientFieldEditability::class)],
            'client_contexts' => ['array', 'required_unless:client_editability,'.ClientFieldEditability::Hidden->value],
            'client_contexts.*' => [Rule::enum(ClientFieldContext::class)],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldRow(ClientCustomField $field): array
    {
        return [
            'id' => $field->id,
            'label' => $field->label,
            'type' => $field->type->value,
            'options' => $field->options,
            'required' => $field->required,
            'client_editability' => $field->client_editability->value,
            'client_contexts' => $field->client_contexts ?? [],
        ];
    }

    /**
     * Auto-derives a machine key from the label — the admin never types
     * one directly, and it's never shown again after creation.
     */
    private function uniqueName(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $name = $base;
        $suffix = 2;

        while (ClientCustomField::query()->where('name', $name)->exists()) {
            $name = "{$base}_{$suffix}";
            $suffix++;
        }

        return $name;
    }
}
