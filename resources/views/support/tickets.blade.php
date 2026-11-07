@extends('layouts.workspace')
@section('title','Support tickets')
@section('breadcrumb',$admin ? 'Administration / Support' : 'Account / Support')
@section('heading',$admin ? 'Support Center' : 'My support tickets')
@section('subtitle','Read and respond to actual support requests.')
@section('toolbar')
<a class="btn" href="{{ route('support.center') }}">Knowledge base</a>
<button class="btn primary" type="button" data-modal-open="ticket-modal">+ Open ticket</button>
@endsection
@section('content')
<form class="filter-bar" method="get" action="{{ $admin ? route('dashboard.support') : route('account.support') }}">
<label for="ticket-q" class="sr-only">Search</label><input id="ticket-q" name="q" value="{{ request('q') }}" placeholder="Search tickets">
<label for="ticket-status" class="sr-only">Status</label><select id="ticket-status" name="status"><option value="">All statuses</option><option value="open" @selected(request('status')==='open')>Open</option><option value="closed" @selected(request('status')==='closed')>Closed</option></select>
<button class="btn primary" type="submit">Search & filter</button><a class="btn" href="{{ $admin ? route('dashboard.support') : route('account.support') }}">Clear</a></form>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Ticket</th><th>Customer</th><th>Messages</th><th>Status</th><th>Last update</th><th>Action</th></tr></thead><tbody>
@forelse($tickets as $ticket)
<tr><td>{{ $tickets->firstItem() + $loop->index }}</td>
<td class="title-cell">#{{ $ticket->id }} · {{ $ticket->subject }}</td><td>{{ $ticket->user->name }}</td>
<td>{{ $ticket->messages_count }}</td><td><span class="pill {{ $ticket->status === 'open' ? 'active' : 'inactive' }}">{{ ucfirst($ticket->status) }}</span></td><td>{{ $ticket->updated_at->format('d M Y') }}</td>
<td><button class="icon-btn" type="button" aria-label="Actions for ticket {{ $ticket->id }}" data-action-toggle="support-{{ $ticket->id }}">⋮</button>
<template id="support-{{ $ticket->id }}"><a href="{{ route('support.show',$ticket) }}">View & reply</a>
@if($ticket->status==='open')<form method="post" action="{{ route('support.status',$ticket) }}" data-confirm="Close this support ticket?">@csrf<input type="hidden" name="status" value="closed"><button type="submit">Close ticket</button></form>
@elseif($admin)<form method="post" action="{{ route('support.status',$ticket) }}">@csrf<input type="hidden" name="status" value="open"><button type="submit">Reopen ticket</button></form>@endif
</template></td></tr>
@empty<tr><td colspan="7" class="empty">No matching support tickets.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$tickets])
<dialog id="ticket-modal"><div class="modal-head"><h2>Open support ticket</h2><button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button></div>
<form method="post" action="{{ route('support.store') }}">@csrf<div class="modal-content stack">
<div class="field-group"><label for="ticket-subject">Subject</label><input class="field" name="subject" id="ticket-subject" required maxlength="160"></div>
<div class="field-group"><label for="ticket-body">What do you need help with?</label><textarea class="field" id="ticket-body" name="body" rows="6" minlength="10" maxlength="10000" required></textarea></div>
</div><div class="modal-foot"><button class="btn" type="button" data-modal-close>Cancel</button><button class="btn primary" type="submit">Open ticket</button></div></form></dialog>
@endsection
