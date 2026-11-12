@extends('layouts.workspace')
@section('title','Editorial Workflow')
@section('heading','Editorial Workflow')
@section('subtitle','Human review, scheduled publication and tracked revisions for pages and blog articles.')
@section('content')
<div class="panel" style="padding:16px;margin-bottom:16px">
<form method="get" action="{{ route('dashboard.editorial') }}" class="filter-bar">
<label class="sr-only" for="editorial-q">Search</label><input id="editorial-q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Search titles">
<label class="sr-only" for="editorial-kind">Content type</label>
<select id="editorial-kind" name="kind"><option value="">All content</option>
@foreach(['post'=>'Posts','page'=>'Pages'] as $k=>$v)
<option value="{{ $k }}" @selected(request('kind')===$k)>{{ $v }}</option>
@endforeach</select>
<label class="sr-only" for="editorial-status">Review status</label>
<select id="editorial-status" name="status"><option value="">All review states</option>
@foreach(['draft','in_review','changes_requested','approved','scheduled'] as $state)
<option value="{{ $state }}" @selected(request('status')===$state)>{{ ucfirst(str_replace('_',' ',$state)) }}</option>
@endforeach</select>
<button type="submit" class="btn primary">Apply filters</button>
<a class="btn" href="{{ route('dashboard.editorial') }}">Clear</a>
</form></div>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Title</th><th>Type</th><th>Public</th><th>Editorial</th><th>Reviewer</th><th>Schedule</th><th>Action</th></tr></thead><tbody>
@forelse($entries as $entry)
<tr>
<td>{{ $entries->firstItem()+$loop->index }}</td>
<td class="title-cell">{{ $entry->title }}</td>
<td>{{ ucfirst($entry->kind) }}</td>
<td><span class="pill {{ $entry->status }}">{{ ucfirst($entry->status) }}</span></td>
<td>{{ ucfirst(str_replace('_',' ',$entry->editorial_status ?? 'draft')) }}</td>
<td>{{ $entry->reviewer?->name ?? 'Unassigned' }}</td>
<td>{{ $entry->scheduled_for?->format('d M Y H:i') ?? '—' }}</td>
<td><a class="btn small" href="{{ route('dashboard.editorial.show',$entry) }}">Review</a></td>
</tr>
@empty<tr><td colspan="8" class="empty">No content matches these filters.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$entries])
<p class="tiny muted" style="margin-top:18px">Published pages remain published. The remaining educational articles stay drafts until real human approval; the 30 starter prompt quality gate is separate and unchanged.</p>
@endsection
