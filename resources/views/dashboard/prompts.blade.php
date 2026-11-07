@extends('layouts.workspace')
@section('title','Prompt Manager')
@section('breadcrumb',$isAdmin && request()->routeIs('dashboard.prompts') ? 'Administration / Prompts' : 'Account / Creator')
@section('heading',$isAdmin && request()->routeIs('dashboard.prompts') ? 'Prompt moderation' : 'My prompts')
@section('subtitle','Manage prompt drafts, publication and revisions.')
@section('toolbar')
<a class="btn" href="{{ route('prompts.index') }}">View marketplace</a>
<button class="btn primary" type="button" data-modal-open="prompt-modal">+ New prompt</button>
@endsection
@section('content')
<form class="filter-bar" method="get" action="{{ request()->routeIs('dashboard.prompts') ? route('dashboard.prompts') : route('account.prompts') }}">
<label class="sr-only" for="prompt-q">Search</label><input id="prompt-q" name="q" placeholder="Search prompts" value="{{ request('q') }}">
<label class="sr-only" for="prompt-status">Status</label><select id="prompt-status" name="status"><option value="">All statuses</option>
@foreach(['draft','pending','published','rejected'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
<button class="btn primary" type="submit">Search & filter</button><a class="btn" href="{{ request()->routeIs('dashboard.prompts') ? route('dashboard.prompts') : route('account.prompts') }}">Clear</a></form>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Prompt</th><th>Creator</th><th>Category</th><th>Price</th><th>Status</th><th>Updated</th><th>Action</th></tr></thead><tbody>
@forelse($prompts as $prompt)
<tr><td>{{ $prompts->firstItem()+$loop->index }}</td>
<td class="title-cell">{{ $prompt->title }}<div class="muted tiny">{{ \Illuminate\Support\Str::limit($prompt->summary,65) }}</div></td>
<td>{{ $prompt->user->name }}</td><td>{{ $prompt->category->name }}</td>
<td>{{ $prompt->isFree() ? 'Free' : '$'.number_format((float)$prompt->price,2) }}</td>
<td><span class="pill {{ $prompt->status }}">{{ ucfirst($prompt->status) }}</span></td>
<td>{{ $prompt->updated_at->format('d M Y') }}</td>
<td><button class="icon-btn" type="button" aria-label="Actions for {{ $prompt->title }}" data-action-toggle="prompt-actions-{{ $prompt->id }}">⋮</button>
<template id="prompt-actions-{{ $prompt->id }}">
@if($prompt->status === 'published')<a href="{{ route('prompts.show',$prompt) }}">View on marketplace</a>@endif
<button type="button" data-modal-open="prompt-modal" data-resource-url="{{ route('account.prompts.update',$prompt) }}"
data-field-title="{{ $prompt->title }}" data-field-category-id="{{ $prompt->category_id }}"
data-field-summary="{{ $prompt->summary }}" data-field-content="{{ $prompt->content }}"
data-field-price="{{ $prompt->price }}" data-field-license-type="{{ $prompt->license_type }}">Edit prompt</button>
@if(in_array($prompt->status,['draft','rejected'],true))
<form method="post" action="{{ route('account.prompts.submit',$prompt) }}">@csrf<button type="submit">Submit for review</button></form>
@endif
@if($isAdmin && $prompt->status === 'pending')
<form method="post" action="{{ route('dashboard.prompts.moderate',$prompt) }}">@csrf<input type="hidden" name="decision" value="approve"><button type="submit">Approve & publish</button></form>
<form method="post" action="{{ route('dashboard.prompts.moderate',$prompt) }}">@csrf<input type="hidden" name="decision" value="reject"><button type="submit">Reject submission</button></form>
@endif
<form method="post" action="{{ route('account.prompts.destroy',$prompt) }}" data-confirm="Delete this prompt? Existing customer orders prevent deletion.">@csrf @method('DELETE')<button type="submit" class="danger">Delete prompt</button></form>
</template></td></tr>
@empty<tr><td colspan="8" class="empty">No prompts found. Use New prompt to create one.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$prompts])
<dialog id="prompt-modal">
<div class="modal-head"><h2><span data-modal-mode>Create</span> prompt</h2><button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button></div>
<form method="post" action="{{ route('account.prompts.store') }}" data-resource-form data-create-url="{{ route('account.prompts.store') }}">@csrf <input type="hidden" name="_method" value="PUT" disabled>
<div class="modal-content stack">
<div class="field-group"><label for="pr-title">Prompt title</label><input id="pr-title" name="title" data-fill="title" required maxlength="160" class="field"></div>
<div class="form-grid">
<div class="field-group"><label for="pr-category">Category</label><select class="field" id="pr-category" name="category_id" data-fill="categoryId" required>
@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
</select></div>
<div class="field-group"><label for="pr-license">License</label><select class="field" id="pr-license" name="license_type" data-fill="licenseType"><option value="regular">Regular</option><option value="extended">Extended</option></select></div></div>
<div class="field-group"><label for="pr-summary">Summary</label><textarea id="pr-summary" class="field" name="summary" data-fill="summary" required maxlength="3000"></textarea></div>
<div class="field-group"><label for="pr-content">Private prompt text / HTML source</label>
<div class="flex wrap tiny" role="toolbar" aria-label="Text formatting">
<button class="btn small" type="button" data-editor-wrap="strong" data-editor-target="pr-content"><b>Bold</b></button>
<button class="btn small" type="button" data-editor-wrap="em" data-editor-target="pr-content"><i>Italic</i></button>
<button class="btn small" type="button" data-editor-wrap="code" data-editor-target="pr-content">Code</button>
</div>
<textarea class="field" id="pr-content" name="content" data-fill="content" required rows="8" maxlength="100000"></textarea>
<div class="muted tiny">Prompt content is stored as text and safely escaped when displayed. HTML tags are not executed in this release.</div></div>
<div class="field-group"><label for="pr-price">Price (USD)</label><input class="field" id="pr-price" type="number" min="0" step="0.01" name="price" data-fill="price" value="0" required></div>
<div class="notice warning">Paid checkout remains disabled pending verified payment-provider integration. You can publish paid listings, but customers cannot purchase them yet.</div>
</div><div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn primary">Save prompt</button></div>
</form></dialog>
@endsection
