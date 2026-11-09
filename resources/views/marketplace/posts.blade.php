@extends('layouts.site')
@section('title','Blog')
@section('content')
@php($pageCopy = \App\Support\FrontendPageCopy::values('blog'))
<section class="section"><div class="container"><div class="eyebrow">{{ $pageCopy['eyebrow'] }}</div><h1>{{ $pageCopy['title'] }}</h1>
<div class="category-grid">
@forelse($entries as $entry)
<a class="panel stack" href="{{ route('blog.show',$entry->slug) }}"><span class="tag">{{ $entry->published_at?->format('d M Y') }}</span><h2>{{ $entry->title }}</h2><p>{{ $entry->excerpt }}</p><span class="muted">{{ $pageCopy['article_cta'] }}</span></a>
@empty<div class="panel empty">{{ $pageCopy['empty_message'] }}</div>@endforelse
</div><div class="table-foot">{{ $entries->links() }}</div></div></section>
@endsection
