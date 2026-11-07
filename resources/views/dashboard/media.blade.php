@extends('layouts.workspace')
@section('title','Media Library')
@section('breadcrumb','Workspace / Media Library')
@section('heading','Media Library')
@section('subtitle','Secure images, videos, YouTube previews and featured colors.')
@section('toolbar')
<button class="btn" type="button" data-modal-open="media-reference-modal">+ Video / Color</button>
<button class="btn primary" type="button" data-modal-open="media-upload-modal">+ Upload media</button>
@endsection
@section('content')
<form class="filter-bar" method="get" action="{{ request()->routeIs('dashboard.media') ? route('dashboard.media') : route('account.media') }}">
<label class="sr-only" for="media-q">Search</label><input id="media-q" name="q" placeholder="Search by media title" value="{{ request('q') }}">
<label class="sr-only" for="media-kind">Media type</label><select name="kind" id="media-kind">
<option value="">All types</option>@foreach(['image','video','youtube','color'] as $kind)
<option value="{{ $kind }}" @selected(request('kind') === $kind)>{{ ucfirst($kind) }}</option>@endforeach
</select><button class="btn primary" type="submit">Search & filter</button>
<a class="btn" href="{{ request()->routeIs('dashboard.media') ? route('dashboard.media') : route('account.media') }}">Clear</a>
</form>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Preview</th><th>Title</th><th>Kind</th><th>Size</th><th>Owner</th><th>Created</th><th>Action</th></tr></thead><tbody>
@forelse($assets as $asset)
<tr>
<td>{{ $assets->firstItem() + $loop->index }}</td>
<td><div class="media-thumb">
@if($asset->kind === 'image')<img src="{{ route('media.serve',$asset) }}" alt="{{ $asset->title }}" loading="lazy">
@elseif($asset->kind === 'color')<span style="background:{{ $asset->color_hex }}"></span>
@elseif($asset->kind === 'youtube')<span>▶</span>
@else<span>▣</span>@endif
</div></td>
<td class="title-cell">{{ $asset->title }}</td><td><span class="pill">{{ ucfirst($asset->kind) }}</span></td>
<td>{{ $asset->bytes ? number_format($asset->bytes / 1024, 1).' KB' : '—' }}</td>
<td>{{ $asset->owner->name }}</td><td>{{ $asset->created_at->format('d M Y') }}</td>
<td><button class="icon-btn" type="button" data-action-toggle="media-menu-{{ $asset->id }}" aria-label="Actions for {{ $asset->title }}">⋮</button>
<template id="media-menu-{{ $asset->id }}">
@if(in_array($asset->kind, ['image','video'],true))<a href="{{ route('media.serve',$asset) }}" target="_blank" rel="noopener">Preview</a>
@elseif($asset->kind === 'youtube')<a href="https://www.youtube.com/watch?v={{ $asset->youtube_id }}" target="_blank" rel="noopener noreferrer">Preview on YouTube</a>@endif
<button type="button" data-modal-open="media-edit-modal" data-resource-url="{{ route('media.update',$asset) }}" data-field-title="{{ $asset->title }}">Rename</button>
<form method="post" action="{{ route('media.destroy',$asset) }}" data-confirm="Delete this media asset? You cannot delete an asset used in featured content.">@csrf @method('DELETE')<button type="submit" class="danger">Delete</button></form>
</template></td></tr>
@empty<tr><td colspan="8" class="empty">No media assets found. Use Upload media to add your first image or video.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$assets])
<dialog id="media-upload-modal">
<div class="modal-head"><h2>Upload media</h2><button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button></div>
<form action="{{ route('media.upload') }}" method="post" enctype="multipart/form-data">@csrf
<div class="modal-content stack">
<div class="field-group"><label for="upload-title">Title (optional)</label><input class="field" type="text" name="title" id="upload-title" maxlength="160" placeholder="Descriptive media title"></div>
<div class="field-group"><label for="upload-file">Image or video</label><input class="field" type="file" name="file" id="upload-file" required accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"></div>
<p class="tiny">JPG, PNG, WEBP, GIF, MP4 and WEBM only. Maximum 15 MB. Files stay in private application storage.</p>
</div><div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn primary" type="submit">Upload media</button></div>
</form></dialog>
<dialog id="media-reference-modal">
<div class="modal-head"><h2>Featured media reference</h2><button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button></div>
<form method="post" action="{{ route('media.reference') }}">@csrf
<div class="modal-content stack">
<div class="field-group"><label for="reference-title">Title</label><input class="field" id="reference-title" name="title" required maxlength="160"></div>
<div class="field-group"><label for="reference-kind">Type</label><select class="field" id="reference-kind" name="kind"><option value="youtube">YouTube video</option><option value="color">Color</option></select></div>
<div class="field-group"><label for="reference-url">YouTube watch or youtu.be URL (video only)</label><input class="field" type="url" id="reference-url" name="url"></div>
<div class="field-group"><label for="reference-color">Hex color (color only)</label><input class="field" type="text" id="reference-color" name="color_hex" placeholder="#ED702B" pattern="#[0-9a-fA-F]{6}"></div>
</div><div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn primary" type="submit">Save media reference</button></div></form>
</dialog>
<dialog id="media-edit-modal">
<div class="modal-head"><h2>Rename media</h2><button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button></div>
<form method="post" action="{{ route('media.upload') }}" data-resource-form data-create-url="{{ route('media.upload') }}">@csrf <input type="hidden" name="_method" value="PUT" disabled>
<div class="modal-content"><div class="field-group"><label for="media-edit-title">Title</label><input class="field" id="media-edit-title" name="title" data-fill="title" maxlength="160" required></div></div>
<div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn primary" type="submit">Save changes</button></div></form>
</dialog>
@endsection
