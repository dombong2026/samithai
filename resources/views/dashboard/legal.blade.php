@extends('layouts.workspace')
@section('title','License & Legal Review')
@section('breadcrumb','Administration / Legal governance')
@section('heading','License & Legal Review')
@section('subtitle','Keep an immutable record of the exact published license and policy versions.')
@section('toolbar')<button class="btn primary" type="button" data-modal-open="legal-modal">+ New draft version</button>@endsection
@section('content')
<div class="notice warning">These documents require qualified review. A saved draft is not an approved agreement; publication requires an independent review reference. This tool records your decision but cannot certify legal compliance, applicable taxes, or enforceability.</div>
<form class="filter-bar" method="get" action="{{ route('dashboard.legal') }}">
<label class="sr-only" for="legal-q">Search documents</label><input id="legal-q" name="q" placeholder="Search title" value="{{ request('q') }}">
<label class="sr-only" for="legal-kind">Type</label><select id="legal-kind" name="kind"><option value="">All types</option>@foreach($kinds as $kind)<option value="{{ $kind }}" @selected(request('kind')===$kind)>{{ ucwords(str_replace('_',' ',$kind)) }}</option>@endforeach</select>
<label class="sr-only" for="legal-status">Status</label><select id="legal-status" name="status"><option value="">All statuses</option>@foreach(['draft','published','retired'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
<button class="btn primary" type="submit">Search & filter</button><a class="btn" href="{{ route('dashboard.legal') }}">Clear</a></form>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Document</th><th>Type</th><th>Version</th><th>Status</th><th>Review</th><th>Effective</th><th>Action</th></tr></thead><tbody>
@forelse($documents as $doc)
<tr><td>{{ $documents->firstItem()+$loop->index }}</td><td class="title-cell">{{ $doc->title }}</td>
<td>{{ ucwords(str_replace('_',' ',$doc->kind)) }}</td><td>v{{ $doc->version }}</td><td><span class="pill {{ $doc->status }}">{{ ucfirst($doc->status) }}</span></td>
<td>{{ $doc->review_reference ?? 'Not reviewed' }}</td><td>{{ $doc->effective_at?->format('d M Y') ?? '—' }}</td>
<td><button class="icon-btn" type="button" data-action-toggle="legal-action-{{ $doc->id }}" aria-label="Actions for {{ $doc->title }}">⋮</button>
<template id="legal-action-{{ $doc->id }}">
@if($doc->status==='published')<a href="{{ route('legal.show',$doc->kind) }}" target="_blank" rel="noopener">Public view</a>@endif
@if($doc->status==='draft')
<button type="button" data-modal-open="legal-modal" data-resource-url="{{ route('dashboard.legal.update',$doc) }}" data-field-kind="{{ $doc->kind }}" data-field-version="{{ $doc->version }}" data-field-title="{{ $doc->title }}" data-field-body-html="{{ $doc->body_html }}">Edit draft</button>
<button type="button" data-modal-open="legal-publish-modal" data-resource-url="{{ route('dashboard.legal.publish',$doc) }}" data-modal-force-post="1" data-field-title="{{ $doc->title }}">Record approval & publish</button>
@else
@if($doc->status==='published')<form method="post" action="{{ route('dashboard.legal.retire',$doc) }}" data-confirm="Retire this version? Previously issued customer acceptances will remain recorded.">@csrf<button class="danger" type="submit">Retire</button></form>@endif
@endif
</template></td></tr>
@empty<tr><td colspan="8" class="empty">No legal versions exist. Draft documents first; do not publish unreviewed terms.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$documents])
<dialog id="legal-modal" class="wide-modal"><div class="modal-head"><h2><span data-modal-mode>Create</span> legal draft</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close">✕</button></div>
<form method="post" action="{{ route('dashboard.legal.store') }}" data-resource-form data-create-url="{{ route('dashboard.legal.store') }}">@csrf<input type="hidden" name="_method" value="PUT" disabled>
<div class="modal-content stack">
<div class="form-grid"><div class="field-group"><label for="doc-kind">Document type</label><select class="field" id="doc-kind" name="kind" data-fill="kind">@foreach($kinds as $kind)<option value="{{ $kind }}">{{ ucwords(str_replace('_',' ',$kind)) }}</option>@endforeach</select></div>
<div class="field-group"><label for="doc-version">Version (semantic)</label><input class="field" id="doc-version" name="version" value="1.0.0" data-fill="version" required pattern="[0-9]{1,4}\.[0-9]{1,4}\.[0-9]{1,4}"></div></div>
<div class="field-group"><label for="doc-title">Document title</label><input class="field" id="doc-title" name="title" data-fill="title" maxlength="160" required></div>
<div class="field-group"><label for="doc-body">Document body (safe HTML, minimum 100 characters)</label><textarea class="field" id="doc-body" name="body_html" data-fill="bodyHtml" rows="11" minlength="100" maxlength="180000" required></textarea></div>
<div class="notice">Publication freezes the version. Create another draft for future amendments.</div></div>
<div class="modal-foot"><button class="btn" type="button" data-modal-close>Cancel</button><button class="btn primary" type="submit">Save draft</button></div></form></dialog>
<dialog id="legal-publish-modal"><div class="modal-head"><h2>Record review and publish</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close">✕</button></div>
<form method="post" action="{{ route('dashboard.legal') }}" data-resource-form>@csrf<div class="modal-content stack">
<div class="field-group"><label for="publish-legal-name">Document</label><input class="field" id="publish-legal-name" data-fill="title" readonly></div>
<div class="field-group"><label for="legal-review-ref">External legal review reference</label><input class="field" id="legal-review-ref" name="review_reference" minlength="8" maxlength="255" required placeholder="Firm / reviewer name and dated approval reference"></div>
<label class="flex"><input type="checkbox" name="acknowledged" value="1" required> I confirm that this exact version has undergone appropriate legal review and I am authorized to publish it.</label>
<div class="notice warning">This confirmation is an operator attestation, not independent proof of legal compliance.</div>
</div><div class="modal-foot"><button class="btn" type="button" data-modal-close>Cancel</button><button class="btn primary" type="submit">Publish reviewed version</button></div></form></dialog>
@endsection
