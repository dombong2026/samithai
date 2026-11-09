@extends('layouts.workspace')
@section('title','Administration')
@section('breadcrumb','Administration / Overview')
@section('heading','Marketplace dashboard')
@section('subtitle','Everything you need to manage your prompts, people and content in one place.')
@section('toolbar')
<a class="btn" href="{{ route('dashboard.categories') }}"><x-nav-icon name="categories"/> Manage categories</a>
<a class="btn primary" href="{{ route('dashboard.prompts') }}"><x-nav-icon name="review"/> Review prompts</a>
@endsection
@section('content')
<div class="admin-dashboard">
<section class="admin-hero-panel" aria-label="Marketplace command center">
<div>
<div class="admin-hero-kicker"><span class="admin-live-dot"></span> MARKETPLACE CONTROL CENTER</div>
<h2>Run your marketplace with clarity.</h2>
<p>Review creator submissions, organize your catalog and keep your community supported.</p>
<div class="admin-hero-meta">
<span><x-nav-icon name="check"/> {{ number_format($publishedCount) }} published prompts</span>
<span><x-nav-icon name="categories"/> {{ number_format($activeCategoryCount) }} active categories</span>
</div>
</div>
<div class="admin-hero-actions">
<a href="{{ route('dashboard.prompts',['status'=>'pending']) }}" class="btn primary">Moderation queue <x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('prompts.index') }}" class="btn admin-hero-secondary">View storefront <x-nav-icon name="arrow-up-right"/></a>
</div>
</section>

<div class="admin-section-heading"><div><div class="eyebrow">AT A GLANCE</div><h2>Marketplace performance</h2></div><span class="admin-heading-note">Live database totals</span></div>
<div class="admin-metrics">
<a class="admin-metric" href="{{ route('dashboard.prompts',['status'=>'pending']) }}">
<div class="admin-metric-heading"><span>Awaiting review</span><span class="admin-metric-icon is-orange"><x-nav-icon name="review"/></span></div>
<strong>{{ number_format($pendingCount) }}</strong><span class="admin-metric-footer">Open moderation queue <x-nav-icon name="arrow-up-right"/></span>
</a>
<a class="admin-metric" href="{{ route('dashboard.prompts') }}">
<div class="admin-metric-heading"><span>All prompts</span><span class="admin-metric-icon is-blue"><x-nav-icon name="posts"/></span></div>
<strong>{{ number_format($promptsCount) }}</strong><span class="admin-metric-footer">{{ number_format($publishedCount) }} published <x-nav-icon name="arrow-up-right"/></span>
</a>
<a class="admin-metric" href="{{ route('dashboard.users') }}">
<div class="admin-metric-heading"><span>Registered accounts</span><span class="admin-metric-icon is-purple"><x-nav-icon name="users"/></span></div>
<strong>{{ number_format($usersCount) }}</strong><span class="admin-metric-footer">{{ number_format($creatorsCount) }} creators and admins <x-nav-icon name="arrow-up-right"/></span>
</a>
<a class="admin-metric" href="{{ route('dashboard.support') }}">
<div class="admin-metric-heading"><span>Open support tickets</span><span class="admin-metric-icon is-green"><x-nav-icon name="support"/></span></div>
<strong>{{ number_format($supportOpenCount) }}</strong><span class="admin-metric-footer">Manage support inbox <x-nav-icon name="arrow-up-right"/></span>
</a>
</div>

@if($categoryCount === 0)
<section class="admin-setup-strip">
<span class="admin-setup-icon"><x-nav-icon name="spark"/></span>
<div><strong>Start with your first category</strong><p>Set up the marketplace taxonomy before inviting creators to publish their prompts.</p></div>
<a href="{{ route('dashboard.categories') }}" class="btn primary">Create category <x-nav-icon name="arrow-up-right"/></a>
</section>
@endif

<div class="admin-dashboard-grid">
<section class="admin-surface" aria-labelledby="admin-recent-heading">
<div class="admin-surface-header"><div><div class="eyebrow">CONTENT PIPELINE</div><h2 id="admin-recent-heading">Recent submissions</h2><p>Latest creator prompts and their moderation status.</p></div><a href="{{ route('dashboard.prompts') }}" class="admin-text-link">All prompts <x-nav-icon name="arrow-up-right"/></a></div>
@if($recentPrompts->isEmpty())
<div class="admin-empty-state">
<span class="admin-empty-icon"><x-nav-icon name="posts"/></span>
<h3>No prompt submissions yet</h3>
<p>Once creators submit prompts, their status and review actions will appear here.</p>
<a class="btn small" href="{{ route('dashboard.categories') }}">Prepare categories</a>
</div>
@else
<div class="admin-submissions">
@foreach($recentPrompts as $prompt)
<div class="admin-submission">
<div class="admin-submission-avatar">{{ strtoupper(mb_substr($prompt->title,0,1)) }}</div>
<div class="admin-submission-main"><strong>{{ $prompt->title }}</strong><span>{{ $prompt->user?->name ?? 'Former creator' }} · {{ $prompt->category?->name ?? 'Uncategorized' }} · {{ $prompt->created_at->format('d M Y') }}</span></div>
<div class="admin-submission-actions"><span class="pill {{ $prompt->status }}">{{ ucfirst($prompt->status) }}</span><a class="admin-inline-action" href="{{ route('dashboard.prompts',['q'=>$prompt->title]) }}" aria-label="Manage {{ $prompt->title }}"><x-nav-icon name="arrow-up-right"/></a></div>
</div>
@endforeach
</div>
@endif
</section>

<section class="admin-surface admin-quick-actions" aria-labelledby="admin-quick-heading">
<div class="admin-surface-header"><div><div class="eyebrow">YOUR NEXT STEPS</div><h2 id="admin-quick-heading">Priority actions</h2><p>Direct access to the tasks that matter.</p></div></div>
<div class="admin-action-stack">
<a href="{{ route('dashboard.prompts',['status'=>'pending']) }}" class="admin-action-item"><span class="admin-action-icon is-orange"><x-nav-icon name="review"/></span><span><strong>Review submissions</strong><small>{{ $pendingCount > 0 ? number_format($pendingCount).' pending approvals' : 'Moderation queue is clear' }}</small></span><x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('dashboard.support') }}" class="admin-action-item"><span class="admin-action-icon is-green"><x-nav-icon name="support"/></span><span><strong>Customer support</strong><small>{{ $supportOpenCount > 0 ? number_format($supportOpenCount).' open support tickets' : 'No open support tickets' }}</small></span><x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('dashboard.content','posts') }}" class="admin-action-item"><span class="admin-action-icon is-blue"><x-nav-icon name="posts"/></span><span><strong>Manage content</strong><small>{{ number_format($contentDraftCount) }} unpublished drafts</small></span><x-nav-icon name="arrow-up-right"/></a>
<a href="{{ route('dashboard.users') }}" class="admin-action-item"><span class="admin-action-icon is-purple"><x-nav-icon name="users"/></span><span><strong>Manage accounts</strong><small>Users and creator roles</small></span><x-nav-icon name="arrow-up-right"/></a>
</div>
</section>
</div>

<div class="admin-dashboard-grid">
<section class="admin-surface" id="marketplace-interest" aria-labelledby="admin-interest-heading">
<div class="admin-surface-header"><div><div class="eyebrow">DISCOVERY</div><h2 id="admin-interest-heading">Marketplace interest</h2><p>Saved prompts from real accounts, not sales or page views.</p></div><span class="admin-count-badge">{{ number_format($savedCount) }} saves</span></div>
@if($mostSavedPrompts->isEmpty())
<div class="admin-empty-state is-compact"><span class="admin-empty-icon"><x-nav-icon name="saved"/></span><h3>No saved prompts yet</h3><p>Popular prompts will appear here when creators publish listings and customers save them.</p></div>
@else
<div class="admin-interest-list">
@foreach($mostSavedPrompts as $prompt)
<div class="admin-interest-item"><span class="admin-interest-rank">{{ $loop->iteration }}</span><span class="admin-interest-details"><strong>{{ $prompt->title }}</strong><small>{{ $prompt->user?->name ?? 'Former creator' }}</small></span><span class="admin-interest-count">{{ number_format($prompt->savers_count) }} saved</span><a href="{{ route('prompts.show',$prompt) }}" class="admin-inline-action" aria-label="View {{ $prompt->title }}"><x-nav-icon name="arrow-up-right"/></a></div>
@endforeach
</div>
@endif
</section>

<section class="admin-surface" aria-labelledby="admin-operation-heading">
<div class="admin-surface-header"><div><div class="eyebrow">OPERATIONS</div><h2 id="admin-operation-heading">Platform snapshot</h2><p>Actual publishing and fulfillment activity.</p></div></div>
<div class="admin-operations-grid">
<a href="{{ route('dashboard.categories') }}" class="admin-operation"><span>Categories</span><strong>{{ number_format($categoryCount) }}</strong><small>{{ number_format($activeCategoryCount) }} active <x-nav-icon name="arrow-up-right"/></small></a>
<a href="{{ route('dashboard.content','posts') }}" class="admin-operation"><span>Published content</span><strong>{{ number_format($publishedContentCount) }}</strong><small>Pages, posts and articles <x-nav-icon name="arrow-up-right"/></small></a>
<a href="{{ route('dashboard.finance') }}" class="admin-operation"><span>Completed free orders</span><strong>{{ number_format($ordersCount) }}</strong><small>Verified free fulfillment <x-nav-icon name="arrow-up-right"/></small></a>
<a href="{{ route('dashboard.system') }}" class="admin-operation"><span>System Center</span><strong><x-nav-icon name="system"/></strong><small>Runtime checks <x-nav-icon name="arrow-up-right"/></small></a>
</div>
<p class="admin-operational-note">Paid checkout is not enabled until the provider verification and compliance requirements are complete.</p>
</section>
</div>
</div>
@endsection
