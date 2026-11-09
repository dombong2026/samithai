@extends('layouts.site')
@section('title','Discover AI prompts')
@section('content')
@php($pageCopy = \App\Support\FrontendPageCopy::values('home'))
<section class="hero"><div class="container hero-inner"><div>
<div class="eyebrow">{{ $pageCopy['eyebrow'] }}</div>
<h1>{{ \App\Models\SiteSetting::text('hero_title','Find the right prompt. Make every idea count.') }}</h1>
<p>{{ \App\Models\SiteSetting::text('hero_subtitle','Explore practical AI prompts from creators across different fields.') }}</p>
<div class="flex wrap"><a href="{{ route('prompts.index') }}" class="btn primary">{{ $pageCopy['primary_cta'] }}</a>
@guest<a href="{{ route('register') }}" class="btn">{{ $pageCopy['guest_cta'] }}</a>@endguest
@auth<a href="{{ route('account') }}" class="btn">{{ $pageCopy['member_cta'] }}</a>@endauth</div>
</div>@if($pageCopy['show_hero_art'])<div class="hero-art" aria-hidden="true"><div class="hero-chip orange"><span class="eyebrow">Create</span><b>Prompt</b></div><div class="hero-chip blue"><span class="eyebrow">Discover</span><b>Explore</b></div><div class="hero-chip blue"><span class="eyebrow">Own</span><b>Library</b></div><div class="hero-chip orange"><span class="eyebrow">Work</span><b>Faster</b></div></div>@endif</div></section>
@if($pageCopy['show_categories'])
<section class="section-tight"><div class="container">
<div class="flex between"><h2>{{ $pageCopy['categories_title'] }}</h2><a class="btn small" href="{{ route('prompts.index') }}">{{ $pageCopy['categories_cta'] }}</a></div>
<div class="category-grid">
@forelse($categories as $category)
<a class="category-tile" href="{{ route('categories.show', $category) }}"><span>{{ $category->name }}</span><span>↗</span></a>
@empty<div class="panel empty">{{ $pageCopy['categories_empty'] }}</div>
@endforelse
</div></div></section>
@endif
@if($pageCopy['show_recent'])
<section class="section"><div class="container"><div class="flex between"><div><div class="eyebrow">{{ $pageCopy['recent_eyebrow'] }}</div><h2>{{ $pageCopy['recent_title'] }}</h2></div><a class="btn" href="{{ route('prompts.index') }}">{{ $pageCopy['recent_cta'] }}</a></div>
@if($prompts->isEmpty())<div class="panel empty"><h3>{{ $pageCopy['recent_empty_title'] }}</h3><p>{{ $pageCopy['recent_empty_message'] }}</p></div>
@else<div class="prompt-grid">@foreach($prompts as $prompt)<x-prompt-card :prompt="$prompt" />@endforeach</div>@endif
</div></section>
@endif
@endsection
