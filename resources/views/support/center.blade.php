@extends('layouts.site')
@section('title','Support Center')
@section('content')
@php($pageCopy = \App\Support\FrontendPageCopy::values('support'))
<section class="hero"><div class="container"><div class="eyebrow">{{ $pageCopy['eyebrow'] }}</div><h1>{{ $pageCopy['title'] }}</h1><p>{{ $pageCopy['description'] }}</p>
<div class="flex wrap">@auth<a href="{{ route('account.support') }}" class="btn primary">{{ $pageCopy['member_cta'] }}</a>@else<a href="{{ route('login') }}" class="btn primary">{{ $pageCopy['guest_cta'] }}</a>@endauth</div></div></section>
<section class="section"><div class="container"><div class="flex between"><h2>{{ $pageCopy['articles_title'] }}</h2><span class="muted">{{ $articles->total() }} published articles</span></div>
<div class="category-grid">
@forelse($articles as $entry)
@if(\App\Support\FrontendPageAvailability::enabled('support_detail'))
<a href="{{ route('support.articles.show',$entry->slug) }}" class="panel stack"><h3>{{ $entry->title }}</h3><p>{{ $entry->excerpt }}</p><span class="muted">{{ $pageCopy['article_cta'] }}</span></a>
@else<article class="panel stack"><h3>{{ $entry->title }}</h3><p>{{ $entry->excerpt }}</p></article>@endif
@empty<div class="panel empty">{{ $pageCopy['empty_message'] }}</div>@endforelse
</div><div class="table-foot">{{ $articles->links() }}</div></div></section>
@endsection
