@extends('layouts.site')
@section('title','Discover AI prompts')
@section('content')
<section class="hero"><div class="container hero-inner"><div>
<div class="eyebrow">Samith AI · Prompt marketplace</div>
<h1>Find the right prompt. Make every idea count.</h1>
<p>Explore practical AI prompts from creators across different fields. Save time, improve workflows and build your personal prompt library.</p>
<div class="flex wrap"><a href="{{ route('prompts.index') }}" class="btn primary">Browse prompts</a>
@guest<a href="{{ route('register') }}" class="btn">Create account</a>@endguest
@auth<a href="{{ route('account') }}" class="btn">My workspace</a>@endauth</div>
</div><div class="hero-art" aria-hidden="true"><div class="hero-chip orange"><span class="eyebrow">Create</span><b>Prompt</b></div><div class="hero-chip blue"><span class="eyebrow">Discover</span><b>Explore</b></div><div class="hero-chip blue"><span class="eyebrow">Own</span><b>Library</b></div><div class="hero-chip orange"><span class="eyebrow">Work</span><b>Faster</b></div></div></div></section>
<section class="section-tight"><div class="container">
<div class="flex between"><h2>Explore categories</h2><a class="btn small" href="{{ route('prompts.index') }}">All prompts</a></div>
<div class="category-grid">
@forelse($categories as $category)
<a class="category-tile" href="{{ route('categories.show', $category) }}"><span>{{ $category->name }}</span><span>↗</span></a>
@empty<div class="panel empty">Categories will appear when published by an administrator.</div>
@endforelse
</div></div></section>
<section class="section"><div class="container"><div class="flex between"><div><div class="eyebrow">Marketplace</div><h2>Recently published prompts</h2></div><a class="btn" href="{{ route('prompts.index') }}">Explore all</a></div>
@if($prompts->isEmpty())<div class="panel empty"><h3>No published prompts yet</h3><p>Approved creator prompts will appear here.</p></div>
@else<div class="prompt-grid">@foreach($prompts as $prompt)<x-prompt-card :prompt="$prompt" />@endforeach</div>@endif
</div></section>
@endsection
