<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Prompt;
use App\Models\MediaAsset;
use App\Support\AuditTrail;
use App\Support\PromptQualityGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PromptController extends Controller
{
    private function authorizeCreator(): void
    {
        abort_unless(auth()->user()?->isCreator(), 403);
    }

    private function authorizePrompt(Prompt $prompt): void
    {
        abort_unless(auth()->user()?->isAdmin() || $prompt->user_id === auth()->id(), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeCreator();
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,pending,published,rejected']]);
        $isAdmin = $request->user()->isAdmin();
        if ($request->routeIs('dashboard.prompts')) {
            abort_unless($isAdmin, 403);
        }
        $query = Prompt::with(['category', 'user', 'latestReview'])->withCount('reviewEvents');
        if (! $isAdmin || $request->routeIs('account.prompts')) {
            $query->where('user_id', $request->user()->id);
        }
        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->input('q').'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return view('dashboard.prompts', [
            'prompts' => $query->latest()->paginate(10)->withQueryString(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'isAdmin' => $isAdmin,
            'mediaAssets' => MediaAsset::where('user_id', $request->user()->id)->whereIn('kind', ['image','video','youtube','color'])->latest()->limit(80)->get(),
        ]);
    }

    private function validatePrompt(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('is_active', true)],
            'summary' => ['required', 'string', 'max:3000'],
            'content' => ['required', 'string', 'max:100000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'license_type' => ['required', 'in:regular,extended'],
            'featured_media_id' => ['nullable', 'integer', Rule::exists('media_assets','id')->where('user_id', auth()->id())],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCreator();
        $data = $this->validatePrompt($request);
        $data['user_id'] = $request->user()->id;
        $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(8));
        $data['status'] = 'draft';
        $prompt = Prompt::create($data);
        AuditTrail::record($request, 'prompt.create', $prompt);

        return back()->with('success', 'Prompt saved as a draft. Submit it for review when ready.');
    }

    public function update(Request $request, Prompt $prompt): RedirectResponse
    {
        $this->authorizeCreator();
        $this->authorizePrompt($prompt);
        $data = $this->validatePrompt($request);
        if (! $request->user()->isAdmin()) {
            $data['status'] = 'draft';
            $data['published_at'] = null;
        }
        $prompt->update($data);
        AuditTrail::record($request, 'prompt.update', $prompt);

        return back()->with('success', 'Prompt updated.');
    }

    public function destroy(Prompt $prompt): RedirectResponse
    {
        $this->authorizeCreator();
        $this->authorizePrompt($prompt);
        if (OrderItem::where('prompt_id', $prompt->id)->exists()) {
            return back()->withErrors(['prompt' => 'This prompt has customer orders and cannot be deleted.']);
        }
        $prompt->delete();
        AuditTrail::record(request(), 'prompt.delete', $prompt);

        return back()->with('success', 'Prompt deleted.');
    }

    public function submit(Prompt $prompt): RedirectResponse
    {
        $this->authorizeCreator();
        $this->authorizePrompt($prompt);

        DB::transaction(function () use ($prompt): void {
            $locked = Prompt::whereKey($prompt->id)->lockForUpdate()->firstOrFail();
            $this->authorizePrompt($locked);
            abort_unless(in_array($locked->status, ['draft', 'rejected'], true), 422);
            if (PromptQualityGate::requiresReview($locked)) {
                abort_unless(PromptQualityGate::passes($locked), 422,
                    'This starter prompt requires a current administrator-approved test record before submission.');
            }

            $locked->update(['status' => 'pending', 'published_at' => null]);
            $locked->reviewEvents()->create([
                'actor_id' => auth()->id(),
                'action' => 'submitted',
            ]);
            AuditTrail::record(request(), 'prompt.submit', $locked);
        });

        return back()->with('success', 'Prompt submitted for administrator review.');
    }

    public function reviewHistory(Request $request, Prompt $prompt): View
    {
        $this->authorizeCreator();
        $this->authorizePrompt($prompt);

        return view('dashboard.prompt-reviews', [
            'prompt' => $prompt,
            'reviews' => $prompt->reviewEvents()->with('actor:id,name')->latest('id')
                ->paginate(20),
        ]);
    }

    public function moderate(Request $request, Prompt $prompt): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'review_note' => [
                Rule::requiredIf(fn () => $request->input('decision') === 'reject'),
                'nullable', 'string', 'min:10', 'max:1200',
            ],
        ]);

        $approve = $data['decision'] === 'approve';
        $note = trim((string) ($data['review_note'] ?? ''));
        DB::transaction(function () use ($prompt, $request, $approve, $note): void {
            $locked = Prompt::whereKey($prompt->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422);
            if ($approve && PromptQualityGate::requiresReview($locked)) {
                abort_unless(PromptQualityGate::passes($locked), 422,
                    'This starter prompt cannot be published without current verified quality evidence.');
            }

            $locked->update([
                'status' => $approve ? 'published' : 'rejected',
                'published_at' => $approve ? now() : null,
            ]);
            $locked->reviewEvents()->create([
                'actor_id' => $request->user()->id,
                'action' => $approve ? 'approved' : 'rejected',
                'note' => $note !== '' ? $note : null,
            ]);
            AuditTrail::record($request, 'prompt.moderate', $locked, [
                'decision' => $approve ? 'approve' : 'reject',
            ]);
        });

        return back()->with('success', $approve
            ? 'Prompt published.'
            : 'Prompt returned to the creator with written review feedback.');
    }
}
