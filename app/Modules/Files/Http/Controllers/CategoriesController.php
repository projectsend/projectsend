<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\CategoryColor;
use App\Modules\Files\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Flat file categories (cross-cutting labels; folders handle hierarchy).
 * Configuration data: hard-deleted, and deleting one only detaches it
 * from files. Management is gated by the category permissions.
 */
class CategoriesController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user !== null
                && ($user->can('create_categories') || $user->can('edit_categories') || $user->can('delete_categories')),
            403,
        );

        $validated = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $filters = ['search' => $validated['search'] ?? null];

        $categories = Category::query()
            ->withCount('files')
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'files_count' => $category->files_count,
            ]);

        return Inertia::render('categories/index', [
            'categories' => $categories->all(),
            'filters' => $filters,
            'can_create' => $user->can('create_categories'),
            'can_edit' => $user->can('edit_categories'),
            'can_delete' => $user->can('delete_categories'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('categories/create', [
            'colors' => array_map(fn (CategoryColor $color): string => $color->value, CategoryColor::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'color' => ['nullable', Rule::enum(CategoryColor::class)],
        ]);

        $category = Category::query()->create([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? CategoryColor::Gray->value,
        ]);

        $this->activity->log(Action::CategoryCreated, subject: $category);

        return redirect()->route('categories.edit', $category)->with('success', __('Category created.'));
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('categories/edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
            ],
            'colors' => array_map(fn (CategoryColor $color): string => $color->value, CategoryColor::cases()),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
            'color' => ['nullable', Rule::enum(CategoryColor::class)],
        ]);

        $category->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? CategoryColor::Gray->value,
        ]);

        $this->activity->log(Action::CategoryRenamed, subject: $category);

        return back()->with('success', __('Category updated.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $name = $category->name;

        // Hard delete (configuration); the category_file pivot cascades, so
        // files keep existing — they just lose the label.
        $category->delete();

        $this->activity->log(Action::CategoryDeleted, context: ['name' => $name]);

        return redirect()->route('categories.index')->with('success', __('Category deleted.'));
    }
}
