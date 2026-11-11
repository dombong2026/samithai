@extends('layouts.site')
@section('title',($display['title'] ?? $entry->title))
@if(isset($seo))
@section('seo_title', $seo['title'])
@section('seo_head')
@include('partials.seo-head',['seo'=>$seo])
@endsection
@endif
@section('content')
<section class="section"><div class="container" style="max-width:900px">
@if($preview)<div class="notice warning" style="margin-bottom:20px">Administrator preview — this content is not necessarily published.</div>@endif
<div class="eyebrow">{{ ucfirst($entry->kind) }} @if($entry->published_at) · {{ $entry->published_at->format('d M Y') }}@endif</div>
@if(!($translationAvailable ?? true))<div class="notice" style="margin:12px 0">A reviewed translation is not yet available in this language. Showing the source document.</div>@endif
<h1>{{ $display['title'] ?? $entry->title }}</h1>
@if($display['excerpt'] ?? $entry->excerpt)<p>{{ $display['excerpt'] ?? $entry->excerpt }}</p>@endif
@if($entry->media?->kind === 'image')<img src="{{ route('media.serve',$entry->media) }}" alt="{{ $entry->media->title }}" style="width:100%;max-height:440px;object-fit:cover;border-radius:17px;margin:18px 0">
@elseif($entry->media?->kind === 'video')<video controls preload="metadata" style="width:100%;max-height:440px;border-radius:17px"><source src="{{ route('media.serve',$entry->media) }}" type="{{ $entry->media->mime }}"></video>
@elseif($entry->media?->kind === 'youtube')<div style="aspect-ratio:16/9;margin:20px 0"><iframe title="Featured video" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-same-origin allow-presentation" allowfullscreen style="width:100%;height:100%;border:0" src="https://www.youtube-nocookie.com/embed/{{ $entry->media->youtube_id }}"></iframe></div>
@elseif($entry->media?->kind === 'color')<div style="height:160px;background:{{ $entry->media->color_hex }};border-radius:14px;margin:20px 0"></div>@endif
<article class="panel document-content">{!! $display['body_html'] ?? $entry->body_html !!}</article>
</div></section>
@endsection
