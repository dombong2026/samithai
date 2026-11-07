@extends('layouts.site')
@section('title','Policies')
@section('content')
<section class="section"><div class="container" style="max-width:960px"><div class="eyebrow">Legal</div><h1>Marketplace policies</h1>
<p>Review the published legal and marketplace policies. These terms are managed by the administrator.</p>
<div class="stack">@forelse($entries as $entry)
<a class="category-tile" href="{{ route('policies.show',$entry->slug) }}"><span>{{ $entry->title }}</span><span>View policy →</span></a>
@empty<div class="panel empty">Marketplace policies have not been published yet.</div>@endforelse</div>
</div></section>
@endsection
