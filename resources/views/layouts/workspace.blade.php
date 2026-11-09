<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.branding-head')
<title>@yield('title', 'Workspace') — {{ \App\Models\SiteSetting::text('site_name','Samith AI') }}</title>
<style>:root{--primary:{{ \App\Models\SiteSetting::text('primary_color','#ED702B') }} !important;--secondary:{{ \App\Models\SiteSetting::text('secondary_color','#246BD0') }} !important}</style>
<link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}">
<script defer src="{{ asset('assets/app.js') }}?v={{ filemtime(public_path('assets/app.js')) }}"></script>
</head>
<body>
@php
  $adminWorkspace = auth()->user()->isAdmin();
  $creatorWorkspace = auth()->user()->isCreator();
  $marketplaceGroup = request()->routeIs('dashboard.prompts', 'dashboard.categories', 'dashboard.users');
  $contentGroup = request()->routeIs('dashboard.media', 'dashboard.translations*') || request()->is('dashboard/posts','dashboard/pages','dashboard/policies','dashboard/knowledge');
  $commerceGroup = request()->routeIs('dashboard.finance', 'dashboard.legal', 'dashboard.support');
  $appearanceGroup = request()->routeIs('dashboard.appearance', 'dashboard.footer', 'dashboard.languages');
  $systemGroup = request()->routeIs('dashboard.security', 'dashboard.system');
@endphp
<div class="workspace {{ $adminWorkspace ? 'workspace-admin' : 'workspace-account' }}">
<aside class="workspace-aside" id="workspace-sidebar">
<div class="workspace-brand">
  @include('partials.brand', ['href' => route('home')])
  <span class="workspace-brand-caption">{{ $adminWorkspace ? 'ADMIN CONSOLE' : 'MY WORKSPACE' }}</span>
</div>
<details class="workspace-nav-shell" open data-workspace-mobile-nav>
<summary class="workspace-nav-trigger"><x-nav-icon name="menu"/> <span>Navigation menu</span><x-nav-icon name="chevron"/></summary>
<nav class="workspace-nav" aria-label="{{ $adminWorkspace ? 'Administration navigation' : 'Account navigation' }}">
@if($adminWorkspace)
<div class="workspace-nav-label">WORKSPACE</div>
<a href="{{ route('dashboard') }}" class="side-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-nav-icon name="dashboard"/><span>Overview</span></a>
<details class="workspace-nav-group" data-nav-group="marketplace" @if($marketplaceGroup) open @endif>
<summary><x-nav-icon name="store"/><span>Marketplace</span><x-nav-icon name="chevron"/></summary>
<div class="workspace-nav-items">
<a href="{{ route('dashboard.prompts') }}" class="side-link {{ request()->routeIs('dashboard.prompts') ? 'active' : '' }}" @if(request()->routeIs('dashboard.prompts')) aria-current="page" @endif><x-nav-icon name="review"/><span>Prompt moderation</span></a>
<a href="{{ route('dashboard.categories') }}" class="side-link {{ request()->routeIs('dashboard.categories') ? 'active' : '' }}" @if(request()->routeIs('dashboard.categories')) aria-current="page" @endif><x-nav-icon name="categories"/><span>Categories</span></a>
<a href="{{ route('dashboard.users') }}" class="side-link {{ request()->routeIs('dashboard.users') ? 'active' : '' }}" @if(request()->routeIs('dashboard.users')) aria-current="page" @endif><x-nav-icon name="users"/><span>Users & Roles</span></a>
</div></details>
<details class="workspace-nav-group" data-nav-group="content" @if($contentGroup) open @endif>
<summary><x-nav-icon name="posts"/><span>Content & Media</span><x-nav-icon name="chevron"/></summary>
<div class="workspace-nav-items">
<a href="{{ route('dashboard.content','posts') }}" class="side-link {{ request()->is('dashboard/posts') ? 'active' : '' }}" @if(request()->is('dashboard/posts')) aria-current="page" @endif><x-nav-icon name="posts"/><span>Post Manager</span></a>
<a href="{{ route('dashboard.content','pages') }}" class="side-link {{ request()->is('dashboard/pages') ? 'active' : '' }}" @if(request()->is('dashboard/pages')) aria-current="page" @endif><x-nav-icon name="pages"/><span>Page Manager</span></a>
<a href="{{ route('dashboard.content','policies') }}" class="side-link {{ request()->is('dashboard/policies') ? 'active' : '' }}" @if(request()->is('dashboard/policies')) aria-current="page" @endif><x-nav-icon name="policies"/><span>Policy Center</span></a>
<a href="{{ route('dashboard.content','knowledge') }}" class="side-link {{ request()->is('dashboard/knowledge') ? 'active' : '' }}" @if(request()->is('dashboard/knowledge')) aria-current="page" @endif><x-nav-icon name="knowledge"/><span>Knowledge Base</span></a>
<a href="{{ route('dashboard.media') }}" class="side-link {{ request()->routeIs('dashboard.media') ? 'active' : '' }}" @if(request()->routeIs('dashboard.media')) aria-current="page" @endif><x-nav-icon name="media"/><span>Media Library</span></a>
</div></details>
<details class="workspace-nav-group" data-nav-group="commerce" @if($commerceGroup) open @endif>
<summary><x-nav-icon name="finance"/><span>Commerce & Support</span><x-nav-icon name="chevron"/></summary>
<div class="workspace-nav-items">
<a href="{{ route('dashboard.finance') }}" class="side-link {{ request()->routeIs('dashboard.finance') ? 'active' : '' }}" @if(request()->routeIs('dashboard.finance')) aria-current="page" @endif><x-nav-icon name="finance"/><span>Finance Manager</span></a>
<a href="{{ route('dashboard.legal') }}" class="side-link {{ request()->routeIs('dashboard.legal') ? 'active' : '' }}" @if(request()->routeIs('dashboard.legal')) aria-current="page" @endif><x-nav-icon name="legal"/><span>License & Legal Review</span></a>
<a href="{{ route('dashboard.support') }}" class="side-link {{ request()->routeIs('dashboard.support') ? 'active' : '' }}" @if(request()->routeIs('dashboard.support')) aria-current="page" @endif><x-nav-icon name="support"/><span>Support Center</span></a>
</div></details>
<details class="workspace-nav-group" data-nav-group="appearance" @if($appearanceGroup) open @endif>
<summary><x-nav-icon name="appearance"/><span>Appearance & Localization</span><x-nav-icon name="chevron"/></summary>
<div class="workspace-nav-items">
<a href="{{ route('dashboard.appearance') }}" class="side-link {{ request()->routeIs('dashboard.appearance') ? 'active' : '' }}" @if(request()->routeIs('dashboard.appearance')) aria-current="page" @endif><x-nav-icon name="appearance"/><span>Appearance</span></a>
<a href="{{ route('dashboard.footer') }}" class="side-link {{ request()->routeIs('dashboard.footer') ? 'active' : '' }}" @if(request()->routeIs('dashboard.footer')) aria-current="page" @endif><x-nav-icon name="footer"/><span>Global Footer</span></a>
<a href="{{ route('dashboard.languages') }}" class="side-link {{ request()->routeIs('dashboard.languages') ? 'active' : '' }}" @if(request()->routeIs('dashboard.languages')) aria-current="page" @endif><x-nav-icon name="language"/><span>Language Manager</span></a>
</div></details>
<details class="workspace-nav-group" data-nav-group="system" @if($systemGroup) open @endif>
<summary><x-nav-icon name="security"/><span>Platform & Security</span><x-nav-icon name="chevron"/></summary>
<div class="workspace-nav-items">
<a href="{{ route('dashboard.security') }}" class="side-link {{ request()->routeIs('dashboard.security') ? 'active' : '' }}" @if(request()->routeIs('dashboard.security')) aria-current="page" @endif><x-nav-icon name="security"/><span>Security Center</span></a>
<a href="{{ route('dashboard.system') }}" class="side-link {{ request()->routeIs('dashboard.system') ? 'active' : '' }}" @if(request()->routeIs('dashboard.system')) aria-current="page" @endif><x-nav-icon name="system"/><span>System Center</span></a>
</div></details>
<div class="workspace-nav-divider"></div>
<a href="{{ route('account') }}" class="side-link {{ request()->routeIs('account') ? 'active' : '' }}" @if(request()->routeIs('account')) aria-current="page" @endif><x-nav-icon name="account"/><span>My account</span></a>
<a href="{{ route('prompts.index') }}" class="side-link"><x-nav-icon name="store"/><span>Explore marketplace</span><x-nav-icon name="arrow-up-right"/></a>
@else
<div class="workspace-nav-label">MY WORKSPACE</div>
<a href="{{ route('account') }}" class="side-link {{ request()->routeIs('account') ? 'active' : '' }}" @if(request()->routeIs('account')) aria-current="page" @endif><x-nav-icon name="dashboard"/><span>Overview</span></a>
<a href="{{ route('account.library') }}" class="side-link {{ request()->routeIs('account.library*') ? 'active' : '' }}" @if(request()->routeIs('account.library*')) aria-current="page" @endif><x-nav-icon name="library"/><span>My library</span></a>
<a href="{{ route('account.saved') }}" class="side-link {{ request()->routeIs('account.saved') ? 'active' : '' }}" @if(request()->routeIs('account.saved')) aria-current="page" @endif><x-nav-icon name="saved"/><span>Saved prompts</span></a>
<a href="{{ route('account.orders') }}" class="side-link {{ request()->routeIs('account.orders') ? 'active' : '' }}" @if(request()->routeIs('account.orders')) aria-current="page" @endif><x-nav-icon name="finance"/><span>My orders</span></a>
<a href="{{ route('account.support') }}" class="side-link {{ request()->routeIs('account.support') ? 'active' : '' }}" @if(request()->routeIs('account.support')) aria-current="page" @endif><x-nav-icon name="support"/><span>Support tickets</span></a>
@if($creatorWorkspace)
<div class="workspace-nav-label">CREATOR STUDIO</div>
<a href="{{ route('account.prompts') }}" class="side-link {{ request()->routeIs('account.prompts*') ? 'active' : '' }}" @if(request()->routeIs('account.prompts*')) aria-current="page" @endif><x-nav-icon name="posts"/><span>My prompts</span></a>
<a href="{{ route('account.creator.insights') }}" class="side-link {{ request()->routeIs('account.creator.insights') ? 'active' : '' }}" @if(request()->routeIs('account.creator.insights')) aria-current="page" @endif><x-nav-icon name="insights"/><span>Creator insights</span></a>
<a href="{{ route('account.media') }}" class="side-link {{ request()->routeIs('account.media') ? 'active' : '' }}" @if(request()->routeIs('account.media')) aria-current="page" @endif><x-nav-icon name="media"/><span>My media</span></a>
@endif
<div class="workspace-nav-label">ACCOUNT & SHOPPING</div>
<a href="{{ route('cart.index') }}" class="side-link {{ request()->routeIs('cart.*') ? 'active' : '' }}" @if(request()->routeIs('cart.*')) aria-current="page" @endif><x-nav-icon name="cart"/><span>My cart</span></a>
<a href="{{ route('account.settings') }}" class="side-link {{ request()->routeIs('account.settings*') ? 'active' : '' }}" @if(request()->routeIs('account.settings*')) aria-current="page" @endif><x-nav-icon name="account"/><span>Account settings</span></a>
<a href="{{ route('prompts.index') }}" class="side-link"><x-nav-icon name="store"/><span>Explore marketplace</span><x-nav-icon name="arrow-up-right"/></a>
@endif
</nav></details>
<div class="workspace-aside-footer">
<div class="workspace-avatar" aria-hidden="true">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
<div class="workspace-aside-identity"><strong>{{ auth()->user()->name }}</strong><span>{{ $adminWorkspace ? 'Administrator' : ($creatorWorkspace ? 'Creator' : 'Member') }}</span></div>
</div>
</aside>
<div class="workspace-main">
<header class="workspace-top">
<div class="workspace-breadcrumb"><span class="workspace-breadcrumb-mark"><x-nav-icon name="{{ $adminWorkspace ? 'dashboard' : 'account' }}"/></span><span>Samith AI</span><span class="workspace-breadcrumb-divider">/</span><strong>@yield('breadcrumb', 'Workspace')</strong></div>
<div class="workspace-top-actions">
<a class="btn small workspace-top-site" href="{{ route('prompts.index') }}"><x-nav-icon name="store"/> Marketplace</a>
<details class="profile-menu"><summary class="btn small" aria-label="Language">{{ strtoupper(app()->getLocale()) }} <x-nav-icon name="chevron"/></summary>
<div class="dropdown workspace-language-list">
@foreach(config('samith.locales') as $code=>$name)<a href="{{ route('language.switch',$code) }}" lang="{{ $code }}">{{ $name }}</a>@endforeach
</div></details>
<button class="icon-btn" type="button" data-theme-toggle aria-label="Switch light or dark mode">◐</button>
<details class="profile-menu"><summary class="btn workspace-user-menu" aria-label="Open profile menu">{{ auth()->user()->name }} <x-nav-icon name="chevron"/></summary>
<div class="dropdown workspace-profile-dropdown">
<a href="{{ route('account') }}">My account</a>
<a href="{{ route('account.library') }}">My library</a>
<a href="{{ route('account.saved') }}">Saved prompts</a>
@if($creatorWorkspace)
<a href="{{ route('account.prompts') }}">My prompts</a>
<a href="{{ route('account.creator.insights') }}">Creator insights</a>
@endif
<a href="{{ route('account.orders') }}">My orders</a>
<a href="{{ route('account.support') }}">Support tickets</a>
<a href="{{ route('account.settings') }}">Account settings</a>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
</div></details>
</div></header>
<main class="workspace-body">
<div class="page-heading"><div><div class="eyebrow">@yield('breadcrumb', 'Workspace')</div><h1>@yield('heading', 'Dashboard')</h1><p>@yield('subtitle')</p></div>
<div class="toolbar">@yield('toolbar')</div></div>
@yield('content')
</main>
</div>
</div>
@include('partials.feedback')
</body>
</html>
