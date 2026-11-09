<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SavedPromptController extends Controller
{
    public function index(Request $request): View
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $prompts = Prompt::published()
            ->whereHas('savers', fn ($query) => $query->where('users.id', $request->user()->id))
            ->with(['category', 'featuredMedia'])
            ->withViewerSave($request->user()->id);

        if (filled($input['q'] ?? null)) {
            $prompts->where('title', 'like', '%'.$input['q'].'%');
        }

        return view('account.saved', [
            'prompts' => $prompts->orderByDesc('published_at')->orderByDesc('id')
                ->paginate(12)->withQueryString(),
        ]);
    }

    public function store(Request $request, Prompt $prompt): RedirectResponse
    {
        abort_unless($prompt->status === 'published', 404);

        // Database uniqueness and insertOrIgnore make repeated saves idempotent.
        DB::table('prompt_saves')->insertOrIgnore([
            'user_id' => $request->user()->id,
            'prompt_id' => $prompt->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Prompt saved for later.');
    }

    public function destroy(Request $request, Prompt $prompt): RedirectResponse
    {
        // Remove only this user's bookmark, including one from a now-unpublished prompt.
        DB::table('prompt_saves')->where('user_id', $request->user()->id)
            ->where('prompt_id', $prompt->id)->delete();

        return back()->with('success', 'Prompt removed from your saved list.');
    }
}
