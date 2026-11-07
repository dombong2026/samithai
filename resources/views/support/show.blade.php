@extends('layouts.workspace')
@section('title','Support ticket #'.$ticket->id)
@section('breadcrumb','Support / Ticket #'.$ticket->id)
@section('heading',$ticket->subject)
@section('subtitle','Opened by '.$ticket->user->name.' · '.$ticket->created_at->format('d M Y'))
@section('toolbar')
<a class="btn" href="{{ auth()->user()->isAdmin() ? route('dashboard.support') : route('account.support') }}">← Tickets</a>
@if($ticket->status === 'open')<form method="post" action="{{ route('support.status',$ticket) }}" data-confirm="Close this support ticket?">@csrf<input type="hidden" name="status" value="closed"><button class="btn" type="submit">Close ticket</button></form>
@elseif(auth()->user()->isAdmin())<form method="post" action="{{ route('support.status',$ticket) }}">@csrf<input type="hidden" name="status" value="open"><button class="btn primary" type="submit">Reopen ticket</button></form>@endif
@endsection
@section('content')
<div class="panel stack"><div class="flex between"><h2>Conversation</h2><span class="pill {{ $ticket->status === 'open' ? 'active' : 'inactive' }}">{{ ucfirst($ticket->status) }}</span></div>
@foreach($ticket->messages as $message)
<div class="panel" style="box-shadow:none">
<div class="flex between"><strong>{{ $message->user->name }} @if($message->user->isAdmin())<span class="tag">Staff</span>@endif</strong><span class="tiny muted">{{ $message->created_at->format('d M Y H:i') }}</span></div>
<p style="white-space:pre-wrap;color:var(--ink);margin:12px 0 0">{{ $message->body }}</p>
</div>
@endforeach
@if($ticket->status === 'open')
<form class="stack" method="post" action="{{ route('support.reply',$ticket) }}">@csrf
<div class="field-group"><label for="ticket-reply">Reply</label><textarea class="field" name="body" id="ticket-reply" required minlength="2" maxlength="10000" rows="4"></textarea></div>
<div><button class="btn primary" type="submit">Send reply</button></div></form>
@else<div class="notice">This ticket is closed. Only an administrator can reopen it.</div>@endif
</div>
@endsection
