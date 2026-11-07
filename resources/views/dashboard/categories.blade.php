@extends('layouts.workspace')
@section('title','Category Manager')
@section('breadcrumb','Administration / Categories')
@section('heading','Category Manager')
@section('subtitle','Create and maintain marketplace categories.')
@section('toolbar')<button type="button" class="btn primary" data-modal-open="category-modal">+ Add category</button>@endsection
@section('content')
<form class="filter-bar" method="get" action="{{ route('dashboard.categories') }}">
<label class="sr-only" for="category-q">Search</label><input id="category-q" name="q" placeholder="Search categories" value="{{ request('q') }}">
<label class="sr-only" for="category-status">Filter status</label><select id="category-status" name="status"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select>
<button class="btn primary" type="submit">Search & filter</button><a class="btn" href="{{ route('dashboard.categories') }}">Clear</a>
</form>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Category</th><th>Slug</th><th>Prompts</th><th>Status</th><th>Created</th><th>Action</th></tr></thead><tbody>
@forelse($categories as $category)
<tr><td>{{ $categories->firstItem()+$loop->index }}</td>
<td><div class="title-cell">{{ $category->name }}</div><div class="muted tiny">{{ \Illuminate\Support\Str::limit($category->description,55) }}</div></td>
<td>{{ $category->slug }}</td><td>{{ $category->prompts_count }}</td><td><span class="pill {{ $category->is_active ? 'active' : 'inactive' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
<td>{{ $category->created_at->format('d M Y') }}</td>
<td><button type="button" class="icon-btn" aria-label="Actions for {{ $category->name }}" data-action-toggle="category-actions-{{ $category->id }}">⋮</button>
<template id="category-actions-{{ $category->id }}">
@if($category->is_active)<a href="{{ route('categories.show',$category) }}">View category</a>@endif
<button type="button" data-modal-open="category-modal" data-resource-url="{{ route('dashboard.categories.update',$category) }}"
data-field-name="{{ $category->name }}" data-field-description="{{ $category->description }}" data-field-is-active="{{ (int)$category->is_active }}">Edit category</button>
<form method="post" action="{{ route('dashboard.categories.destroy',$category) }}" data-confirm="Delete this category? This cannot be undone.">@csrf @method('DELETE')<button type="submit" class="danger">Delete category</button></form>
</template></td></tr>
@empty<tr><td colspan="7" class="empty">No categories match your search.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$categories])
<dialog id="category-modal">
<div class="modal-head"><h2><span data-modal-mode>Create</span> category</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close">✕</button></div>
<form method="post" action="{{ route('dashboard.categories.store') }}" data-resource-form data-create-url="{{ route('dashboard.categories.store') }}">@csrf <input type="hidden" name="_method" value="PUT" disabled>
<div class="modal-content stack">
<div class="field-group"><label for="cat-name">Category name</label><input class="field" id="cat-name" name="name" data-fill="name" maxlength="100" required></div>
<div class="field-group"><label for="cat-description">Description</label><textarea class="field" id="cat-description" name="description" data-fill="description" maxlength="2000"></textarea></div>
<div class="field-group"><label for="cat-active">Visibility</label><select class="field" name="is_active" id="cat-active" data-fill="isActive"><option value="1">Active</option><option value="0">Inactive</option></select></div>
</div><div class="modal-foot"><button class="btn" type="button" data-modal-close>Cancel</button><button class="btn primary" type="submit">Save category</button></div></form>
</dialog>
@endsection
