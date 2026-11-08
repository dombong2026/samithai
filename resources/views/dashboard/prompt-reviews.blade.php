@extends('layouts.workspace')
@section('title', 'Prompt review history')
@section('breadcrumb', 'Creator / Review history')
@section('heading', 'Review history')
@section('subtitle', 'Submission and moderation decisions for this prompt.')
@section('toolbar')
<a class="btn" href="{{ auth()->user()->isAdmin() ? route('dashboard.prompts') : route('account.prompts') }}">← Back to prompts</a>
@endsection
@section('content')
<div class="panel stack" style="max-width:940px">
<div class="flex between wrap">
<div><h2>{{ $prompt->title }}</h2><p class="muted tiny">Creator review record</p></div>
<span class="pill {{ $prompt->status }}">{{ ucfirst($prompt->status) }}</span>
</div>
@if($reviews->isEmpty())
<p class="muted">There are no recorded review events for this prompt yet.</p>
@else
@foreach($reviews as $entry)
<div class="prompt-review-event">
<div class="flex between wrap">
<strong>{{ ucfirst($entry->action) }}</strong>
<span class="muted tiny">{{ $entry->created_at->format('d M Y, H:i') }}</span>
</div>
<p class="muted tiny" style="margin:4px 0 0">By {{ $entry->actor?->name ?? 'Former account' }}</p>
@if(filled($entry->note))<p class="prompt-review-note">{{ $entry->note }}</p>@endif
</div>
@endforeach
{{ $reviews->links() }}
@endif
</div>
@endsection
