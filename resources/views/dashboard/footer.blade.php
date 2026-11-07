@extends('layouts.workspace')
@section('title','Footer Manager')
@section('breadcrumb','Administration / Appearance')
@section('heading','Global footer')
@section('subtitle','Manage up to five columns, their order, visibility and links.')
@section('toolbar')<button class="btn primary" type="button" data-modal-open="column-modal" @disabled($columns->count() >= 5)>+ Add column</button>@endsection
@section('content')
<div class="stack">
@forelse($columns as $column)
<div class="panel">
<div class="flex between wrap">
<div><h2>{{ $column->title }} <span class="pill {{ $column->enabled ? 'active' : 'inactive' }}">{{ $column->enabled ? 'Enabled' : 'Disabled' }}</span></h2><p class="tiny">Position {{ $column->position }} · {{ $column->links->count() }} links</p></div>
<div class="flex">
<button type="button" class="btn small" data-modal-open="footer-link-modal" data-resource-url="{{ route('dashboard.footer.links.store',$column) }}" data-field-label="" data-field-url="" data-field-position="1">+ Add link</button>
<button type="button" class="icon-btn" data-action-toggle="column-menu-{{ $column->id }}" aria-label="Actions for {{ $column->title }}">⋮</button>
<template id="column-menu-{{ $column->id }}">
<button type="button" data-modal-open="column-modal" data-resource-url="{{ route('dashboard.footer.update',$column) }}" data-field-title="{{ $column->title }}" data-field-position="{{ $column->position }}" data-field-enabled="{{ (int)$column->enabled }}">Edit column</button>
<form method="post" action="{{ route('dashboard.footer.destroy',$column) }}" data-confirm="Delete this column and every link in it?">@csrf @method('DELETE')<button type="submit" class="danger">Delete column</button></form>
</template>
</div></div>
@if($column->links->isNotEmpty())
<div class="table-scroll"><table><thead><tr><th>Label</th><th>URL</th><th>Order</th><th>Action</th></tr></thead><tbody>
@foreach($column->links as $link)
<tr><td>{{ $link->label }}</td><td class="muted">{{ $link->url }}</td><td>{{ $link->position }}</td>
<td><button type="button" class="icon-btn" aria-label="Actions for {{ $link->label }}" data-action-toggle="footer-link-{{ $link->id }}">⋮</button>
<template id="footer-link-{{ $link->id }}">
<button type="button" data-modal-open="footer-link-modal" data-resource-url="{{ route('dashboard.footer.links.update',$link) }}" data-field-label="{{ $link->label }}" data-field-url="{{ $link->url }}" data-field-position="{{ $link->position }}">Edit link</button>
<form method="post" action="{{ route('dashboard.footer.links.destroy',$link) }}" data-confirm="Delete this footer link?">@csrf @method('DELETE')<button type="submit" class="danger">Delete link</button></form>
</template></td></tr>@endforeach
</tbody></table></div>
@else<div class="notice">This column has no links. Add a link to display content in the public footer.</div>@endif
</div>
@empty<div class="panel empty">No footer columns configured. Add your first column from the top-right button.</div>@endforelse
</div>
<dialog id="column-modal"><div class="modal-head"><h2><span data-modal-mode>Create</span> footer column</h2><button class="icon-btn" type="button" data-modal-close>✕</button></div>
<form method="post" action="{{ route('dashboard.footer.store') }}" data-resource-form data-create-url="{{ route('dashboard.footer.store') }}">@csrf<input type="hidden" name="_method" value="PUT" disabled>
<div class="modal-content stack">
<div class="field-group"><label for="footer-title">Heading</label><input class="field" id="footer-title" name="title" data-fill="title" maxlength="80" required></div>
<div class="field-group"><label for="footer-position">Position (1-5)</label><input class="field" id="footer-position" name="position" type="number" min="1" max="5" value="1" data-fill="position" required></div>
<div class="field-group"><label for="footer-enabled">Visible on website?</label><select class="field" id="footer-enabled" name="enabled" data-fill="enabled"><option value="1">Enabled</option><option value="0">Disabled</option></select></div></div>
<div class="modal-foot"><button class="btn" type="button" data-modal-close>Cancel</button><button class="btn primary" type="submit">Save column</button></div></form></dialog>
<dialog id="footer-link-modal"><div class="modal-head"><h2>Footer link</h2><button class="icon-btn" type="button" data-modal-close>✕</button></div>
<form method="post" action="{{ route('dashboard.footer') }}" data-resource-form data-create-url="{{ route('dashboard.footer') }}">@csrf<input type="hidden" name="_method" value="PUT" disabled>
<div class="modal-content stack">
<div class="field-group"><label for="footer-link-label">Label</label><input class="field" id="footer-link-label" name="label" data-fill="label" maxlength="100" required></div>
<div class="field-group"><label for="footer-link-url">URL</label><input class="field" id="footer-link-url" name="url" data-fill="url" maxlength="500" placeholder="/prompts or https://..." required></div>
<div class="field-group"><label for="footer-link-pos">Order</label><input class="field" id="footer-link-pos" type="number" min="1" max="999" name="position" data-fill="position" value="1" required></div>
</div><div class="modal-foot"><button class="btn" type="button" data-modal-close>Cancel</button><button class="btn primary" type="submit">Save link</button></div></form></dialog>
@endsection
