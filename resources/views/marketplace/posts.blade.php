@extends('layouts.site')
@section('title','Blog')
@section('content')
<section class="section"><div class="container"><div class="eyebrow">Updates</div><h1>Samith AI Blog</h1>
<div class="category-grid">
@forelse($entries as $entry)
<a class="panel stack" href="{{ route('blog.show',$entry->slug) }}"><span class="tag">{{ $entry->published_at?->format('d M Y') }}</span><h2>{{ $entry->title }}</h2><p>{{ $entry->excerpt }}</p><span class="muted">Read article →</span></a>
@empty<div class="panel empty">No published articles are available yet.</div>@endforelse
</div><div class="table-foot">{{ $entries->links() }}</div></div></section>
@endsection
