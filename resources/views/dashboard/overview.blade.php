@extends('layouts.workspace')
@section('title','Administration')
@section('breadcrumb','Administration')
@section('heading','Marketplace overview')
@section('subtitle','Current accounts, content and fulfillment activity.')
@section('toolbar')
<a class="btn" href="{{ route('dashboard.categories') }}">Manage categories</a>
<a class="btn primary" href="{{ route('dashboard.prompts') }}">Review prompts</a>
@endsection
@section('content')
<div class="stats">
<a class="stat" href="{{ route('dashboard.prompts') }}"><p>Total prompts</p><b>{{ number_format($promptsCount) }}</b><span class="muted tiny">View all prompts →</span></a>
<a class="stat" href="{{ route('dashboard.prompts', ['status'=>'pending']) }}"><p>Pending moderation</p><b>{{ number_format($pendingCount) }}</b><span class="muted tiny">Review queue →</span></a>
<a class="stat" href="{{ route('dashboard.categories') }}"><p>Categories</p><b>{{ number_format($categoryCount) }}</b><span class="muted tiny">Manage categories →</span></a>
<div class="stat"><p>Customer and creator accounts</p><b>{{ number_format($usersCount) }}</b><span class="muted tiny">Registered users</span></div>
</div>
<div class="panel" style="margin-top:23px"><div class="flex between"><h2>Marketplace activity</h2><span class="tag">{{ $ordersCount }} completed free orders</span></div>
@if($recentPrompts->isEmpty())<div class="empty">No prompts have been submitted yet.</div>
@else<div class="stack">
@foreach($recentPrompts as $prompt)
<div class="flex between wrap" style="padding:11px 0;border-bottom:1px solid var(--line)">
<div><strong>{{ $prompt->title }}</strong><div class="muted tiny">{{ $prompt->user->name }} · {{ $prompt->category->name }} · {{ $prompt->created_at->format('d M Y') }}</div></div>
<div class="flex"><span class="pill {{ $prompt->status }}">{{ ucfirst($prompt->status) }}</span><a class="btn small" href="{{ route('dashboard.prompts', ['q'=>$prompt->title]) }}">Manage</a></div>
</div>
@endforeach</div>@endif
</div>
@endsection
