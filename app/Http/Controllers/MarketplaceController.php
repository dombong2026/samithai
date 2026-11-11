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
            'prompts' => Prompt::published()->with(['category', 'user', 'featuredMedia'])
                ->withViewerSave(auth()->id())
                ->latest('published_at')->take(12)->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:120'],
            'price' => ['nullable', 'in:free,paid'],
            'sort' => ['nullable', 'in:newest,oldest,title_az,price_asc,price_desc,most_saved'],
        ]);

        $query = Prompt::published()->with(['category', 'user', 'featuredMedia'])
            ->withViewerSave(auth()->id());
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

        $sort = $data['sort'] ?? 'newest';
        switch ($sort) {
            case 'most_saved':
                $query->withCount('savers')->orderByDesc('savers_count')
                    ->orderByDesc('published_at')->orderByDesc('id');
                break;
            case 'oldest':
                $query->orderBy('published_at')->orderBy('id');
                break;
            case 'title_az':
                $query->orderBy('title')->orderBy('id');
                break;
            case 'price_asc':
                $query->orderBy('price')->orderByDesc('published_at')->orderByDesc('id');
                break;
            case 'price_desc':
                $query->orderByDesc('price')->orderByDesc('published_at')->orderByDesc('id');
                break;
            default:
                $query->orderByDesc('published_at')->orderByDesc('id');
        }

        return view('marketplace.index', [
            'prompts' => $query->paginate(12)->withQueryString(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function category(Category $category): View
    {
        abort_unless($category->is_active, 404);

        return view('marketplace.category', [
            'category' => $category,
            'prompts' => $category->prompts()->published()->with(['user','featuredMedia'])
                ->withViewerSave(auth()->id())
                ->latest('published_at')->paginate(12),
        ]);
    }

    public function show(Prompt $prompt): View
    {
        abort_unless($prompt->status === 'published', 404);

        return view('marketplace.show', [
            'prompt' => $prompt->load(['category', 'user', 'featuredMedia','seo.ogMedia']),
            'seo' => \App\Support\SeoHead::prompt($prompt),
            'saved' => auth()->check() && auth()->user()->savedPrompts()->whereKey($prompt->id)->exists(),
            'owned' => auth()->check() && (
                auth()->user()->isAdmin() ||
                $prompt->user_id === auth()->id() ||
                auth()->user()->entitlements()->where('prompt_id', $prompt->id)->exists()
            ),
        ]);
    }
}
