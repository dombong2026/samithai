@extends('layouts.workspace')
@section('title',auth()->user()->isCreator() ? 'Creator workspace' : 'My account')
@section('breadcrumb',auth()->user()->isCreator() ? 'Creator / Overview' : 'Account / Overview')
@section('heading',auth()->user()->isCreator() ? 'Creator dashboard' : 'Account dashboard')
@section('subtitle',auth()->user()->isCreator() ? 'Manage your prompt catalog and follow real audience activity.' : 'Your library, saved prompts and account activity in one place.')
@section('toolbar')
<a class="btn" href="{{ route('prompts.index') }}"><x-nav-icon name="store"/> Explore prompts</a>
@if(auth()->user()->isCreator())<a class="btn primary" href="{{ route('account.prompts') }}"><x-nav-icon name="posts"/> Manage prompts</a>@endif
@endsection
@section('content')
<div class="account-dashboard-premium">
<div class="account-welcome">
<div class="account-welcome-text">
<div class="eyebrow">{{ auth()->user()->isCreator() ? 'CREATOR STUDIO' : 'MY WORKSPACE' }}</div>
<h2>{{ auth()->user()->isCreator() ? 'Your creator workspace' : 'Welcome to your account' }}</h2>
<p>{{ auth()->user()->isCreator() ? 'Publish and refine your prompts, keep your media organized, and measure genuine marketplace interest.' : 'Keep your favorite prompts, licensed library and purchase history easy to find.' }}</p>
</div>
<div class="account-welcome-actions">
@if(auth()->user()->isCreator())
<a class="btn primary" href="{{ route('account.prompts') }}">Manage my prompts <x-nav-icon name="arrow-up-right"/></a>
<a class="btn" href="{{ route('account.creator.insights') }}">Creator insights</a>
@else
<a class="btn primary" href="{{ route('account.library') }}">Open my library <x-nav-icon name="arrow-up-right"/></a>
<a class="btn" href="{{ route('account.saved') }}">Saved prompts</a>
@endif
</div>
</div>

<div class="account-section-heading"><div><div class="eyebrow">ACCOUNT SNAPSHOT</div><h2>At a glance</h2></div><span class="muted tiny">Your actual account records</span></div>
<div class="account-metrics">
<a class="account-metric" href="{{ route('account.library') }}"><span class="account-metric-icon"><x-nav-icon name="library"/></span><span class="account-metric-label">Licensed library</span><strong>{{ number_format($libraryCount) }}</strong><small>View your prompt library <x-nav-icon name="arrow-up-right"/></small></a>
<a class="account-metric" href="{{ route('account.saved') }}"><span class="account-metric-icon"><x-nav-icon name="saved"/></span><span class="account-metric-label">Saved prompts</span><strong>{{ number_format($savedCount) }}</strong><small>Review saved items <x-nav-icon name="arrow-up-right"/></small></a>
<a class="account-metric" href="{{ route('cart.index') }}"><span class="account-metric-icon"><x-nav-icon name="cart"/></span><span class="account-metric-label">My cart</span><strong>{{ number_format($cartCount) }}</strong><small>Review cart items <x-nav-icon name="arrow-up-right"/></small></a>
<a class="account-metric" href="{{ route('account.orders') }}"><span class="account-metric-icon"><x-nav-icon name="finance"/></span><span class="account-metric-label">Order records</span><strong>{{ number_format($ordersCount) }}</strong><small>View order history <x-nav-icon name="arrow-up-right"/></small></a>
</div>

@if(auth()->user()->isCreator())
<div class="account-content-grid">
<section class="account-link-panel account-creator-panel" aria-labelledby="creator-tools-heading">
<div class="account-panel-header"><div><div class="eyebrow">PUBLISHING WORKSPACE</div><h2 id="creator-tools-heading">Creator Studio</h2><p>Everything you need to manage and improve your prompts.</p></div><span class="account-panel-badge">{{ number_format($promptsCount) }} prompts</span></div>
<div class="account-action-grid">
<a href="{{ route('account.prompts') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="posts"/></span><span><strong>My prompts</strong><small>Draft, submit and edit prompts</small></span><x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('account.media') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="media"/></span><span><strong>My media</strong><small>Organize your prompt images</small></span><x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('account.creator.insights') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="insights"/></span><span><strong>Creator insights</strong><small>Real saves and license claims</small></span><x-nav-icon name="arrow-up-right"/></a>
</div>
</section>
<section class="account-link-panel" aria-labelledby="creator-account-heading">
<div class="account-panel-header"><div><div class="eyebrow">YOUR ACCOUNT</div><h2 id="creator-account-heading">Your essentials</h2><p>Quick access to collection, purchases and help.</p></div></div>
<div class="account-action-grid">
<a href="{{ route('account.saved') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="saved"/></span><span><strong>Saved prompts</strong><small>Your private shortlist</small></span><x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('account.support') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="support"/></span><span><strong>Support tickets</strong><small>Requests and conversations</small></span><x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('account.settings') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="account"/></span><span><strong>Account settings</strong><small>Profile and security</small></span><x-nav-icon name="arrow-up-right"/></a>
</div>
</section>
</div>
@else
<div class="account-content-grid">
<section class="account-link-panel" aria-labelledby="account-collection-heading">
<div class="account-panel-header"><div><div class="eyebrow">YOUR COLLECTION</div><h2 id="account-collection-heading">Library & saved</h2><p>All the prompts you have collected and saved.</p></div></div>
<div class="account-action-grid">
<a href="{{ route('account.library') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="library"/></span><span><strong>My library</strong><small>Licensed prompts and access</small></span><x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('account.saved') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="saved"/></span><span><strong>Saved prompts</strong><small>Your private shortlist</small></span><x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('prompts.index') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="store"/></span><span><strong>Explore marketplace</strong><small>Discover published prompts</small></span><x-nav-icon name="arrow-up-right"/></a>
</div>
</section>
<section class="account-link-panel" aria-labelledby="account-help-heading">
<div class="account-panel-header"><div><div class="eyebrow">SUPPORT & PROFILE</div><h2 id="account-help-heading">Help & settings</h2><p>Manage your profile and get assistance.</p></div></div>
<div class="account-action-grid">
<a href="{{ route('account.orders') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="finance"/></span><span><strong>Order history</strong><small>View your order records</small></span><x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('account.support') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="support"/></span><span><strong>Support tickets</strong><small>Ask questions and track replies</small></span><x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('account.settings') }}" class="account-action-link"><span class="account-action-icon"><x-nav-icon name="account"/></span><span><strong>Account settings</strong><small>Profile and security</small></span><x-nav-icon name="arrow-up-right"/></a>
</div>
</section>
</div>
<div class="account-create-banner">
<div><span class="eyebrow">READY TO CREATE?</span><h2>Share your prompts with the community.</h2><p>Become a creator to manage your own prompt listings and access creator insights.</p></div>
<form method="post" action="{{ route('account.creator') }}">@csrf<button type="submit" class="btn primary">Start creating prompts <x-nav-icon name="arrow-up-right"/></button></form>
</div>
@endif
</div>
@endsection
