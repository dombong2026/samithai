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
<h3>Featured image & social image</h3>
<p class="muted tiny">Choose an original or licensed image from your private Media Library. Changes to a draft image revoke the previous editorial approval and schedule. Published content is locked.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin:14px 0">
<div>
<div class="tiny muted">Featured image</div>
@if($entry->media?->isImage())
<img src="{{ route('media.serve',$entry->media) }}" alt="Current featured image for {{ $entry->title }}" style="display:block;width:100%;height:156px;object-fit:cover;border-radius:10px;margin-top:6px">
@else
<p class="muted">No image selected</p>
@endif
</div>
<div>
<div class="tiny muted">Social sharing image</div>
@if($entry->seo?->ogMedia?->isImage())
<img src="{{ route('media.serve',$entry->seo->ogMedia) }}" alt="Current social sharing image for {{ $entry->title }}" style="display:block;width:100%;height:156px;object-fit:cover;border-radius:10px;margin-top:6px">
@else
<p class="muted">Uses the featured image by default</p>
@endif
</div>
</div>
@if($entry->status === 'draft')
<form method="post" action="{{ route('dashboard.editorial.media',$entry) }}" class="stack">
@csrf
<div class="field-group"><label for="featured-media-id">Featured image</label>
<select id="featured-media-id" class="field" name="featured_media_id">
<option value="">No featured image</option>
@foreach($availableImages as $asset)
<option value="{{ $asset->id }}" @selected((int)old('featured_media_id',$entry->featured_media_id)===$asset->id)>{{ $asset->title }}</option>
@endforeach
</select></div>
<div class="field-group"><label for="og-media-id">Social sharing image</label>
<select id="og-media-id" class="field" name="og_media_id">
<option value="">Use featured image</option>
@foreach($availableImages as $asset)
<option value="{{ $asset->id }}" @selected((int)old('og_media_id',$entry->seo?->og_media_id)===$asset->id)>{{ $asset->title }}</option>
@endforeach
</select></div>
<div class="flex wrap" style="justify-content:flex-end;gap:10px">
<a class="btn" href="{{ route('dashboard.media') }}">Upload images</a>
<button class="btn primary" type="submit">Save images</button>
</div>
</form>
@else
<p class="muted tiny">Unpublish this content before changing featured or social images.</p>
@endif
</div>
<div class="panel" style="padding:22px">
<h3>SEO & content review</h3>
<p class="muted tiny">Confirm accuracy, search metadata and media permissions before approving. A fallback title or description may be acceptable only after manual review.</p>
<div class="stack">
<div><strong>Article title</strong><p class="tiny muted">{{ $entry->title }}</p></div>
<div><strong>SEO title</strong><p class="tiny muted">{{ $entry->seo?->seo_title ?: 'Automatic title fallback — check before approving' }}</p></div>
<div><strong>Meta description</strong><p class="tiny muted">{{ $entry->seo?->meta_description ?: 'Automatic description fallback — check before approving' }}</p></div>
<div><strong>Canonical URL</strong><p class="tiny muted">{{ $entry->seo?->canonical_path ?: 'Automatic canonical path' }}</p></div>
<div class="flex wrap" style="justify-content:flex-end">
<a class="btn" href="{{ route('dashboard.seo.edit',['kind'=>$entry->kind,'id'=>$entry->id]) }}">Edit SEO metadata</a>
<a class="btn" href="{{ route('dashboard.content.preview',$entry) }}" target="_blank" rel="noopener">Preview draft</a>
</div>
</div>
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
<fieldset style="padding:12px;border:1px solid var(--line);border-radius:10px"><legend>Required before approval</legend>
<p class="tiny muted">All four checks are mandatory when choosing Approve. Requesting changes does not require them.</p>
<label class="flex wrap" style="gap:8px;margin:9px 0"><input type="checkbox" name="review_checks[accuracy]" value="1"> Verified the full article, facts and source claims</label>
<label class="flex wrap" style="gap:8px;margin:9px 0"><input type="checkbox" name="review_checks[seo]" value="1"> Reviewed SEO title, description, canonical and preview</label>
<label class="flex wrap" style="gap:8px;margin:9px 0"><input type="checkbox" name="review_checks[media]" value="1"> Reviewed featured/social images or documented intentional omission</label>
<label class="flex wrap" style="gap:8px;margin:9px 0"><input type="checkbox" name="review_checks[rights]" value="1"> Confirmed originality, licenses and publication rights</label>
</fieldset>
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
@if($entry->status==='draft' && $entry->editorial_status==='scheduled')
<form method="post" action="{{ route('dashboard.editorial.cancel',$entry) }}" style="margin-top:10px">
@csrf <button class="btn" type="submit">Cancel scheduled publication</button>
</form>
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
<p style="margin:6px 0"><a class="btn small" href="{{ route('dashboard.editorial.revision',['entry'=>$entry,'revision'=>$revision]) }}">Inspect revision</a></p>
</div>
@empty<p class="muted">No revisions recorded since installation.</p>@endforelse
<p class="muted tiny">Revision snapshots are stored privately. This view does not expose prior drafts publicly.</p>
</div>
<div class="notice">To revise public posts/pages, unpublish first and use the existing Post or Page Manager. Substantive changes invalidate prior approval.</div>
</div>
</div>
@endsection
