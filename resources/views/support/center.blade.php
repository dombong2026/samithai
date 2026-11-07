@extends('layouts.site')
@section('title','Support Center')
@section('content')
<section class="hero"><div class="container"><div class="eyebrow">Help & guidance</div><h1>Support Center</h1><p>Browse help articles or contact our team from your account.</p>
<div class="flex wrap">@auth<a href="{{ route('account.support') }}" class="btn primary">My support tickets</a>@else<a href="{{ route('login') }}" class="btn primary">Sign in for support</a>@endauth</div></div></section>
<section class="section"><div class="container"><div class="flex between"><h2>Knowledge base</h2><span class="muted">{{ $articles->total() }} published articles</span></div>
<div class="category-grid">
@forelse($articles as $entry)
<a href="{{ route('support.articles.show',$entry->slug) }}" class="panel stack"><h3>{{ $entry->title }}</h3><p>{{ $entry->excerpt }}</p><span class="muted">Read article →</span></a>
@empty<div class="panel empty">Knowledge base articles will appear when published.</div>@endforelse
</div><div class="table-foot">{{ $articles->links() }}</div></div></section>
@endsection
