@extends('layouts.workspace')
@section('title','My account')
@section('breadcrumb','Account')
@section('heading','Account dashboard')
@section('subtitle','Your prompts, orders and account activity.')
@section('toolbar')
<a class="btn" href="{{ route('prompts.index') }}">Explore prompts</a>
@if(auth()->user()->isCreator())<a class="btn primary" href="{{ route('account.prompts') }}">Manage prompts</a>@endif
@endsection
@section('content')
<div class="stats">
<a class="stat" href="{{ route('account.library') }}"><p>My prompt library</p><b>{{ number_format($libraryCount) }}</b><span class="muted tiny">Open library →</span></a>
<a class="stat" href="{{ route('cart.index') }}"><p>Shopping cart</p><b>{{ number_format($cartCount) }}</b><span class="muted tiny">Review cart →</span></a>
<div class="stat"><p>Completed orders</p><b>{{ number_format($ordersCount) }}</b><span class="muted tiny">Free checkout records</span></div>
@if(auth()->user()->isCreator())<a class="stat" href="{{ route('account.prompts') }}"><p>My published and draft prompts</p><b>{{ number_format($promptsCount) }}</b><span class="muted tiny">Manage prompts →</span></a>
@else<a class="stat" href="{{ route('account.settings') }}"><p>Account</p><b>✓</b><span class="muted tiny">Manage profile →</span></a>@endif
</div>
<div class="panel" style="margin-top:24px"><h2>Quick actions</h2>
<div class="flex wrap">
<a class="btn" href="{{ route('account.library') }}">My library</a>
<a class="btn" href="{{ route('account.settings') }}">Account settings</a>
@if(!auth()->user()->isCreator())
<form method="post" action="{{ route('account.creator') }}">@csrf<button type="submit" class="btn primary">Start creating prompts</button></form>
@else<a class="btn primary" href="{{ route('account.prompts') }}">Publish a prompt</a>@endif
</div></div>
@endsection
