@extends('layouts.workspace')
@section('title','Appearance')
@section('breadcrumb','Administration / Appearance')
@section('heading','Website appearance')
@section('subtitle','Manage brand, site hero and theme colors from one location.')
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
@endsection
