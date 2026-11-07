@extends('layouts.site')
@section('title',$document->title)
@section('content')
<section class="section"><div class="container" style="max-width:900px">
<div class="eyebrow">Published legal document · v{{ $document->version }}</div>
<h1>{{ $document->title }}</h1>
<p class="muted">Effective {{ $document->effective_at?->format('d M Y') }} · Reference: {{ $document->review_reference }}</p>
<article class="panel document-content">{!! $document->body_html !!}</article>
</div></section>
@endsection
