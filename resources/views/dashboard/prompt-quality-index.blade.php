@extends('layouts.workspace')
@section('title','Prompt Quality Center')
@section('breadcrumb', $isAdmin ? 'Administration / Prompt Quality' : 'Creator / Prompt Quality')
@section('heading','Prompt Quality Center')
@section('subtitle','Track real human testing, findings, and revision readiness for the Starter 30 collection.')
@section('toolbar')
<a class="btn" href="{{ $isAdmin ? route('dashboard.prompts', ['status'=>'draft']) : route('account.prompts', ['status'=>'draft']) }}"><x-nav-icon name="posts"/> Draft prompts</a>
@endsection
@section('content')
<section class="prompt-qa-intro">
<div><div class="eyebrow">EDITORIAL QUALITY CONTROL</div><h2>Review before publishing</h2>
<p>Test prompts in a real AI tool, record the model and a sanitized case, and save your findings. Only an administrator can record a passing test. The Starter 30 cannot be submitted or published until the current revision has a passing record.</p></div>
<div class="prompt-qa-steps"><span><x-nav-icon name="check"/> Human-reviewed</span><span><x-nav-icon name="security"/> Private evidence</span></div>
</section>
<div class="prompt-qa-kpis">
<div><span>Starter products</span><strong>{{ number_format($totalCount) }}</strong></div>
<div><span>Never tested</span><strong>{{ number_format($untestedCount) }}</strong></div>
<div><span>Currently drafts</span><strong>{{ number_format($draftCount) }}</strong></div>
</div>
<form class="filter-bar prompt-qa-filter" method="get" action="{{ $isAdmin ? route('dashboard.prompt-quality') : route('account.prompt-quality') }}">
<label class="sr-only" for="qa-search">Search prompts</label>
<input type="search" id="qa-search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Find a starter prompt...">
<label class="sr-only" for="qa-status">Publication status</label>
<select id="qa-status" name="status">
<option value="">All publication statuses</option>
@foreach(['draft'=>'Draft','rejected'=>'Rejected','pending'=>'Pending','published'=>'Published'] as $value=>$label)
<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
@endforeach
</select>
<button type="submit" class="btn primary">Apply</button>
<a class="btn" href="{{ $isAdmin ? route('dashboard.prompt-quality') : route('account.prompt-quality') }}">Clear</a>
</form>
<div class="table-scroll prompt-qa-table">
<table><thead><tr><th>No.</th><th>Prompt</th>@if($isAdmin)<th>Creator</th>@endif<th>Category</th><th>Publication</th><th>Test readiness</th><th>Last updated</th><th>Action</th></tr></thead>
<tbody>
@forelse($prompts as $prompt)
@php($qaStatus = \App\Support\PromptQualityGate::status($prompt))
<tr>
<td>{{ $prompts->firstItem()+$loop->index }}</td>
<td><strong>{{ $prompt->title }}</strong><div class="muted tiny">{{ \Illuminate\Support\Str::limit($prompt->summary, 75) }}</div></td>
@if($isAdmin)<td>{{ $prompt->user?->name ?? 'Former user' }}</td>@endif
<td>{{ $prompt->category?->name ?? '—' }}</td>
<td><span class="pill {{ $prompt->status }}">{{ ucfirst($prompt->status) }}</span></td>
<td><span @class(['prompt-qa-status','is-passed' => $qaStatus==='Passed human QA','is-stale' => $qaStatus==='Revision needs retest'])>{{ $qaStatus }}</span></td>
<td>{{ $prompt->updated_at->format('d M Y') }}</td>
<td><a href="{{ route('account.prompts.quality', $prompt) }}" class="btn small"><x-nav-icon name="review"/> Review tests</a></td>
</tr>
@empty
<tr><td colspan="{{ $isAdmin ? 8 : 7 }}" class="empty">No matching starter products. Try another filter.</td></tr>
@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$prompts])
@endsection
