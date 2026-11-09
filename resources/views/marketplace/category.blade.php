@extends('layouts.site')
@section('title',$category->name)
@section('content')
@php($pageCopy = \App\Support\FrontendPageCopy::values('category'))
<section class="section"><div class="container">
<div class="eyebrow">{{ $pageCopy['eyebrow'] }}</div><h1>{{ $category->name }}</h1><p>{{ $category->description }}</p>
<div class="flex between" style="margin-bottom:20px">@if(\App\Support\FrontendPageAvailability::enabled('catalog'))<a class="btn small" href="{{ route('prompts.index') }}">{{ $pageCopy['all_cta'] }}</a>@endif<span class="muted">{{ $prompts->total() }} prompts</span></div>
@if($prompts->isEmpty())<div class="panel empty"><h3>{{ $pageCopy['empty_message'] }}</h3></div>
@else<div class="prompt-grid">@foreach($prompts as $prompt)<x-prompt-card :prompt="$prompt" />@endforeach</div>
<div class="table-foot">{{ $prompts->links() }}</div>@endif
</div></section>
@endsection
