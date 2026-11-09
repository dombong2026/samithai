<!doctype html><html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
@include('partials.branding-head')<title>Create account — Samith AI</title><link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}"><script defer src="{{ asset('assets/app.js') }}?v={{ filemtime(public_path('assets/app.js')) }}"></script></head><body>
@php($authCopy = \App\Support\FrontendPageCopy::values('register'))
<main class="auth-screen"><section class="auth-card">
@include('partials.brand', ['href' => route('home')])
<h1>{{ $authCopy['title'] }}</h1><p>{{ $authCopy['subtitle'] }}</p>
<form method="post" action="{{ route('register.submit') }}">@csrf
<div class="floating"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-5 3-8 8-8s8 3 8 8"/></svg>
<input id="name" type="text" name="name" placeholder=" " required autocomplete="name" value="{{ old('name') }}"><label for="name">{{ __('account.full_name') }}</label></div>
<div class="floating"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
<input id="email" type="email" name="email" placeholder=" " required autocomplete="email" value="{{ old('email') }}"><label for="email">{{ __('account.email_address') }}</label></div>
<div class="floating"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
<input class="password-entry" id="password" type="password" name="password" placeholder=" " required autocomplete="new-password" minlength="10"><label for="password">{{ __('account.password') }}</label>@include('partials.auth-password-toggle', ['target' => 'password'])</div>
<div class="floating"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
<input class="password-entry" id="password_confirmation" type="password" name="password_confirmation" placeholder=" " required autocomplete="new-password" minlength="10"><label for="password_confirmation">{{ __('account.confirm_password') }}</label>@include('partials.auth-password-toggle', ['target' => 'password_confirmation'])</div>
<p class="tiny">Use at least 10 characters with letters and numbers.</p>
<button type="submit" class="btn primary">{{ $authCopy['submit'] }}</button></form>
<div class="auth-bottom">{{ $authCopy['bottom'] }} <a href="{{ route('login') }}">{{ $authCopy['bottom_cta'] }}</a></div>
</section></main>@include('partials.feedback')
</body></html>
