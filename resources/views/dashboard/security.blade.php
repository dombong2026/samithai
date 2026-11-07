@extends('layouts.workspace')
@section('title','Security Center')
@section('breadcrumb','Administration / Security')
@section('heading','Security Center')
@section('subtitle','Read-only operational audit trail of sensitive changes.')
@section('toolbar')<a class="btn primary" href="{{ route('dashboard.security') }}">Refresh audit log</a>@endsection
@section('content')
<div class="stats" style="margin-bottom:22px"><div class="stat"><p>Admin accounts</p><b>{{ $admins }}</b><span class="muted tiny">Currently assigned</span></div>
<div class="stat"><p>Audit records</p><b>{{ $events->total() }}</b><span class="muted tiny">Matching current filters</span></div></div>
<form method="get" class="filter-bar" action="{{ route('dashboard.security') }}">
<label class="sr-only" for="audit-q">Search audit</label><input name="q" id="audit-q" value="{{ request('q') }}" placeholder="Action or actor">
<label class="sr-only" for="audit-action">Event type</label><select id="audit-action" name="action"><option value="">All actions</option>
@foreach($actions as $name)<option value="{{ $name }}" @selected(request('action')===$name)>{{ $name }}</option>@endforeach</select>
<button class="btn primary" type="submit">Search & filter</button><a class="btn" href="{{ route('dashboard.security') }}">Clear</a></form>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Action</th><th>Actor</th><th>Resource</th><th>Time</th><th>Details</th></tr></thead><tbody>
@forelse($events as $event)
<tr><td>{{ $events->firstItem()+$loop->index }}</td><td class="title-cell">{{ $event->action }}</td><td>{{ $event->actor_name ?? 'System' }}</td>
<td>{{ $event->subject_type }} #{{ $event->subject_id }}</td><td>{{ $event->created_at->format('d M Y H:i') }}</td>
<td class="muted tiny">{{ \Illuminate\Support\Str::limit(json_encode($event->details ?? []),100) }}</td></tr>
@empty<tr><td colspan="6" class="empty">No matching audit events yet.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$events])
<div class="notice" style="margin-top:17px">Audit events are append-only through the application UI. Only approved actions should be logged; passwords, API keys and full payment data must never be recorded.</div>
@endsection
