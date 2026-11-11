@extends('layouts.workspace')
@section('title','Prompt quality testing')
@section('breadcrumb','Prompt Manager / Quality')
@section('heading','Prompt quality testing')
@section('subtitle','Keep evidence of real model testing private and tied to the exact prompt revision.')
@section('toolbar')
<a href="{{ auth()->user()->isAdmin() ? route('dashboard.prompt-quality') : route('account.prompt-quality') }}" class="btn"><x-nav-icon name="arrow-up-right"/> Quality Center</a>
@if(in_array($prompt->status,['draft','rejected'],true))
<button type="button" class="btn primary" data-modal-open="prompt-qa-record-dialog"><x-nav-icon name="plus"/> Record test</button>
@endif
@endsection
@section('content')
<section class="prompt-qa-detail-heading">
<div class="eyebrow">PROMPT REVIEW</div>
<div class="prompt-qa-detail-row">
<div><h2>{{ $prompt->title }}</h2><p class="muted tiny">Category: {{ $prompt->category?->name ?? 'Unknown' }} · Creator: {{ $prompt->user?->name ?? 'Former account' }}</p></div>
<span class="pill {{ $prompt->status }}">{{ ucfirst($prompt->status) }}</span>
</div>
<div class="prompt-qa-readiness">
<div><span class="muted tiny">Current test readiness</span><strong>{{ $readiness }}</strong></div>
<div><span class="muted tiny">Review requirement</span><strong>{{ $requiresGate ? 'Required before submission' : 'Optional for legacy products' }}</strong></div>
<div><span class="muted tiny">Test history</span><strong>{{ $records->total() }} {{ $records->total() === 1 ? 'record' : 'records' }}</strong></div>
</div>
<p class="prompt-qa-disclaimer">This area records human-declared results. It does not contact an AI provider or certify prompt accuracy. Only sanitized test cases and findings should be entered.</p>
</section>
@if($editorial)
<section class="prompt-qa-guidance" aria-labelledby="qa-editorial-heading">
<div class="flex between wrap"><div><div class="eyebrow">STARTER COLLECTION</div><h2 id="qa-editorial-heading">Suggested test scenario</h2></div><span class="muted tiny">From the reviewed content source</span></div>
<p class="prompt-qa-scenario">{{ $editorial['scenario'] }}</p>
@if(count($editorial['checks']))
<h3>Product-specific review checks</h3>
<ul>@foreach($editorial['checks'] as $check)<li>{{ $check }}</li>@endforeach</ul>
@endif
</section>
@endif
<section class="prompt-qa-history" aria-labelledby="qa-history-title">
<div class="prompt-qa-section-heading"><div><div class="eyebrow">TEST HISTORY</div><h2 id="qa-history-title">Recorded evidence</h2><p class="muted tiny">Append-only records preserve the reviewer, model and outcome for each prompt revision.</p></div></div>
@if($records->isEmpty())
<div class="prompt-qa-empty"><x-nav-icon name="clock"/> No test evidence recorded yet. Use a real AI model, then record the findings.</div>
@else
@foreach($records as $record)
<div class="prompt-qa-evidence">
<div class="prompt-qa-evidence-top">
<span @class(['prompt-qa-status','is-passed' => $record->result === 'passed'])>{{ $record->result === 'passed' ? 'Passed human QA' : 'Needs revision' }}</span>
<time datetime="{{ $record->created_at->toIso8601String() }}">{{ $record->created_at->format('d M Y, H:i') }}</time>
</div>
<div class="prompt-qa-evidence-meta">
<span><strong>Reviewer:</strong> {{ $record->reviewer?->name ?? 'Former account' }}</span>
<span><strong>Model:</strong> {{ $record->model_name }}{{ filled($record->model_version) ? ' · '.$record->model_version : '' }}</span>
<span><strong>Revision:</strong> {{ $record->content_sha256 === \App\Support\PromptQualityGate::fingerprint($prompt) ? 'Matches current draft' : 'Older version' }}</span>
</div>
<details><summary>Test case and reviewer findings</summary>
<p class="prompt-qa-case-label">Test scenario</p><p>{{ $record->test_scenario }}</p>
<p class="prompt-qa-case-label">Observations</p><p>{{ $record->findings }}</p>
<div class="prompt-qa-evidence-checks">
@foreach(['accuracy'=>'Factual accuracy','structure'=>'Output structure','safety'=>'Safety and privacy','reuse'=>'Reusable with a second input'] as $key=>$label)
<span>{{ !empty($record->checks[$key]) ? '✓' : '○' }} {{ $label }}</span>
@endforeach
</div>
</details>
</div>
@endforeach
@include('partials.pagination',['paginator'=>$records])
@endif
</section>
@if(in_array($prompt->status,['draft','rejected'],true))
<dialog id="prompt-qa-record-dialog" class="prompt-qa-dialog">
<div class="modal-head"><h2>Record a model test</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close test editor"><x-nav-icon name="x"/></button></div>
<form method="post" action="{{ route('account.prompts.quality.store',$prompt) }}">
@csrf
<div class="modal-content stack">
<p class="muted tiny">Run the prompt against a real AI model first. This form does not execute a test or publish a product. Never paste confidential customer information.</p>
<div class="form-grid">
<div class="field-group"><label for="qa-model">AI model used</label><input id="qa-model" class="field" name="model_name" maxlength="120" minlength="2" required placeholder="e.g. ChatGPT or Claude" value="{{ old('model_name') }}"></div>
<div class="field-group"><label for="qa-version">Model version (optional)</label><input id="qa-version" class="field" name="model_version" maxlength="120" placeholder="Model/version as tested" value="{{ old('model_version') }}"></div>
</div>
<div class="field-group"><label for="qa-scenario">Anonymized test scenario</label><textarea id="qa-scenario" class="field" name="test_scenario" minlength="20" maxlength="1500" rows="3" required placeholder="Inputs used and expected outcome.">{{ old('test_scenario', $editorial['scenario'] ?? '') }}</textarea></div>
<div class="field-group"><label for="qa-findings">Observed result and follow-up actions</label><textarea id="qa-findings" class="field" name="findings" minlength="30" maxlength="4000" rows="5" required placeholder="What actually worked, what failed, what to revise.">{{ old('findings') }}</textarea></div>
<fieldset class="prompt-qa-check-fieldset">
<legend>Evidence checklist</legend>
@foreach(['check_accuracy'=>'Facts and claims verified','check_structure'=>'Required output format met','check_safety'=>'Responsible-use and privacy checks passed','check_reuse'=>'Re-tested with an independent input'] as $name=>$label)
<label><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name))> {{ $label }}</label>
@endforeach
</fieldset>
<div class="field-group"><label for="qa-result">Review outcome</label>
<select name="result" id="qa-result" class="field" required>
<option value="needs_revision" @selected(old('result')==='needs_revision')>Needs revision</option>
@if(auth()->user()->isAdmin())
<option value="passed" @selected(old('result')==='passed')>Passed (all four checks required)</option>
@endif
</select></div>
<label class="prompt-qa-attestation"><input type="checkbox" name="verified_execution" value="1" required> I confirm I personally tested this version using the stated AI model and recorded actual, anonymized observations.</label>
</div>
<div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn primary">Save test evidence</button></div>
</form>
</dialog>
@endif
@endsection
