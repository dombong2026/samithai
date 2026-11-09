@extends('layouts.workspace')
@section('title', 'Saved prompts')
@section('breadcrumb', 'Account / Saved prompts')
@section('heading', 'Saved prompts')
@section('subtitle', 'Your private shortlist of published prompts to revisit later.')
@section('toolbar')
<a class="btn" href="{{ route('prompts.index') }}">Explore marketplace</a>
@endsection
@section('content')
<form class="filter-bar" method="get" action="{{ route('account.saved') }}">
<label for="saved-search" class="sr-only">Search saved prompts</label>
<input id="saved-search" name="q" placeholder="Search saved prompts" value="{{ request('q') }}" maxlength="100">
<button class="btn primary" type="submit">Search</button>
<a class="btn" href="{{ route('account.saved') }}">Clear</a>
</form>
@if($prompts->isEmpty())
<div class="panel empty"><h3>No saved prompts found</h3>
<p>Save a prompt from the marketplace to return to it quickly. Unpublished prompts are hidden here.</p>
<a class="btn primary" href="{{ route('prompts.index') }}">Browse prompts</a></div>
@else
<div class="prompt-grid">
@foreach($prompts as $prompt)
<x-prompt-card :prompt="$prompt" />
@endforeach
</div>
<div class="table-foot"><span class="muted">{{ $prompts->total() }} saved prompts</span>{{ $prompts->links() }}</div>
@endif
@endsection
