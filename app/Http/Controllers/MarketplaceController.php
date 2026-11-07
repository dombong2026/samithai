<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function home(): View
    {
        return view('marketplace.home', [
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'prompts' => Prompt::published()->with(['category', 'user'])
                ->latest('published_at')->take(12)->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:120'],
            'price' => ['nullable', 'in:free,paid'],
        ]);

        $query = Prompt::published()->with(['category', 'user']);
        if (filled($data['q'] ?? null)) {
            $query->where(function ($builder) use ($data): void {
                $builder->where('title', 'like', '%'.$data['q'].'%')
                    ->orWhere('summary', 'like', '%'.$data['q'].'%');
            });
        }
        if (filled($data['category'] ?? null)) {
            $query->whereHas('category', fn ($builder) => $builder->where('slug', $data['category'])->where('is_active', true));
        }
        if (($data['price'] ?? null) === 'free') {
            $query->where('price', 0);
        } elseif (($data['price'] ?? null) === 'paid') {
            $query->where('price', '>', 0);
        }

        return view('marketplace.index', [
            'prompts' => $query->latest('published_at')->paginate(12)->withQueryString(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function category(Category $category): View
    {
        abort_unless($category->is_active, 404);

        return view('marketplace.category', [
            'category' => $category,
            'prompts' => $category->prompts()->published()->with('user')
                ->latest('published_at')->paginate(12),
        ]);
    }

    public function show(Prompt $prompt): View
    {
        abort_unless($prompt->status === 'published', 404);

        return view('marketplace.show', [
            'prompt' => $prompt->load(['category', 'user']),
            'owned' => auth()->check() && (
                auth()->user()->isAdmin() ||
                $prompt->user_id === auth()->id() ||
                auth()->user()->entitlements()->where('prompt_id', $prompt->id)->exists()
            ),
        ]);
    }
}
