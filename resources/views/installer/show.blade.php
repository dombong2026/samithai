<!doctype html><html lang="en" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install Samith AI</title><link rel="stylesheet" href="{{ asset('assets/app.css') }}"><script defer src="{{ asset('assets/app.js') }}"></script></head><body>
<main class="auth-screen" style="display:block"><div class="container" style="max-width:850px;padding:35px 0">
<div class="flex between wrap" style="margin-bottom:24px"><div class="brand"><span class="brand-mark">S</span><span>Samith <strong>AI</strong></span></div><button class="btn" type="button" data-theme-toggle>Light / dark</button></div>
<div class="eyebrow">First-time setup · v0.0.1</div><h1>Install Samith AI</h1><p>Complete the setup once. The installer locks automatically after the administrator account is created.</p>
<div class="panel" style="margin:22px 0"><h2>1. Server requirements</h2>
<div class="form-grid">
@foreach($requirements as $name => $ok)
<div class="flex between" style="padding:7px 0;border-bottom:1px solid var(--line)"><span>{{ $name }}</span><strong style="color:{{ $ok ? '#0d996d' : '#d85552' }}">{{ $ok ? 'Passed' : 'Fix needed' }}</strong></div>
@endforeach</div>
@if(in_array(false, $requirements, true))<div class="notice warning" style="margin-top:14px">Resolve all failed requirements before installation. Ensure the webserver runs PHP 8.3+, MySQL is available, and the required folders are writable.</div>@endif
</div>
<form method="post" action="{{ route('installer.install') }}" class="stack">@csrf
<div class="panel stack"><h2>2. Verify ownership</h2><p class="tiny">Find <code>INSTALL_TOKEN</code> in your server's private <code>.env</code> file (using cPanel File Manager or aaPanel). Never share this token publicly.</p>
<div class="field-group"><label for="installation_token">Installation token</label><input class="field" id="installation_token" name="installation_token" type="password" required autocomplete="off"></div>
<div class="field-group"><label for="app_url">Website URL</label><input class="field" type="url" id="app_url" name="app_url" value="{{ old('app_url', request()->getSchemeAndHttpHost()) }}" required></div>
</div>
<div class="panel stack"><h2>3. MySQL database</h2><p class="tiny">Create an empty MySQL database and user in the hosting control panel first, and grant the user privileges for that database.</p>
<div class="form-grid">
<div class="field-group"><label for="db_host">Database host</label><input class="field" id="db_host" name="db_host" value="{{ old('db_host', '127.0.0.1') }}" required></div>
<div class="field-group"><label for="db_port">Database port</label><input class="field" type="number" id="db_port" name="db_port" value="{{ old('db_port', 3306) }}" required></div>
<div class="field-group"><label for="db_database">Database name</label><input class="field" id="db_database" name="db_database" value="{{ old('db_database') }}" required></div>
<div class="field-group"><label for="db_username">Database username</label><input class="field" id="db_username" name="db_username" value="{{ old('db_username') }}" required></div>
<div class="field-group full"><label for="db_password">Database password</label><input class="field" type="password" id="db_password" name="db_password" autocomplete="new-password"></div>
</div></div>
<div class="panel stack"><h2>4. Administrator account</h2>
<div class="field-group"><label for="admin_name">Administrator name</label><input class="field" id="admin_name" name="admin_name" value="{{ old('admin_name') }}" required></div>
<div class="field-group"><label for="admin_email">Administrator email</label><input class="field" id="admin_email" name="admin_email" type="email" value="{{ old('admin_email') }}" required></div>
<div class="form-grid">
<div class="field-group"><label for="admin_password">Password (12+ characters, letters and numbers)</label><input class="field" type="password" id="admin_password" name="admin_password" minlength="12" required></div>
<div class="field-group"><label for="admin_password_confirmation">Confirm password</label><input class="field" type="password" id="admin_password_confirmation" name="admin_password_confirmation" minlength="12" required></div>
</div></div>
<div class="panel stack"><h2>5. Install and secure</h2><div class="notice">The application will test MySQL, run migrations, create one administrator, write private environment settings and permanently lock the setup page.</div>
<button type="submit" class="btn primary" @disabled(in_array(false, $requirements, true))>Install Samith AI</button></div></form>
</div></main>@include('partials.feedback')
</body></html>
