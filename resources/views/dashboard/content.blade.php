@extends('layouts.workspace')
@section('title',ucfirst($section).' Manager')
@section('breadcrumb','Administration / '.ucfirst($section))
@section('heading',ucfirst($section).' Manager')
@section('subtitle','Manage drafts, previews and published '. $section .'.')
@section('toolbar')
<button class="btn primary" type="button" data-modal-open="content-modal">+ New {{ ucfirst($kind) }}</button>
@endsection
@section('content')
<form class="filter-bar" method="get" action="{{ route('dashboard.content',$section) }}">
<label class="sr-only" for="content-q">Search</label><input id="content-q" name="q" value="{{ request('q') }}" placeholder="Search {{ $section }}">
<label class="sr-only" for="content-status">Filter</label><select id="content-status" name="status"><option value="">All statuses</option><option value="draft" @selected(request('status')==='draft')>Draft</option><option value="published" @selected(request('status')==='published')>Published</option></select>
<button class="btn primary" type="submit">Search & filter</button><a class="btn" href="{{ route('dashboard.content',$section) }}">Clear</a></form>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Title</th><th>Slug</th><th>Author</th><th>Featured</th><th>Status</th><th>Updated</th><th>Action</th></tr></thead><tbody>
@forelse($entries as $entry)
<tr><td>{{ $entries->firstItem()+$loop->index }}</td><td class="title-cell">{{ $entry->title }}</td>
<td>{{ $entry->slug }}</td><td>{{ $entry->author->name }}</td><td>{{ $entry->media?->title ?? 'None' }}</td>
<td><span class="pill {{ $entry->status }}">{{ ucfirst($entry->status) }}</span></td>
<td>{{ $entry->updated_at->format('d M Y') }}</td>
<td><button class="icon-btn" type="button" data-action-toggle="entry-{{ $entry->id }}" aria-label="Actions for {{ $entry->title }}">⋮</button>
<template id="entry-{{ $entry->id }}">
<a href="{{ route('dashboard.translations',$entry) }}">Translations</a>
<a href="{{ route('dashboard.content.preview',$entry) }}" target="_blank" rel="noopener">Preview</a>
@if($entry->status === 'published')
@if($kind === 'post')<a href="{{ route('blog.show',$entry->slug) }}">View published</a>
@elseif($kind === 'page')<a href="{{ route('pages.show',$entry->slug) }}">View published</a>
@else<a href="{{ route('policies.show',$entry->slug) }}">View published</a>@endif
@endif
<button type="button" data-modal-open="content-modal" data-resource-url="{{ route('dashboard.content.update',$entry) }}"
 data-field-title="{{ $entry->title }}" data-field-excerpt="{{ $entry->excerpt }}"
 data-field-body-html="{{ $entry->body_html }}" data-field-featured-media-id="{{ $entry->featured_media_id }}">Edit</button>
<form method="post" action="{{ route('dashboard.content.publish',$entry) }}" data-confirm="Change the publication status of this {{ $kind }}?">@csrf
<input type="hidden" name="status" value="{{ $entry->status === 'published' ? 'draft' : 'published' }}">
<button type="submit">{{ $entry->status === 'published' ? 'Unpublish' : 'Publish' }}</button></form>
<form method="post" action="{{ route('dashboard.content.destroy',$entry) }}" data-confirm="Permanently delete this {{ $kind }}?">@csrf @method('DELETE')<button class="danger" type="submit">Delete</button></form>
</template></td></tr>
@empty<tr><td colspan="8" class="empty">No {{ $section }} found. Create a draft to begin.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$entries])
<dialog id="content-modal" class="wide-modal">
<div class="modal-head"><h2><span data-modal-mode>Create</span> {{ $kind }}</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close">✕</button></div>
<form method="post" data-resource-form data-create-url="{{ route('dashboard.content.store',$kind) }}" action="{{ route('dashboard.content.store',$kind) }}">@csrf<input type="hidden" name="_method" value="PUT" disabled>
<div class="modal-content stack">
<div class="field-group"><label for="entry-title">Title</label><input class="field" id="entry-title" name="title" data-fill="title" required maxlength="160"></div>
<div class="field-group"><label for="entry-excerpt">Excerpt</label><textarea class="field" id="entry-excerpt" name="excerpt" data-fill="excerpt" maxlength="3000" rows="2"></textarea></div>
<div class="field-group"><label for="entry-media">Featured media</label><select class="field" id="entry-media" name="featured_media_id" data-fill="featuredMediaId"><option value="">None</option>
@foreach($mediaAssets as $asset)<option value="{{ $asset->id }}">{{ $asset->title }} ({{ $asset->kind }})</option>@endforeach
</select><a class="tiny muted" href="{{ route('dashboard.media') }}">Open Media Library</a></div>
<div class="field-group">
<label id="body-label">Page body</label>
<div class="editor-toolbar" role="toolbar" aria-label="Rich text editor">
<button class="btn small ghost" type="button" data-rich-command="bold"><b>B</b></button>
<button class="btn small ghost" type="button" data-rich-command="italic"><i>I</i></button>
<button class="btn small ghost" type="button" data-rich-command="underline"><u>U</u></button>
<button class="btn small ghost" type="button" data-rich-command="formatBlock" data-rich-value="h2">H2</button>
<button class="btn small ghost" type="button" data-rich-command="formatBlock" data-rich-value="h3">H3</button>
<button class="btn small ghost" type="button" data-rich-command="insertUnorderedList">Bullets</button>
<button class="btn small ghost" type="button" data-rich-command="insertOrderedList">Numbers</button>
<button class="btn small ghost" type="button" data-rich-command="formatBlock" data-rich-value="blockquote">Quote</button>
<button class="btn small ghost" type="button" data-rich-command="removeFormat">Clear style</button>
<button class="btn small ghost" type="button" data-rich-command="undo">Undo</button>
<button class="btn small ghost" type="button" data-rich-command="redo">Redo</button>
<button class="btn small ghost" type="button" data-rich-source-toggle>HTML source</button>
</div>
<div id="entry-rich-editor" class="rich-editor document-content" contenteditable="true" role="textbox" aria-labelledby="body-label" aria-multiline="true"></div>
<textarea class="field" id="entry-body" name="body_html" data-fill="bodyHtml" rows="10" maxlength="180000" hidden></textarea>
<div class="muted tiny">Content is sanitized server-side to approved HTML tags. Scripts, inline CSS, embedded iframes, forms and unsafe URLs are removed. Use Media Library for featured videos.</div>
</div>
</div><div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn primary">Save draft</button></div>
</form></dialog>
@endsection
