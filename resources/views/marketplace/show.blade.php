@extends('layouts.site')
@section('title',$prompt->title)
@if(isset($seo))
@section('seo_title', $seo['title'])
@section('seo_head')
@include('partials.seo-head',['seo'=>$seo])
@endsection
@endif
@section('content')
@php($pageCopy = \App\Support\FrontendPageCopy::values('prompt'))
<section class="section"><div class="container" style="max-width:1050px">
@if(\App\Support\FrontendPageAvailability::enabled('catalog'))<a class="btn small" href="{{ route('prompts.index') }}">{{ $pageCopy['back_cta'] }}</a>@endif
<div class="panel" style="margin-top:20px">
<div class="flex wrap"><span class="tag">{{ $prompt->category->name }}</span><span class="tag">{{ ucfirst($prompt->license_type) }} license</span></div>
<h1 style="margin-top:17px">{{ $prompt->title }}</h1>
@if($prompt->featuredMedia?->kind === 'image')<img src="{{ route('media.serve',$prompt->featuredMedia) }}" alt="{{ $prompt->title }}" style="max-width:100%;max-height:420px;object-fit:contain;border-radius:14px">
@elseif($prompt->featuredMedia?->kind === 'video')<video controls preload="metadata" style="width:100%;max-height:420px;border-radius:14px"><source src="{{ route('media.serve',$prompt->featuredMedia) }}" type="{{ $prompt->featuredMedia->mime }}"></video>
@elseif($prompt->featuredMedia?->kind === 'youtube')<div style="aspect-ratio:16/9"><iframe style="width:100%;height:100%;border:0" referrerpolicy="strict-origin-when-cross-origin" loading="lazy" allowfullscreen sandbox="allow-scripts allow-same-origin allow-presentation" src="https://www.youtube-nocookie.com/embed/{{ $prompt->featuredMedia->youtube_id }}" title="Video preview"></iframe></div>
@elseif($prompt->featuredMedia?->kind === 'color')<div style="height:160px;border-radius:14px;background:{{ $prompt->featuredMedia->color_hex }}"></div>@endif
<p>{{ $prompt->summary }}</p>
<div class="flex between wrap" style="margin:28px 0"><div><div class="muted tiny">{{ $pageCopy['creator_label'] }}: {{ $prompt->user->name }}</div><div class="price">{{ $prompt->isFree() ? 'Free' : '$'.number_format((float)$prompt->price, 2) }}</div></div>
@if($owned)
<a class="btn primary" href="{{ route('account.library.show', $prompt) }}">Open prompt</a>
@else
<form action="{{ route('cart.add', $prompt) }}" method="post">@csrf<button type="submit" class="btn primary">{{ $pageCopy['cart_cta'] }}</button></form>
@endif</div>
<div class="flex wrap" style="margin-bottom:20px">
@auth
<form method="post" action="{{ $saved ? route('prompts.unsave', $prompt) : route('prompts.save', $prompt) }}">@csrf @if($saved) @method('DELETE') @endif
<button class="btn" type="submit">{{ $saved ? '♥ '.$pageCopy['saved_remove_cta'] : '♡ '.$pageCopy['saved_cta'] }}</button></form>
<a class="btn" href="{{ route('account.saved') }}">{{ $pageCopy['saved_list_cta'] }}</a>
@else
<a class="btn" href="{{ route('login') }}">♡ {{ $pageCopy['saved_cta'] }}</a>
@endauth
</div>
@if($legal=\App\Models\LegalVersion::current($prompt->license_type))<p class="tiny"><a href="{{ route('legal.show',$prompt->license_type) }}" target="_blank" rel="noopener">Read the published {{ ucfirst($prompt->license_type) }} License (v{{ $legal->version }})</a></p>@endif
<div class="notice">The complete prompt text is available only to its creator, administrators, and customers with a valid entitlement. Purchasing a license does not transfer copyright.</div>
@if(!$prompt->isFree())<p class="tiny" style="margin-top:12px">Paid checkout remains disabled until a verified payment provider is configured. No payment will be collected.</p>@endif
</div></div></section>
@endsection
