@extends('layouts.workspace')
@section('title','Appearance')
@section('breadcrumb','Administration / Appearance')
@section('heading','Website appearance')
@section('subtitle','Manage branding and the complete public, legal, member and creator page inventory.')
@section('toolbar')<a class="btn" href="{{ route('dashboard.footer') }}">Footer Manager</a><a class="btn primary" href="{{ route('home') }}" target="_blank" rel="noopener">Preview website</a>@endsection
@section('content')
<form action="{{ route('dashboard.appearance.update') }}" method="post">@csrf @method('PUT')
<div class="form-grid">
<div class="panel stack"><h2>Brand identity</h2>
<div class="field-group"><label for="settings-name">Website name</label><input class="field" id="settings-name" name="site_name" required maxlength="80" value="{{ old('site_name',$settings['site_name']) }}"></div>
<div class="field-group"><label for="settings-desc">Website description</label><textarea class="field" id="settings-desc" name="site_description" required maxlength="300" rows="3">{{ old('site_description',$settings['site_description']) }}</textarea></div>
<div class="field-group"><label for="settings-logo">Website logo</label><select class="field" id="settings-logo" name="logo_media_id"><option value="">Use lettermark</option>
@foreach($images as $image)<option value="{{ $image->id }}" @selected(old('logo_media_id',$settings['logo_media_id']) == $image->id)>{{ $image->title }}</option>@endforeach</select>
<a class="tiny muted" href="{{ route('dashboard.media') }}">Open Media Library to upload an image</a></div>
</div>
<div class="panel stack"><h2>Brand colors</h2>
<div class="field-group"><label for="settings-primary">Primary color</label><input class="field" id="settings-primary" type="color" name="primary_color" value="{{ old('primary_color',$settings['primary_color']) }}" required></div>
<div class="field-group"><label for="settings-secondary">Secondary color</label><input class="field" id="settings-secondary" type="color" name="secondary_color" value="{{ old('secondary_color',$settings['secondary_color']) }}" required></div>
<p class="tiny">Text colors continue to respond to the visitor's light/dark preference. Selected colors affect the public website and shared dashboards.</p>
</div>
<div class="panel full stack"><h2>Homepage hero</h2>
<div class="field-group"><label for="settings-hero-title">Hero title</label><input class="field" id="settings-hero-title" name="hero_title" value="{{ old('hero_title',$settings['hero_title']) }}" maxlength="180" required></div>
<div class="field-group"><label for="settings-hero-sub">Hero description</label><textarea class="field" id="settings-hero-sub" name="hero_subtitle" maxlength="450" required rows="3">{{ old('hero_subtitle',$settings['hero_subtitle']) }}</textarea></div>
</div>
</div><div class="flex" style="justify-content:flex-end;margin:22px 0"><button class="btn primary" type="submit">Save appearance</button></div></form>

<section class="panel frontend-pages-panel" id="frontend-pages" aria-labelledby="frontend-pages-title">
<div class="frontend-pages-heading"><div><div class="eyebrow">SITE BUILDER</div><h2 id="frontend-pages-title">Frontend Pages</h2><p class="muted">All public templates, custom content, legal pages and signed-in account screens in one inventory. Configure supported copy, manage content and control optional public-page visibility; authentication, checkout, entitlements and legal routes stay protected.</p></div>
<button type="button" class="btn primary" data-frontend-dialog="frontend-create-page"><x-nav-icon name="pages"/> Add custom page</button></div>
<form method="get" action="{{ route('dashboard.appearance') }}" class="frontend-pages-search">
<label for="frontend-page-search" class="sr-only">Find frontend page</label>
<input id="frontend-page-search" class="field" name="page_q" value="{{ $pageSearch }}" placeholder="Search name, route or access type" maxlength="80">
<label for="frontend-page-type" class="sr-only">Filter frontend page type</label>
<select id="frontend-page-type" class="field frontend-page-type" name="page_type">
<option value="all" @selected($pageType === 'all')>All frontend pages</option>
<option value="builtin" @selected($pageType === 'builtin')>Built-in templates</option>
<option value="managed" @selected($pageType === 'managed')>Managed content</option>
<option value="system" @selected($pageType === 'system')>Account & protected</option>
<option value="custom" @selected($pageType === 'custom')>Custom pages</option>
</select>
<button type="submit" class="btn">Apply</button>
@if($pageSearch !== '' || $pageType !== 'all')<a class="btn" href="{{ route('dashboard.appearance') }}#frontend-pages">Clear</a>@endif
</form>
<div class="frontend-pages-scroll"><table class="frontend-pages-table">
<thead><tr><th>Frontend page</th><th>URL pattern</th><th>Type</th><th>Visibility</th><th>Action</th></tr></thead>
<tbody>
@foreach($frontendPages as $key => $page)
<tr><td><strong>{{ $page['name'] }}</strong><span class="muted tiny">{{ $page['description'] }}</span></td>
<td><code>{{ $page['path'] }}</code></td><td>Built-in template</td>
<td><span class="frontend-status {{ !$page['toggleable'] ? 'is-protected' : ($page['enabled'] ? 'is-live' : 'is-disabled') }}">{{ !$page['toggleable'] ? 'Protected' : ($page['enabled'] ? 'Enabled' : 'Disabled') }}</span><span class="muted tiny frontend-copy-state">{{ $page['customized'] ? 'Customized copy' : 'Default copy' }}</span></td>
<td><div class="frontend-row-actions"><button class="btn small" type="button" data-frontend-dialog="frontend-config-{{ $key }}">Configure</button>
@if($page['preview_route'] && $page['enabled'])
<a class="btn small" href="{{ route($page['preview_route']) }}" target="_blank" rel="noopener noreferrer">Preview</a>
@endif
@if($page['toggleable'])
<form method="post" action="{{ route('dashboard.appearance.frontend.visibility', $key) }}" data-confirm="{{ $page['enabled'] ? 'Disable this page for public visitors? Content and settings will be preserved.' : 'Enable this public page again?' }}">@csrf @method('PUT')
<input type="hidden" name="enabled" value="{{ $page['enabled'] ? '0' : '1' }}">
<button type="submit" class="btn small frontend-visibility-button {{ $page['enabled'] ? 'is-disable' : 'is-enable' }}">{{ $page['enabled'] ? 'Disable' : 'Enable' }}</button></form>
@else<span class="muted tiny" title="Essential page: access cannot be disabled">Always on</span>@endif
</div></td></tr>
@endforeach
@foreach($managedPages as $page)
<tr><td><strong>{{ $page['name'] }}</strong><span class="muted tiny">{{ $page['description'] }} Content is controlled in {{ $page['label'] }}.</span></td>
<td><code>{{ $page['path'] }}</code></td><td>Managed content</td><td><span class="frontend-status {{ !$page['toggleable'] ? 'is-protected' : ($page['enabled'] ? 'is-live' : 'is-disabled') }}">{{ !empty($page['individually_published']) ? 'Per page' : (!$page['toggleable'] ? 'Protected' : ($page['enabled'] ? 'Enabled' : 'Disabled')) }}</span></td>
<td><div class="frontend-row-actions"><a class="btn small" href="{{ route('dashboard.content',$page['manager']) }}">Manage</a>
@if($page['toggleable'])
<form method="post" action="{{ route('dashboard.appearance.frontend.visibility', $page['key']) }}" data-confirm="{{ $page['enabled'] ? 'Disable public access to these articles? Published content will be preserved.' : 'Enable public access to these articles?' }}">@csrf @method('PUT')
<input type="hidden" name="enabled" value="{{ $page['enabled'] ? '0' : '1' }}">
<button type="submit" class="btn small frontend-visibility-button {{ $page['enabled'] ? 'is-disable' : 'is-enable' }}">{{ $page['enabled'] ? 'Disable' : 'Enable' }}</button></form>
@else<span class="muted tiny">Always on</span>@endif</div></td></tr>
@endforeach
@foreach($systemPages as $page)
<tr><td><strong>{{ $page['name'] }}</strong><span class="muted tiny">{{ $page['description'] }}</span></td>
<td><code>{{ $page['path'] }}</code></td>
<td>{{ $page['audience'] }}</td>
<td><span class="frontend-status is-protected">Protected</span><span class="muted tiny frontend-copy-state">{{ $page['audience'] === 'Public legal' ? 'Legal review required' : 'Sign-in required' }}</span></td>
<td><div class="frontend-row-actions"><a class="btn small" href="{{ route($page['action_route']) }}">{{ $page['action_label'] }}</a><span class="muted tiny">Always on</span></div></td></tr>
@endforeach
@foreach($customPages as $entry)
<tr><td><strong>{{ $entry->title }}</strong><span class="muted tiny">Updated {{ $entry->updated_at->format('d M Y') }}</span></td><td><code>/pages/{{ $entry->slug }}</code></td><td>Custom page</td>
<td><span class="frontend-status {{ $entry->status === 'published' ? 'is-live' : 'is-disabled' }}">{{ $entry->status === 'published' ? 'Enabled' : 'Disabled (draft)' }}</span></td>
<td><div class="frontend-row-actions"><button class="btn small" type="button" data-frontend-dialog="frontend-custom-{{ $entry->id }}">Configure</button>
<form method="post" action="{{ route('dashboard.content.publish',$entry) }}" data-confirm="{{ $entry->status === 'published' ? 'Disable this custom page? Content will be preserved as a draft.' : 'Enable and publish this custom page?' }}">@csrf
<input type="hidden" name="status" value="{{ $entry->status === 'published' ? 'draft' : 'published' }}">
<button type="submit" class="btn small frontend-visibility-button {{ $entry->status === 'published' ? 'is-disable' : 'is-enable' }}">{{ $entry->status === 'published' ? 'Disable' : 'Enable' }}</button></form></div></td></tr>
@endforeach
@if(!$frontendPages && !$managedPages && !$systemPages && $customPages->isEmpty())<tr><td colspan="5" class="empty">No matching frontend pages. Try a different search or page type.</td></tr>@endif
</tbody></table></div>
<div class="frontend-pages-foot"><span class="muted tiny">{{ $visiblePagesCount }} matching entries · {{ count($frontendPages) }} editable templates · {{ count($managedPages) }} managed layouts · {{ count($systemPages) }} protected screens · {{ $customPages->total() }} custom pages. Localized public URLs reuse these templates.</span>{{ $customPages->links() }}</div>
</section>

@foreach($frontendPages as $key => $page)
<dialog class="frontend-config-dialog" id="frontend-config-{{ $key }}" aria-labelledby="frontend-title-{{ $key }}">
<div class="frontend-dialog-head"><div><div class="eyebrow">EDIT PAGE TEMPLATE</div><h2 id="frontend-title-{{ $key }}">{{ $page['name'] }}</h2><span class="muted tiny">{{ $page['path'] }}</span></div><button class="icon-btn" type="button" data-frontend-close aria-label="Close configuration">✕</button></div>
<form id="frontend-form-{{ $key }}" action="{{ route('dashboard.appearance.frontend.update',$key) }}" method="post">@csrf @method('PUT')
<div class="frontend-dialog-body"><p class="muted tiny">Edit visible labels and supported display settings. System URLs and legal requirements remain protected.</p><div class="frontend-fields">
@foreach($page['fields'] as $name => $spec)
<div class="field-group {{ ($spec['type'] ?? '') === 'textarea' ? 'frontend-field-wide' : '' }}">
@if(($spec['type'] ?? '') === 'toggle')
<input type="hidden" name="fields[{{ $name }}]" value="0">
<label class="frontend-toggle"><input type="checkbox" name="fields[{{ $name }}]" value="1" @checked($page['values'][$name])><span>{{ $spec['label'] }}</span></label>
@elseif(($spec['type'] ?? '') === 'textarea')
<label for="frontend-{{ $key }}-{{ $name }}">{{ $spec['label'] }}</label>
<textarea class="field" name="fields[{{ $name }}]" id="frontend-{{ $key }}-{{ $name }}" rows="3" maxlength="450" required>{{ $page['values'][$name] }}</textarea>
@else
<label for="frontend-{{ $key }}-{{ $name }}">{{ $spec['label'] }}</label>
<input class="field" name="fields[{{ $name }}]" id="frontend-{{ $key }}-{{ $name }}" maxlength="450" required value="{{ $page['values'][$name] }}">
@endif
</div>
@endforeach
</div></div>
</form>
<div class="frontend-dialog-actions">
<form method="post" action="{{ route('dashboard.appearance.frontend.reset',$key) }}" data-confirm="Restore the built-in page labels and settings?">@csrf @method('DELETE')
<button type="submit" class="btn" @disabled(!$page['customized'])>Reset defaults</button></form>
<div><button type="button" class="btn" data-frontend-close>Cancel</button><button type="submit" class="btn primary" form="frontend-form-{{ $key }}">Save changes</button></div>
</div>
</dialog>
@endforeach

<dialog class="frontend-config-dialog" id="frontend-create-page" aria-labelledby="frontend-create-title">
<div class="frontend-dialog-head"><div><div class="eyebrow">CUSTOM CONTENT</div><h2 id="frontend-create-title">New frontend page</h2><span class="muted tiny">Creates a draft at /pages/{slug}</span></div><button type="button" class="icon-btn" data-frontend-close aria-label="Close new page">✕</button></div>
<form id="frontend-create-form" action="{{ route('dashboard.content.store','page') }}" method="post">@csrf
<div class="frontend-dialog-body">
<div class="field-group"><label for="frontend-new-title">Page title</label><input class="field" id="frontend-new-title" name="title" required maxlength="160"></div>
<div class="field-group"><label for="frontend-new-excerpt">Description</label><textarea class="field" id="frontend-new-excerpt" name="excerpt" maxlength="3000" rows="2"></textarea></div>
<div class="field-group"><label for="frontend-new-body">Body HTML</label><textarea class="field frontend-content-editor" id="frontend-new-body" name="body_html" maxlength="180000" required rows="10" placeholder="<p>Write your page here.</p>"></textarea><p class="muted tiny">HTML is sanitized upon saving; publish only after reviewing the preview.</p></div>
</div></form>
<div class="frontend-dialog-actions"><span></span><div><button type="button" class="btn" data-frontend-close>Cancel</button><button type="submit" class="btn primary" form="frontend-create-form">Create draft</button></div></div></dialog>

@foreach($customPages as $entry)
<dialog class="frontend-config-dialog" id="frontend-custom-{{ $entry->id }}" aria-labelledby="frontend-custom-title-{{ $entry->id }}">
<div class="frontend-dialog-head"><div><div class="eyebrow">CUSTOM FRONTEND PAGE</div><h2 id="frontend-custom-title-{{ $entry->id }}">{{ $entry->title }}</h2><span class="muted tiny">/pages/{{ $entry->slug }}</span></div><button type="button" class="icon-btn" data-frontend-close aria-label="Close custom page">✕</button></div>
<form id="frontend-custom-form-{{ $entry->id }}" action="{{ route('dashboard.content.update',$entry) }}" method="post">@csrf @method('PUT')
<div class="frontend-dialog-body">
<div class="field-group"><label for="frontend-title-{{ $entry->id }}">Title</label><input class="field" id="frontend-title-{{ $entry->id }}" name="title" maxlength="160" required value="{{ $entry->title }}"></div>
<div class="field-group"><label for="frontend-excerpt-{{ $entry->id }}">Description</label><textarea class="field" id="frontend-excerpt-{{ $entry->id }}" name="excerpt" maxlength="3000" rows="2">{{ $entry->excerpt }}</textarea></div>
<div class="field-group"><label for="frontend-body-{{ $entry->id }}">Body HTML</label><textarea class="field frontend-content-editor" id="frontend-body-{{ $entry->id }}" name="body_html" maxlength="180000" required rows="10">{{ $entry->body_html }}</textarea><p class="muted tiny">The URL slug is preserved when changing the title. Publication is a separate action.</p></div>
</div></form>
<div class="frontend-dialog-actions">
<div class="frontend-dialog-options">
<a href="{{ route('dashboard.content.preview',$entry) }}" target="_blank" rel="noopener" class="btn small">Preview</a>
<form method="post" action="{{ route('dashboard.content.publish',$entry) }}" data-confirm="{{ $entry->status === 'published' ? 'Return this page to draft?' : 'Publish this custom page?' }}">@csrf<input type="hidden" name="status" value="{{ $entry->status === 'published' ? 'draft' : 'published' }}"><button class="btn small" type="submit">{{ $entry->status === 'published' ? 'Unpublish' : 'Publish' }}</button></form>
<form method="post" action="{{ route('dashboard.content.destroy',$entry) }}" data-confirm="Permanently delete this custom page?">@csrf @method('DELETE')<button class="btn small danger" type="submit">Delete</button></form>
</div>
<div><button type="button" class="btn" data-frontend-close>Cancel</button><button type="submit" class="btn primary" form="frontend-custom-form-{{ $entry->id }}">Save page</button></div></div>
</dialog>
@endforeach
@endsection
