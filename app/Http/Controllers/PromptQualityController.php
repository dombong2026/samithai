<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\PromptQualityReview;
use App\Support\AuditTrail;
use App\Support\PromptQualityGate;
use App\Support\StarterPromptScenarios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PromptQualityController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isCreator(), 403);
        $isAdmin = $request->user()->isAdmin();
        if ($request->routeIs('dashboard.prompt-quality')) {
            abort_unless($isAdmin, 403);
        }
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'rejected', 'pending', 'published'])],
        ]);
        $query = Prompt::query()->where('slug', 'like', 'starter-2026-%');
        if (!$isAdmin || $request->routeIs('account.prompt-quality')) {
            $query->where('user_id', $request->user()->getKey());
        }
        $base = clone $query;
        if (filled($filters['q'] ?? null)) {
            $query->where('title', 'like', '%'.$filters['q'].'%');
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return view('dashboard.prompt-quality-index', [
            'prompts' => $query->with(['category:id,name', 'user:id,name', 'latestQualityReview.reviewer:id,name,role'])
                ->orderByDesc('updated_at')->orderByDesc('id')->paginate(10)->withQueryString(),
            'totalCount' => (clone $base)->count(),
            'untestedCount' => (clone $base)->doesntHave('qualityReviews')->count(),
            'draftCount' => (clone $base)->where('status', 'draft')->count(),
            'isAdmin' => $isAdmin && $request->routeIs('dashboard.prompt-quality'),
        ]);
    }

    private function authorizePrompt(Request $request, Prompt $prompt): void
    {
        $user = $request->user();
        abort_unless($user && $user->isCreator(), 403);
        abort_unless($user->isAdmin() || $prompt->user_id === $user->getKey(), 403);
    }

    public function show(Request $request, Prompt $prompt): View
    {
        $this->authorizePrompt($request, $prompt);

        return view('dashboard.prompt-quality', [
            'prompt' => $prompt->load(['category:id,name', 'user:id,name']),
            'readiness' => PromptQualityGate::status($prompt),
            'requiresGate' => PromptQualityGate::requiresReview($prompt),
            'editorial' => StarterPromptScenarios::forPrompt($prompt),
            'records' => $prompt->qualityReviews()
                ->with('reviewer:id,name')
                ->orderByDesc('id')->paginate(10),
        ]);
    }

    public function store(Request $request, Prompt $prompt): RedirectResponse
    {
        $this->authorizePrompt($request, $prompt);
        $data = $request->validate([
            'model_name' => ['required', 'string', 'min:2', 'max:120'],
            'model_version' => ['nullable', 'string', 'max:120'],
            'test_scenario' => ['required', 'string', 'min:20', 'max:1500'],
            'findings' => ['required', 'string', 'min:30', 'max:4000'],
            'result' => ['required', Rule::in(['passed', 'needs_revision'])],
            'verified_execution' => ['accepted'],
            'check_accuracy' => ['nullable', 'boolean'],
            'check_structure' => ['nullable', 'boolean'],
            'check_safety' => ['nullable', 'boolean'],
            'check_reuse' => ['nullable', 'boolean'],
        ]);
        if ($data['result'] === 'passed') {
            abort_unless($request->user()->isAdmin(), 403);
        }
        $checks = [
            'accuracy' => $request->boolean('check_accuracy'),
            'structure' => $request->boolean('check_structure'),
            'safety' => $request->boolean('check_safety'),
            'reuse' => $request->boolean('check_reuse'),
        ];
        if ($data['result'] === 'passed' && in_array(false, $checks, true)) {
            return back()->withErrors(['result' => 'All four quality checks must pass before recording an accepted test.'])
                ->withInput();
        }

        DB::transaction(function () use ($request, $prompt, $data, $checks): void {
            $locked = Prompt::query()->whereKey($prompt->getKey())->lockForUpdate()->firstOrFail();
            $this->authorizePrompt($request, $locked);
            abort_unless(in_array($locked->status, ['draft', 'rejected'], true), 422);
            $review = $locked->qualityReviews()->create([
                'reviewer_id' => $request->user()->getKey(),
                'result' => $data['result'],
                'model_name' => trim($data['model_name']),
                'model_version' => trim((string) ($data['model_version'] ?? '')) ?: null,
                'test_scenario' => $data['test_scenario'],
                'findings' => $data['findings'],
                'checks' => $checks,
                'content_sha256' => PromptQualityGate::fingerprint($locked),
            ]);
            AuditTrail::record($request, 'prompt.quality.record', $review, [
                'prompt_id' => $locked->getKey(), 'result' => $data['result'],
            ]);
        });

        return redirect()->route('account.prompts.quality', $prompt)
            ->with('success', 'Test evidence recorded. Publication status has not changed.');
    }
}
