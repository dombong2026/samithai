<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('dashboard.overview', [
            'usersCount' => User::count(),
            'promptsCount' => Prompt::count(),
            'pendingCount' => Prompt::where('status', 'pending')->count(),
            'categoryCount' => Category::count(),
            'ordersCount' => Order::count(),
            'savedCount' => \Illuminate\Support\Facades\DB::table('prompt_saves')->count(),
            'mostSavedPrompts' => Prompt::published()
                ->withCount('savers')
                ->with('user:id,name')
                ->orderByDesc('savers_count')->orderByDesc('published_at')
                ->limit(6)->get(),
            'recentPrompts' => Prompt::with(['user', 'category'])->latest()->take(10)->get(),
        ]);
    }
}
