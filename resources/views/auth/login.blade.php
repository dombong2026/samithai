<!doctype html><html lang="en" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in — Samith AI</title><link rel="stylesheet" href="{{ asset('assets/app.css') }}"><script defer src="{{ asset('assets/app.js') }}"></script></head><body>
<main class="auth-screen"><section class="auth-card">
<a href="{{ route('home') }}" class="brand"><span class="brand-mark">S</span><span>Samith <strong>AI</strong></span></a>
<h1>Welcome back</h1><p>Sign in to manage your prompts and purchases.</p>
<form method="post" action="{{ route('login.submit') }}">@csrf
<div class="floating"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
<input id="email" type="email" name="email" placeholder=" " required autocomplete="username" value="{{ old('email') }}"><label for="email">Email address</label></div>
<div class="floating"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
<input id="password" type="password" name="password" placeholder=" " required autocomplete="current-password"><label for="password">Password</label>
<button type="button" class="eye" aria-label="Show password" data-password-toggle="password">◉</button></div>
<div class="flex"><label class="flex"><input type="checkbox" name="remember" value="1">Remember me</label></div>
<button type="submit" class="btn primary">Sign in</button>
</form><div class="auth-bottom">New to Samith AI? <a href="{{ route('register') }}">Create account</a></div>
</section></main>@include('partials.feedback')
</body></html>
