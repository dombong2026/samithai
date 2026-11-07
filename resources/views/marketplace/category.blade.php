@extends('layouts.site')
@section('title',$category->name)
@section('content')
<section class="section"><div class="container">
<div class="eyebrow">Category</div><h1>{{ $category->name }}</h1><p>{{ $category->description }}</p>
<div class="flex between" style="margin-bottom:20px"><a class="btn small" href="{{ route('prompts.index') }}">Browse all prompts</a><span class="muted">{{ $prompts->total() }} prompts</span></div>
@if($prompts->isEmpty())<div class="panel empty"><h3>No published prompts in this category</h3></div>
@else<div class="prompt-grid">@foreach($prompts as $prompt)<x-prompt-card :prompt="$prompt" />@endforeach</div>
<div class="table-foot">{{ $prompts->links() }}</div>@endif
</div></section>
@endsection
