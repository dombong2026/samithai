<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\PromptEntitlement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreatorInsightsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isCreator(), 403);
        $id = $request->user()->id;

        $counts = Prompt::where('user_id', $id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // A save is an expression of interest, not a paid order or a view.
        $saves = \Illuminate\Support\Facades\DB::table('prompt_saves')
            ->join('prompts', 'prompts.id', '=', 'prompt_saves.prompt_id')
            ->where('prompts.user_id', $id)
            ->where('prompts.status', 'published')
            ->count();

        $claims = PromptEntitlement::whereHas(
            'prompt',
            fn ($query) => $query->where('user_id', $id)
        )->count();

        $popular = Prompt::where('user_id', $id)->published()
            ->withCount(['savers', 'entitlements'])
            ->orderByDesc('savers_count')
            ->orderByDesc('published_at')
            ->limit(10)->get();

        return view('account.creator-insights', [
            'counts' => $counts,
            'totalPrompts' => (int) $counts->sum(),
            'savedCount' => $saves,
            'licenseClaims' => $claims,
            'popular' => $popular,
        ]);
    }
}
