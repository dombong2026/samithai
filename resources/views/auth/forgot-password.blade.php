<!doctype html><html lang="{{ app()->getLocale() }}" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
@include('partials.branding-head')<title>Recover account — Samith AI</title><link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}"><script defer src="{{ asset('assets/app.js') }}"></script></head><body><main class="auth-screen"><section class="auth-card">
@include('partials.brand', ['href' => route('home')])
<h1>Reset your password</h1><p>Enter your account email address to request a secure reset link.</p>
<form method="post" action="{{ route('password.email') }}">@csrf
<div class="floating"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg><input type="email" name="email" id="email" placeholder=" " value="{{ old('email') }}" required autocomplete="email"><label for="email">Email address</label></div>
<button type="submit" class="btn primary">Request reset link</button></form>
<div class="auth-bottom"><a href="{{ route('login') }}">Back to sign in</a></div></section></main>
@include('partials.feedback')
</body></html>