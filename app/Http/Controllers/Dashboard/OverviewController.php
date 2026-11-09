<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Order;
use App\Models\Prompt;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('dashboard.overview', [
            'usersCount' => User::count(),
            'creatorsCount' => User::whereIn('role', ['creator', 'admin'])->count(),
            'promptsCount' => Prompt::count(),
            'publishedCount' => Prompt::published()->count(),
            'pendingCount' => Prompt::where('status', 'pending')->count(),
            'categoryCount' => Category::count(),
            'activeCategoryCount' => Category::where('is_active', true)->count(),
            'ordersCount' => Order::where('status', 'completed')->where('total', 0)->count(),
            'supportOpenCount' => SupportTicket::where('status', 'open')->count(),
            'savedCount' => DB::table('prompt_saves')->count(),
            'publishedContentCount' => ContentEntry::where('status', 'published')->count(),
            'contentDraftCount' => ContentEntry::where('status', 'draft')->count(),
            'mostSavedPrompts' => Prompt::published()
                ->withCount('savers')
                ->with('user:id,name')
                ->orderByDesc('savers_count')->orderByDesc('published_at')->orderByDesc('id')
                ->limit(6)->get(),
            'recentPrompts' => Prompt::with(['user:id,name', 'category:id,name'])
                ->latest()->limit(6)->get(),
        ]);
    }
}
