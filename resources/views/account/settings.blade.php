@extends('layouts.workspace')
@section('title','Account Settings')
@section('breadcrumb','Account / Settings')
@section('heading','Account settings')
@section('subtitle','Manage your profile, email verification and password securely.')
@section('toolbar')
<a href="{{ route('account') }}" class="btn"><x-nav-icon name="arrow-up-right"/> Account</a>
<button class="btn" type="button" data-modal-open="account-security-dialog"><x-nav-icon name="security"/> Change password</button>
<button class="btn primary" type="button" data-modal-open="account-profile-dialog"><x-nav-icon name="edit"/> Edit profile</button>
@endsection
@section('content')
<div class="account-settings-premium">
@if(!auth()->user()->hasVerifiedEmail())
<div class="notice warning account-settings-notice">Your email has not been verified.
<a href="{{ route('verification.notice') }}">Review email verification</a></div>
@endif
<section class="account-settings-hero" aria-label="Account identity">
<div class="account-settings-avatar" aria-hidden="true"><x-nav-icon name="account"/></div>
<div class="account-settings-identity">
<div class="eyebrow">ACCOUNT PROFILE</div>
<h2>{{ auth()->user()->name }}</h2>
<p>{{ auth()->user()->email }}</p>
</div>
<span class="account-settings-role">{{ auth()->user()->isAdmin() ? 'Administrator' : (auth()->user()->isCreator() ? 'Creator' : 'Member') }}</span>
</section>
<div class="account-settings-grid">
<section class="account-settings-panel" aria-labelledby="account-profile-heading">
<div class="account-settings-panel-icon"><x-nav-icon name="account"/></div>
<div><div class="eyebrow">PERSONAL INFORMATION</div><h2 id="account-profile-heading">Profile details</h2>
<p>Your display name and email address. Changes to your email require renewed verification.</p></div>
<dl class="account-settings-details">
<div><dt>Display name</dt><dd>{{ auth()->user()->name }}</dd></div>
<div><dt>Email address</dt><dd>{{ auth()->user()->email }}</dd></div>
<div><dt>Verification</dt><dd><span class="account-settings-state {{ auth()->user()->hasVerifiedEmail() ? 'is-good' : 'is-pending' }}">{{ auth()->user()->hasVerifiedEmail() ? 'Verified' : 'Pending' }}</span></dd></div>
</dl>
</section>
<section class="account-settings-panel" aria-labelledby="account-security-heading">
<div class="account-settings-panel-icon"><x-nav-icon name="security"/></div>
<div><div class="eyebrow">ACCOUNT PROTECTION</div><h2 id="account-security-heading">Password & security</h2>
<p>Use a unique password containing at least ten characters, including letters and numbers.</p></div>
<div class="account-settings-security-note"><x-nav-icon name="check"/><span>Changing your password requires the current password and rotates the remember token.</span></div>
</section>
</div>
</div>
<dialog id="account-profile-dialog" class="account-settings-dialog" aria-labelledby="account-profile-dialog-title">
<div class="modal-head"><h2 id="account-profile-dialog-title">Edit profile</h2>
<button class="icon-btn" type="button" data-modal-close aria-label="Close profile editor"><x-nav-icon name="x"/></button></div>
<form method="post" action="{{ route('account.settings.update') }}">@csrf @method('PUT')
<div class="modal-content stack">
<div class="field-group"><label for="account-name">{{ __('account.full_name') }}</label>
<input class="field" id="account-name" name="name" maxlength="100" autocomplete="name" required value="{{ old('name', auth()->user()->name) }}"></div>
<div class="field-group"><label for="account-email">{{ __('account.email_address') }}</label>
<input class="field" id="account-email" name="email" type="email" autocomplete="email" maxlength="190" required value="{{ old('email', auth()->user()->email) }}"></div>
<p class="muted tiny">Changing your email address may require email verification before some account actions.</p>
</div>
<div class="modal-foot"><button class="btn" type="button" data-modal-close>Cancel</button><button class="btn primary" type="submit">Save profile</button></div>
</form>
</dialog>
<dialog id="account-security-dialog" class="account-settings-dialog" aria-labelledby="account-security-dialog-title">
<div class="modal-head"><h2 id="account-security-dialog-title">Change password</h2>
<button class="icon-btn" type="button" data-modal-close aria-label="Close password editor"><x-nav-icon name="x"/></button></div>
<form method="post" action="{{ route('account.password.update') }}">@csrf @method('PUT')
<div class="modal-content stack">
<div class="field-group"><label for="account-current-password">{{ __('account.current_password') }}</label>
<div class="account-settings-password-row"><input class="field" type="password" id="account-current-password" name="current_password" autocomplete="current-password" required>
<button class="icon-btn" type="button" data-password-toggle="account-current-password" aria-label="Show password" title="Show password" aria-pressed="false"><x-nav-icon name="eye"/></button></div></div>
<div class="field-group"><label for="account-new-password">{{ __('account.new_password') }}</label>
<div class="account-settings-password-row"><input class="field" type="password" id="account-new-password" name="password" autocomplete="new-password" minlength="10" required>
<button class="icon-btn" type="button" data-password-toggle="account-new-password" aria-label="Show password" title="Show password" aria-pressed="false"><x-nav-icon name="eye"/></button></div></div>
<div class="field-group"><label for="account-confirm-password">{{ __('account.confirm_password') }}</label>
<div class="account-settings-password-row"><input class="field" type="password" id="account-confirm-password" name="password_confirmation" autocomplete="new-password" minlength="10" required>
<button class="icon-btn" type="button" data-password-toggle="account-confirm-password" aria-label="Show password" title="Show password" aria-pressed="false"><x-nav-icon name="eye"/></button></div></div>
</div>
<div class="modal-foot"><button class="btn" type="button" data-modal-close>Cancel</button><button type="submit" class="btn primary">Update password</button></div></form>
</dialog>
@endsection
