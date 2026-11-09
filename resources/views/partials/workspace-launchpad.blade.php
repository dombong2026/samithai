@php
$launchpad = $workspaceLaunchpad;
$catalog = $launchpad['catalog'];
$selectedKeys = $launchpad['keys'];
$context = $launchpad['context'];
@endphp
<section class="workspace-launchpad" aria-labelledby="workspace-launchpad-title-{{ $context }}" data-workspace-launchpad="{{ $context }}">
<div class="workspace-launchpad-heading">
<div><div class="eyebrow">YOUR WORKSPACE</div><h2 id="workspace-launchpad-title-{{ $context }}">Quick launch</h2><p class="muted">Your favorite tools, synced to your account.</p></div>
<button type="button" class="btn small" data-launchpad-configure="{{ $context }}"><x-nav-icon name="system"/> Customize</button>
</div>
<div class="workspace-launchpad-grid">
@foreach($selectedKeys as $key)
<a href="{{ $catalog[$key]['url'] }}" class="workspace-launchpad-link" data-launch-key="{{ $key }}">
<span class="workspace-launchpad-icon"><x-nav-icon name="{{ $catalog[$key]['icon'] }}"/></span>
<span class="workspace-launchpad-copy"><strong>{{ $catalog[$key]['label'] }}</strong><small>{{ $catalog[$key]['description'] }}</small></span>
<x-nav-icon name="arrow-up-right"/>
</a>
@endforeach
</div>
<dialog class="workspace-launchpad-dialog" id="workspace-launchpad-config-{{ $context }}" aria-labelledby="workspace-launchpad-config-title-{{ $context }}">
<form method="post" action="{{ route('workspace.shortcuts.update', $context) }}" class="workspace-launchpad-form">
@csrf @method('PUT')
<div class="workspace-launchpad-dialog-head">
<div><div class="eyebrow">PERSONALIZE WORKSPACE</div><h2 id="workspace-launchpad-config-title-{{ $context }}">Choose quick links</h2><p>Choose up to {{ \App\Support\WorkspaceLaunchpad::MAX_PINNED }} shortcuts. Only tools available to your account are shown.</p></div>
<button type="button" class="icon-btn" data-launchpad-close aria-label="Close quick-launch settings">✕</button>
</div>
<div class="workspace-launchpad-options">
@foreach($catalog as $key => $item)
<label class="workspace-launchpad-option">
<input type="checkbox" name="shortcuts[]" value="{{ $key }}" @checked(in_array($key, $selectedKeys, true))>
<span><strong>{{ $item['label'] }}</strong><small>{{ $item['description'] }}</small></span>
</label>
@endforeach
</div>
<div class="workspace-launchpad-dialog-footer">
<span class="muted tiny" data-launchpad-count aria-live="polite">{{ count($selectedKeys) }} selected</span>
<div class="workspace-launchpad-dialog-actions">
<button type="button" class="btn" data-launchpad-close>Cancel</button>
<button type="submit" class="btn primary" data-launchpad-save>Save shortcuts</button>
</div>
</div>
</form>
<form method="post" action="{{ route('workspace.shortcuts.reset', $context) }}" data-confirm="Restore default workspace shortcuts?">
@csrf @method('DELETE')
<button type="submit" class="btn small workspace-launchpad-reset">Reset to defaults</button>
</form>
</dialog>
</section>
