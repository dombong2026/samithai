<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Workspace') — Samith AI</title>
<link rel="stylesheet" href="{{ asset('assets/app.css') }}"><script defer src="{{ asset('assets/app.js') }}"></script>
</head><body>
<div class="workspace">
<aside class="workspace-aside">
<a class="brand" href="{{ route('home') }}"><span class="brand-mark">S</span><span>Samith <strong>AI</strong></span></a>
<nav aria-label="Account navigation">
@if(auth()->user()->isAdmin())
<div class="side-title">Administration</div>
<a class="side-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Overview</a>
<a class="side-link {{ request()->routeIs('dashboard.prompts') ? 'active' : '' }}" href="{{ route('dashboard.prompts') }}">Prompt moderation</a>
<a class="side-link {{ request()->routeIs('dashboard.categories') ? 'active' : '' }}" href="{{ route('dashboard.categories') }}">Categories</a>
@endif
<div class="side-title">My account</div>
<a class="side-link {{ request()->routeIs('account') ? 'active' : '' }}" href="{{ route('account') }}">Dashboard</a>
<a class="side-link {{ request()->routeIs('account.library*') ? 'active' : '' }}" href="{{ route('account.library') }}">My library</a>
@if(auth()->user()->isCreator())
<a class="side-link {{ request()->routeIs('account.prompts*') ? 'active' : '' }}" href="{{ route('account.prompts') }}">My prompts</a>
@endif
<a class="side-link {{ request()->routeIs('cart.*') ? 'active' : '' }}" href="{{ route('cart.index') }}">My cart</a>
<a class="side-link {{ request()->routeIs('account.settings*') ? 'active' : '' }}" href="{{ route('account.settings') }}">Settings</a>
<div class="side-title">Marketplace</div>
<a class="side-link" href="{{ route('prompts.index') }}">Explore prompts</a>
</nav></aside>
<div class="workspace-main">
<header class="workspace-top">
<span class="muted">Samith AI / @yield('breadcrumb', 'Workspace')</span>
<div class="flex"><button class="icon-btn" type="button" data-theme-toggle aria-label="Switch light or dark mode">◐</button>
<details class="profile-menu"><summary class="btn" aria-label="Open profile menu">{{ auth()->user()->name }} ▾</summary>
<div class="dropdown"><a href="{{ route('account.settings') }}">Account settings</a><a href="{{ route('home') }}">View marketplace</a><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form></div></details>
</div></header>
<main class="workspace-body">
<div class="page-heading"><div><div class="eyebrow">@yield('breadcrumb', 'Workspace')</div><h1>@yield('heading', 'Dashboard')</h1><p>@yield('subtitle')</p></div>
<div class="toolbar">@yield('toolbar')</div></div>
@yield('content')
</main></div></div>
@include('partials.feedback')
</body></html>
