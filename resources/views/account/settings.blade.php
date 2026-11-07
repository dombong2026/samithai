@extends('layouts.workspace')
@section('title','Settings')
@section('breadcrumb','Account / Settings')
@section('heading','Account settings')
@section('subtitle','Update your account and security details.')
@section('toolbar')<a href="{{ route('account') }}" class="btn">Back to account</a>@endsection
@section('content')
@if(!auth()->user()->hasVerifiedEmail())<div class="notice warning" style="margin-bottom:18px">Email not verified. <a href="{{ route('verification.notice') }}">Review email verification</a></div>@endif
<div class="form-grid">
<div class="panel"><h2>Profile</h2><form class="stack" action="{{ route('account.settings.update') }}" method="post">@csrf @method('PUT')
<div class="field-group"><label for="name">{{ __('account.full_name') }}</label><input class="field" id="name" name="name" maxlength="100" required value="{{ old('name', auth()->user()->name) }}"></div>
<div class="field-group"><label for="email">{{ __('account.email_address') }}</label><input class="field" id="email" name="email" type="email" required value="{{ old('email', auth()->user()->email) }}"></div>
<div><button class="btn primary" type="submit">{{ __('account.save_profile') }}</button></div></form></div>
<div class="panel"><h2>Security</h2><form class="stack" method="post" action="{{ route('account.password.update') }}">@csrf @method('PUT')
<div class="field-group"><label for="current_password">{{ __('account.current_password') }}</label><input class="field" id="current_password" type="password" name="current_password" required autocomplete="current-password"></div>
<div class="field-group"><label for="new-password">{{ __('account.new_password') }}</label><input class="field" id="new-password" type="password" name="password" required minlength="10" autocomplete="new-password"></div>
<div class="field-group"><label for="confirm-password">{{ __('account.confirm_password') }}</label><input class="field" id="confirm-password" type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></div>
<div><button class="btn primary" type="submit">{{ __('account.change_password') }}</button></div>
</form></div>
</div>
@endsection
