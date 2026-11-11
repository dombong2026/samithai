@extends('layouts.workspace')
@section('title','Edit SEO')
@section('heading','Edit SEO metadata')
@section('subtitle','Customize search, canonical and social sharing details without editing public page layouts.')
@section('toolbar')
<a class="btn" href="{{ route('dashboard.seo',['kind'=>$kind]) }}">Back to SEO Manager</a>
@endsection
@section('content')
<div class="panel" style="padding:22px;max-width:850px">
<p class="muted tiny">Type: {{ ucfirst($kind) }} · {{ $item->status }}</p>
<h2 style="margin:8px 0 16px">{{ $item->title }}</h2>
<form action="{{ route('dashboard.seo.update',['kind'=>$kind,'id'=>$item->id]) }}" method="post" class="stack">
@csrf @method('PUT')
<div class="field-group"><label for="seo-title">SEO title</label>
<input class="field" id="seo-title" name="seo_title" maxlength="160" value="{{ old('seo_title',$item->seo?->seo_title) }}" placeholder="{{ $item->title }}">
<div class="tiny muted">Optional; blank uses the original title.</div></div>
<div class="field-group"><label for="seo-desc">Meta description</label>
<textarea class="field" id="seo-desc" name="meta_description" maxlength="300" rows="3" placeholder="Accurate summary for search results">{{ old('meta_description',$item->seo?->meta_description) }}</textarea></div>
<div class="field-group"><label for="seo-canonical">Canonical path</label>
<input class="field" id="seo-canonical" name="canonical_path" maxlength="500" value="{{ old('canonical_path',$item->seo?->canonical_path) }}" placeholder="/{{ $kind==='post' ? 'blog' : ($kind==='page' ? 'pages' : 'prompts') }}/{{ $item->slug }}">
<div class="tiny muted">Optional. Use only a relative path within the matching samithai.com content section.</div></div>
<div class="field-group"><label for="seo-og-title">Social sharing title</label>
<input class="field" id="seo-og-title" name="og_title" maxlength="160" value="{{ old('og_title',$item->seo?->og_title) }}" placeholder="{{ $item->title }}"></div>
<div class="field-group"><label for="seo-og-desc">Social sharing description</label>
<textarea class="field" id="seo-og-desc" name="og_description" maxlength="300" rows="3">{{ old('og_description',$item->seo?->og_description) }}</textarea></div>
<div class="field-group"><label for="seo-media">Social sharing image</label>
<select class="field" name="og_media_id" id="seo-media"><option value="">Use featured image if available</option>
@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected((string)old('og_media_id',$item->seo?->og_media_id)===(string)$asset->id)>{{ $asset->title }}</option>@endforeach
</select><a href="{{ route('dashboard.media') }}" class="tiny">Manage original images in Media Library</a></div>
<div class="field-group"><label for="seo-index">Search visibility</label>
<select class="field" id="seo-index" name="robots_index">
<option value="1" @selected((string)old('robots_index',$item->seo?->robots_index ?? true)==='1')>Index published content</option>
<option value="0" @selected((string)old('robots_index',$item->seo?->robots_index ?? true)==='0')>Noindex / nofollow</option>
</select></div>
<div class="panel" style="padding:14px;background:var(--soft)">
<div class="muted tiny">Current search preview</div>
<strong>{{ $seo['title'] }}</strong>
<p class="muted tiny">{{ $seo['canonical'] }}</p>
<p>{{ $seo['description'] }}</p>
<div class="muted tiny">Structured data: {{ $kind === 'post' ? 'BlogPosting' : ($kind==='page' ? 'WebPage' : 'Product') }} · Generated only on public content</div>
</div>
<div class="flex wrap" style="justify-content:flex-end"><button class="btn primary" type="submit">Save SEO settings</button></div>
</form></div>
@endsection
