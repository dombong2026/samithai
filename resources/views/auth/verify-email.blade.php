@extends('layouts.workspace')
@section('title','Verify email')
@section('breadcrumb','Account / Email verification')
@section('heading','Verify your email')
@section('subtitle','Confirm that you own your account email address.')
@section('toolbar')<a class="btn" href="{{ route('account.settings') }}">Account settings</a>@endsection
@section('content')
<div class="panel stack">
@if(auth()->user()->hasVerifiedEmail())
<div class="notice">Your email address has been verified.</div>
@elseif(\App\Support\MailDelivery::permitted())
<p>Verification email can be requested for <strong>{{ auth()->user()->email }}</strong>.</p>
<form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn primary" type="submit">Resend verification email</button></form>
@else
<div class="notice warning">Email delivery has not been enabled on this installation. An administrator must configure and test a trusted SMTP or email provider before verification links can be delivered.</div>
@endif
</div>@endsection