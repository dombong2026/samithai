<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'AI prompt marketplace') — {{ config('app.name', 'Samith AI') }}</title>
<link rel="stylesheet" href="{{ asset('assets/app.css') }}">
<script defer src="{{ asset('assets/app.js') }}"></script>
</head>
<body>
<div class="top-strip"><div class="container"><span>Discover and license AI prompts</span><span>Samith AI Marketplace</span></div></div>
<header class="main-header"><div class="container">
<a class="brand" href="{{ route('home') }}"><span class="brand-mark">S</span><span>Samith <strong>AI</strong></span></a>
<form class="search-bar" action="{{ route('prompts.index') }}" method="get" role="search">
<label class="sr-only" for="site-search">Search prompts</label>
<input id="site-search" name="q" placeholder="Search AI prompts..." value="{{ request('q') }}">
<button type="submit" aria-label="Search">⌕</button>
</form>
<div class="main-nav">
<a class="btn ghost" href="{{ route('cart.index') }}">Cart</a>
<details class="profile-menu"><summary class="icon-btn" aria-label="Account options"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4.5 21c.8-5 4-7 7.5-7s6.7 2 7.5 7"/></svg></summary>
<div class="dropdown">
@auth
@if(auth()->user()->isAdmin())<a href="{{ route('dashboard') }}">Admin dashboard</a>@endif
<a href="{{ route('account') }}">My account</a><a href="{{ route('account.library') }}">My library</a>
<a href="{{ route('account.settings') }}">Settings</a>
@else
<a href="{{ route('login') }}">Sign in</a><a href="{{ route('register') }}">Sign up</a>
@endauth
<button type="button" data-theme-toggle><span data-theme-name>Dark mode</span></button>
@auth<form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>@endauth
</div></details>
</div></div></header>
<nav class="sub-header" aria-label="Marketplace navigation"><div class="container">
<a href="{{ route('home') }}">Home</a><a href="{{ route('prompts.index') }}">All prompts</a>
@foreach(\App\Models\Category::where('is_active', true)->orderBy('name')->limit(8)->get() as $navCategory)
<a href="{{ route('categories.show', $navCategory) }}">{{ $navCategory->name }}</a>
@endforeach
</div></nav>
<main>@yield('content')</main>
<footer class="footer">
<div class="container footer-grid">
<div class="footer-col"><a class="brand" href="{{ route('home') }}"><span class="brand-mark">S</span><span>Samith <strong>AI</strong></span></a><p>Discover useful prompts for work, creativity and learning.</p></div>
<div class="footer-col"><h3>Marketplace</h3><a href="{{ route('prompts.index') }}">Browse prompts</a><a href="{{ route('cart.index') }}">Cart</a></div>
<div class="footer-col"><h3>Account</h3><a href="{{ route('account') }}">Dashboard</a><a href="{{ route('account.library') }}">Library</a></div>
<div class="footer-col"><h3>Creators</h3><a href="{{ route('account.prompts') }}">My prompts</a><a href="{{ route('account.settings') }}">Settings</a></div>
<div class="footer-col"><h3>Company</h3><a href="https://tonsamith.com" rel="noopener">About the author</a></div>
</div>
<div class="footer-bottom"><div class="container"><span>Copyright © {{ date('Y') }} | Samith AI</span><div class="socials"><a href="https://tonsamith.com" rel="noopener">Ton Samith</a></div></div></div>
</footer>
@include('partials.feedback')
</body></html>
