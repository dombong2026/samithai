@extends('layouts.workspace')
@section('title','Editorial Review')
@section('heading','Content Review')
@section('subtitle','Review approved source, assign a person, record a decision and schedule only after approval.')
@section('toolbar')
<a href="{{ route('dashboard.editorial') }}" class="btn">Back to review queue</a>
<a href="{{ route('dashboard.content.preview',$entry) }}" target="_blank" rel="noopener" class="btn primary">Private preview</a>
@endsection
@section('content')
<div class="grid" style="display:grid;grid-template-columns: minmax(0,2fr) minmax(280px,1fr);gap:18px;align-items:start">
<div class="stack">
<div class="panel" style="padding:22px">
<div class="eyebrow">{{ ucfirst($entry->kind) }} · {{ ucfirst($entry->status) }}</div>
<h2>{{ $entry->title }}</h2>
<p>{{ $entry->excerpt }}</p>
<div class="flex wrap" style="gap:10px"><span class="tag">Editorial: {{ str_replace('_',' ',$entry->editorial_status) }}</span>
<span class="tag">Current approval: {{ $approvedCurrent ? 'Valid' : 'Not approved' }}</span></div>
@if($entry->status==='draft' && in_array($entry->editorial_status,['draft','changes_requested'],true))
<form method="post" action="{{ route('dashboard.editorial.submit',$entry) }}" style="margin-top:18px">@csrf
<button type="submit" class="btn primary">Submit for review</button></form>
@endif
</div>
<div class="panel" style="padding:22px">
<h3>Review and editorial decision</h3>
<p class="muted tiny">Record an actual human review. A passing decision is not an AI model test or independent certification.</p>
@if($entry->status==='draft')
<form method="post" action="{{ route('dashboard.editorial.assign',$entry) }}" class="stack">@csrf
<div class="field-group"><label for="reviewer_id">Assign reviewer</label>
<select id="reviewer_id" class="field" name="reviewer_id" required>
<option value="">Choose an administrator</option>
@foreach($reviewers as $person)<option value="{{ $person->id }}" @selected($entry->reviewer_id===$person->id)>{{ $person->name }} ({{ $person->email }})</option>@endforeach
</select></div><button class="btn" type="submit">Save reviewer</button></form>
@endif
@if($entry->status==='draft' && $entry->editorial_status==='in_review')
<form method="post" action="{{ route('dashboard.editorial.decide',$entry) }}" class="stack" style="margin-top:15px">@csrf
<div class="field-group"><label for="decision">Decision</label><select class="field" name="decision" id="decision" required>
<option value="request_changes">Request changes</option><option value="approve">Approve reviewed version</option>
</select></div>
<div class="field-group"><label for="review_notes">Required reviewer notes (min 20 characters)</label>
<textarea class="field" id="review_notes" name="notes" rows="4" maxlength="3000" minlength="20" required></textarea></div>
<button class="btn primary" type="submit">Record review decision</button>
</form>
@endif
</div>
<div class="panel" style="padding:22px">
<h3>Schedule approved publication</h3>
@if($entry->status==='draft' && $approvedCurrent)
<form class="stack" method="post" action="{{ route('dashboard.editorial.schedule',$entry) }}">@csrf
<div class="field-group"><label for="scheduled_for">Publish at ({{ config('app.timezone','UTC') }} server time)</label>
<input class="field" type="datetime-local" id="scheduled_for" name="scheduled_for" required></div>
<button class="btn primary" type="submit">Schedule publication</button></form>
@else
<p class="muted">Requires a current human approval of this exact draft.</p>
@endif
<p class="tiny muted">Scheduled publication requires the actual Laravel cron scheduler to be configured and verified. Scheduling alone does not prove the job has run.</p>
</div>
</div>
<div class="stack">
<div class="panel" style="padding:18px">
<h3>Revision history</h3>
@forelse($revisions as $revision)
<div style="padding:11px 0;border-bottom:1px solid var(--line)">
<strong>{{ ucfirst(str_replace('_',' ',$revision->action)) }}</strong>
<p class="tiny muted" style="margin:6px 0">{{ $revision->created_at?->format('d M Y H:i') }} · {{ $revision->actor?->name ?? 'Scheduler' }}</p>
<code style="font-size:10px">{{ substr($revision->content_sha256,0,16) }}…</code>
</div>
@empty<p class="muted">No revisions recorded since installation.</p>@endforelse
<p class="muted tiny">Revision snapshots are stored privately. This view does not expose prior drafts publicly.</p>
</div>
<div class="notice">To revise public posts/pages, unpublish first and use the existing Post or Page Manager. Substantive changes invalidate prior approval.</div>
</div>
</div>
@endsection
