<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Order;
use App\Models\Prompt;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\WorkspaceActivity;
use App\Support\WorkspaceLaunchpad;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('dashboard.overview', [
            'workspaceLaunchpad' => WorkspaceLaunchpad::data(auth()->user(), 'admin'),
            'workspaceEvents' => WorkspaceActivity::forAdmin(),
            'newUsersWeek' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'newPromptsWeek' => Prompt::where('created_at', '>=', now()->subDays(7))->count(),
            'newTicketsWeek' => SupportTicket::where('created_at', '>=', now()->subDays(7))->count(),
            'newSavesWeek' => DB::table('prompt_saves')->where('created_at', '>=', now()->subDays(7))->count(),
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
