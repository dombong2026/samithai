@extends('layouts.workspace')
@section('title', 'Creator insights')
@section('breadcrumb', 'Account / Creator')
@section('heading', 'Creator insights')
@section('subtitle', 'Real marketplace activity for your own published prompts.')
@section('toolbar')
<a class="btn" href="{{ route('account.prompts') }}">Manage my prompts</a>
@endsection
@section('content')
<div class="stats">
<div class="stat"><p>Total prompts</p><b>{{ number_format($totalPrompts) }}</b><span class="muted tiny">All statuses</span></div>
<div class="stat"><p>Published prompts</p><b>{{ number_format($counts->get('published', 0)) }}</b><span class="muted tiny">Visible on marketplace</span></div>
<div class="stat"><p>Saved by users</p><b>{{ number_format($savedCount) }}</b><span class="muted tiny">Saves on published prompts</span></div>
<div class="stat"><p>License claims</p><b>{{ number_format($licenseClaims) }}</b><span class="muted tiny">Confirmed entitlements, not revenue</span></div>
</div>
<div class="panel" style="margin-top:22px">
<div class="flex between wrap"><div><h2>Content pipeline</h2><p class="muted tiny">Your drafts and moderation progress</p></div><a class="btn small" href="{{ route('account.prompts') }}">Manage prompts</a></div>
<div class="flex wrap" style="margin-top:12px">
@foreach(['draft', 'pending', 'rejected', 'published'] as $status)
<span class="pill {{ $status }}">{{ ucfirst($status) }}: {{ number_format($counts->get($status, 0)) }}</span>
@endforeach
</div>
</div>
<div class="panel" style="margin-top:22px">
<div class="flex between wrap"><h2>Most saved prompts</h2><span class="muted tiny">Your published listings only</span></div>
@if($popular->isEmpty())
<p class="muted">Publish a prompt to start seeing verified marketplace interest.</p>
@else
<div class="table-scroll"><table>
<thead><tr><th>Prompt</th><th>Saved by users</th><th>License claims</th><th>Action</th></tr></thead>
<tbody>@foreach($popular as $prompt)
<tr><td class="title-cell">{{ $prompt->title }}</td>
<td>{{ number_format($prompt->savers_count) }}</td>
<td>{{ number_format($prompt->entitlements_count) }}</td>
<td><a class="btn small" href="{{ route('prompts.show', $prompt) }}">View</a></td>
</tr>@endforeach</tbody></table></div>
@endif
</div>
<p class="muted tiny" style="margin-top:14px">Save counts show how many accounts bookmarked your published prompts. License claims show actual entitlement records, not payments, views or revenue. Paid checkout remains disabled until verified provider onboarding.</p>
@endsection
