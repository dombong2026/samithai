@extends('layouts.site')
@section('title','Policies')
@section('content')
@php($pageCopy = \App\Support\FrontendPageCopy::values('policies'))
<section class="section"><div class="container" style="max-width:960px"><div class="eyebrow">{{ $pageCopy['eyebrow'] }}</div><h1>{{ $pageCopy['title'] }}</h1>
<p>{{ $pageCopy['description'] }}</p>
<div class="stack">@forelse($entries as $entry)
<a class="category-tile" href="{{ route('policies.show',$entry->slug) }}"><span>{{ $entry->title }}</span><span>{{ $pageCopy['policy_cta'] }}</span></a>
@empty<div class="panel empty">{{ $pageCopy['empty_message'] }}</div>@endforelse</div>
</div></section>
@endsection
