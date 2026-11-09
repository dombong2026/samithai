<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.branding-head')
<title>@yield('title', 'AI prompt marketplace') — {{ \App\Models\SiteSetting::text('site_name','Samith AI') }}</title>
<style>:root{--primary:{{ \App\Models\SiteSetting::text('primary_color','#ED702B') }} !important;--secondary:{{ \App\Models\SiteSetting::text('secondary_color','#246BD0') }} !important}</style>
<link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}">
<script defer src="{{ asset('assets/app.js') }}?v={{ filemtime(public_path('assets/app.js')) }}"></script>
</head>
<body>
@php($shellCopy = \App\Support\FrontendPageCopy::values('shell'))
<div class="top-strip"><div class="container"><span>{{ __('ui.top') }}</span><span>{{ $shellCopy['top_strip'] }}</span></div></div>
<header class="main-header"><div class="container">
@include('partials.brand', ['href' => route('home')])
<form class="search-bar" action="{{ route('prompts.index') }}" method="get" role="search">
<label class="sr-only" for="site-search">Search prompts</label>
<input id="site-search" name="q" placeholder="{{ $shellCopy['search_placeholder'] }}" value="{{ request('q') }}">
<button type="submit" aria-label="Search">⌕</button>
</form>
<div class="main-nav">
<a class="btn ghost" href="{{ route('cart.index') }}">{{ __('ui.cart') }}</a>
<details class="profile-menu"><summary class="icon-btn" aria-label="Account options"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4.5 21c.8-5 4-7 7.5-7s6.7 2 7.5 7"/></svg></summary>
<div class="dropdown">
@auth
@if(auth()->user()->isAdmin())<a href="{{ route('dashboard') }}">Admin dashboard</a>@endif
<a href="{{ route('account') }}">{{ __('ui.account') }}</a><a href="{{ route('account.library') }}">{{ __('ui.library') }}</a>
<a href="{{ route('account.saved') }}">Saved prompts</a>
<a href="{{ route('account.settings') }}">{{ __('ui.settings') }}</a>
@else
<a href="{{ route('login') }}">{{ __('ui.login') }}</a><a href="{{ route('register') }}">{{ __('ui.register') }}</a>
@endauth
<button type="button" data-theme-toggle><span data-theme-name>Dark mode</span></button>
@auth<form method="post" action="{{ route('logout') }}">@csrf<button type="submit">{{ __('ui.logout') }}</button></form>@endauth
</div></details>
</div></div></header>
<nav class="sub-header" aria-label="Marketplace navigation"><div class="container">
<a href="{{ route('home') }}">{{ __('ui.home') }}</a><a href="{{ route('prompts.index') }}">{{ __('ui.prompts') }}</a>
<a href="{{ route('blog.index') }}">{{ __('ui.blog') }}</a>
<a href="{{ route('policies.index') }}">{{ __('ui.policies') }}</a>
<a href="{{ route('support.center') }}">{{ __('ui.support') }}</a>
@foreach(\App\Models\Category::where('is_active', true)->orderBy('name')->limit(8)->get() as $navCategory)
<a href="{{ route('categories.show', $navCategory) }}">{{ $navCategory->name }}</a>
@endforeach
</div></nav>
<main>@yield('content')</main>
<footer class="footer">
<div class="container footer-grid">
<div class="footer-col">@include('partials.brand', ['href' => route('home')])<p>{{ \App\Models\SiteSetting::text('site_description','Discover and license practical AI prompts.') }}</p></div>
@foreach(\App\Models\FooterColumn::where('enabled', true)->with('links')->orderBy('position')->orderBy('id')->limit(5)->get() as $footerColumn)
<div class="footer-col"><h3>{{ $footerColumn->title }}</h3>
@foreach($footerColumn->links as $footerLink)
<a href="{{ str_starts_with($footerLink->url,'/') ? url($footerLink->url) : $footerLink->url }}" @if(!str_starts_with($footerLink->url,'/')) target="_blank" rel="noopener noreferrer" @endif>{{ $footerLink->label }}</a>
@endforeach
</div>
@endforeach
</div>
<div class="footer-bottom"><div class="container"><span>Copyright © {{ date('Y') }} | {{ \App\Models\SiteSetting::text('site_name','Samith AI') }}</span><div class="socials" style="align-items:center">
<details class="profile-menu"><summary class="btn small">{{ __('ui.language') }}: {{ config('samith.locales.'.app()->getLocale(), 'English') }}</summary>
<div class="dropdown" style="top:auto;bottom:calc(100% + 8px);max-height:350px;overflow:auto">
@foreach(config('samith.locales') as $code => $name)
<a lang="{{ $code }}" href="{{ route('language.switch',$code) }}">{{ $name }}</a>
@endforeach
</div></details><a href="https://tonsamith.com" rel="noopener">Ton Samith</a></div></div></div>
</footer>
@include('partials.feedback')
</body></html>
