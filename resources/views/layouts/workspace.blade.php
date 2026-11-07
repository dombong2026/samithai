<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Workspace') — {{ \App\Models\SiteSetting::text('site_name','Samith AI') }}</title>
<style>:root{--primary:{{ \App\Models\SiteSetting::text('primary_color','#ED702B') }} !important;--secondary:{{ \App\Models\SiteSetting::text('secondary_color','#246BD0') }} !important}</style>
<link rel="stylesheet" href="{{ asset('assets/app.css') }}"><script defer src="{{ asset('assets/app.js') }}"></script>
</head><body>
<div class="workspace">
<aside class="workspace-aside">
<a class="brand" href="{{ route('home') }}">@if($siteLogo = \App\Models\SiteSetting::text('logo_media_id'))<img class="brand-mark" src="{{ route('media.serve',$siteLogo) }}" alt="" style="object-fit:cover">@else<span class="brand-mark">S</span>@endif<span>{{ \App\Models\SiteSetting::text('site_name','Samith AI') }}</span></a>
<nav aria-label="Account navigation">
@if(auth()->user()->isAdmin())
<div class="side-title">Administration</div>
<a class="side-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Overview</a>
<a class="side-link {{ request()->routeIs('dashboard.prompts') ? 'active' : '' }}" href="{{ route('dashboard.prompts') }}">Prompt moderation</a>
<a class="side-link {{ request()->routeIs('dashboard.categories') ? 'active' : '' }}" href="{{ route('dashboard.categories') }}">Categories</a>
<a class="side-link {{ request()->routeIs('dashboard.users') ? 'active' : '' }}" href="{{ route('dashboard.users') }}">Users & Roles</a>
<div class="side-title">Content & appearance</div>
<a class="side-link {{ request()->routeIs('dashboard.appearance') ? 'active' : '' }}" href="{{ route('dashboard.appearance') }}">Appearance</a>
<a class="side-link {{ request()->is('dashboard/media') ? 'active' : '' }}" href="{{ route('dashboard.media') }}">Media Library</a>
<a class="side-link {{ request()->is('dashboard/posts') ? 'active' : '' }}" href="{{ route('dashboard.content','posts') }}">Post Manager</a>
<a class="side-link {{ request()->is('dashboard/pages') ? 'active' : '' }}" href="{{ route('dashboard.content','pages') }}">Page Manager</a>
<a class="side-link {{ request()->routeIs('dashboard.legal') ? 'active' : '' }}" href="{{ route('dashboard.legal') }}">License & Legal Review</a>
<a class="side-link {{ request()->is('dashboard/policies') ? 'active' : '' }}" href="{{ route('dashboard.content','policies') }}">Policy Center</a>
<a class="side-link {{ request()->is('dashboard/knowledge') ? 'active' : '' }}" href="{{ route('dashboard.content','knowledge') }}">Knowledge Base</a>
<a class="side-link {{ request()->routeIs('dashboard.footer') ? 'active' : '' }}" href="{{ route('dashboard.footer') }}">Global Footer</a>
<div class="side-title">Operations</div>
<a class="side-link {{ request()->routeIs('dashboard.finance') ? 'active' : '' }}" href="{{ route('dashboard.finance') }}">Finance Manager</a>
<a class="side-link {{ request()->routeIs('dashboard.support') ? 'active' : '' }}" href="{{ route('dashboard.support') }}">Support Center</a>
<a class="side-link {{ request()->routeIs('dashboard.languages') ? 'active' : '' }}" href="{{ route('dashboard.languages') }}">Language Manager</a>
<a class="side-link {{ request()->routeIs('dashboard.security') ? 'active' : '' }}" href="{{ route('dashboard.security') }}">Security Center</a>
<a class="side-link {{ request()->routeIs('dashboard.system') ? 'active' : '' }}" href="{{ route('dashboard.system') }}">System Center</a>
@endif
<div class="side-title">My account</div>
<a class="side-link {{ request()->routeIs('account') ? 'active' : '' }}" href="{{ route('account') }}">Dashboard</a>
<a class="side-link {{ request()->routeIs('account.orders') ? 'active' : '' }}" href="{{ route('account.orders') }}">My orders</a>
<a class="side-link {{ request()->routeIs('account.support') ? 'active' : '' }}" href="{{ route('account.support') }}">Support tickets</a>
<a class="side-link {{ request()->routeIs('account.library*') ? 'active' : '' }}" href="{{ route('account.library') }}">My library</a>
@if(auth()->user()->isCreator())
<a class="side-link {{ request()->routeIs('account.media') ? 'active' : '' }}" href="{{ route('account.media') }}">My media</a>
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
