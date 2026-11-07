<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin();
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,inactive']]);
        $query = Category::withCount('prompts');
        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->input('q').'%');
        }
        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('dashboard.categories', [
            'categories' => $query->latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $slug = Str::slug($data['name']);
        if ($slug === '') {
            return back()->withErrors(['name' => 'Enter a category name that produces a URL slug.']);
        }
        if (Category::where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'This category already exists.']);
        }
        Category::create($data + ['slug' => $slug]);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $slug = Str::slug($data['name']);
        if ($slug === '') {
            return back()->withErrors(['name' => 'Enter a valid category name.']);
        }
        $request->validate(['name' => [Rule::unique('categories', 'name')->ignore($category->id)]]);
        if (Category::where('slug', $slug)->whereKeyNot($category->id)->exists()) {
            return back()->withErrors(['name' => 'Another category already uses this URL.']);
        }
        $category->update($data + ['slug' => $slug]);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorizeAdmin();
        if ($category->prompts()->exists()) {
            return back()->withErrors(['category' => 'Move or remove the prompts in this category before deleting it.']);
        }
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
