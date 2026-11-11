@extends('layouts.workspace')
@section('title','Editorial Revision')
@section('heading','Revision detail')
@section('subtitle','Private historical snapshot for review; never publicly accessible.')
@section('toolbar')
<a href="{{ route('dashboard.editorial.show',$entry) }}" class="btn">Back to review history</a>
@endsection
@section('content')
<div class="panel" style="padding:22px;max-width:900px">
<h2>{{ $snapshot['title'] ?? 'Untitled revision' }}</h2>
<p class="muted">Revision #{{ $revision->id }} · {{ ucfirst(str_replace('_',' ',$revision->action)) }} · {{ $revision->created_at?->format('d M Y H:i') }} · {{ $revision->actor?->name ?? 'Scheduler' }}</p>
<p>{{ $snapshot['excerpt'] ?? '' }}</p>
<h3>Previously saved article markup</h3>
<pre style="white-space:pre-wrap;overflow-wrap:anywhere;max-height:520px;overflow:auto;padding:16px;border-radius:12px;background:var(--surface,#0b1729)"><code>{{ $snapshot['body_html'] ?? '' }}</code></pre>
<p class="tiny muted">This view escapes stored HTML so that scripts and embedded content cannot execute. Restoring always sanitizes the saved version and requires a new editorial review.</p>
@if($entry->status === 'draft')
<form method="post" action="{{ route('dashboard.editorial.restore',['entry'=>$entry,'revision'=>$revision]) }}" data-confirm="Restore this historical version as a draft and invalidate the current approval?">
@csrf
<button type="submit" class="btn">Restore this version to draft</button>
</form>
@else
<div class="notice">Unpublish this content before restoring a historical version.</div>
@endif
</div>
@endsection
