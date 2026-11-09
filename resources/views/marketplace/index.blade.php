@extends('layouts.site')
@section('title','Browse AI prompts')
@section('content')
@php($pageCopy = \App\Support\FrontendPageCopy::values('catalog'))
<section class="section"><div class="container">
<div class="eyebrow">{{ $pageCopy['eyebrow'] }}</div><h1>{{ $pageCopy['title'] }}</h1><p>{{ $pageCopy['description'] }}</p>
@if($pageCopy['show_filters'])
<form class="filter-bar" action="{{ route('prompts.index') }}" method="get">
<label class="sr-only" for="q">Search</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Search prompts">
<label class="sr-only" for="category">Category</label><select id="category" name="category"><option value="">All categories</option>
@foreach($categories as $cat)<option value="{{ $cat->slug }}" @selected(request('category') === $cat->slug)>{{ $cat->name }}</option>@endforeach</select>
<label class="sr-only" for="price">Price</label><select id="price" name="price"><option value="">All prices</option><option value="free" @selected(request('price') === 'free')>Free</option><option value="paid" @selected(request('price') === 'paid')>Paid</option></select>
<label class="sr-only" for="sort">Sort prompts</label><select id="sort" name="sort" aria-label="Sort prompts">
<option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest first</option>
<option value="most_saved" @selected(request('sort') === 'most_saved')>Most saved</option>
<option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
<option value="title_az" @selected(request('sort') === 'title_az')>Title A–Z</option>
<option value="price_asc" @selected(request('sort') === 'price_asc')>Price: low to high</option>
<option value="price_desc" @selected(request('sort') === 'price_desc')>Price: high to low</option>
</select>
<button class="btn primary" type="submit">{{ $pageCopy['search_cta'] }}</button><a class="btn" href="{{ route('prompts.index') }}">{{ $pageCopy['clear_cta'] }}</a>
</form>
@endif
@if($prompts->isEmpty())<div class="panel empty"><h3>{{ $pageCopy['empty_title'] }}</h3><p>{{ $pageCopy['empty_message'] }}</p></div>
@else<div class="prompt-grid">@foreach($prompts as $prompt)<x-prompt-card :prompt="$prompt" />@endforeach</div>
<div class="table-foot"><span class="muted">{{ $prompts->total() }} prompts</span>{{ $prompts->links() }}</div>@endif
</div></section>
@endsection
