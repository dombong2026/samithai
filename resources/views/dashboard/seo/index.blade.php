@extends('layouts.workspace')
@section('title','SEO Manager')
@section('heading','SEO Manager')
@section('subtitle','Manage original search titles, descriptions and sharing metadata across content and prompt products.')
@section('content')
<div class="panel" style="padding:18px;margin-bottom:16px">
<div class="flex wrap" style="gap:8px;margin-bottom:14px">
<a class="btn {{ $kind==='post' ? 'primary' : '' }}" href="{{ route('dashboard.seo',['kind'=>'post']) }}">Blog posts</a>
<a class="btn {{ $kind==='page' ? 'primary' : '' }}" href="{{ route('dashboard.seo',['kind'=>'page']) }}">Pages</a>
<a class="btn {{ $kind==='prompt' ? 'primary' : '' }}" href="{{ route('dashboard.seo',['kind'=>'prompt']) }}">Prompt products</a>
</div>
<form method="get" action="{{ route('dashboard.seo') }}" class="filter-bar">
<input type="hidden" name="kind" value="{{ $kind }}">
<label class="sr-only" for="seo-search">Search title</label>
<input id="seo-search" name="q" value="{{ request('q') }}" placeholder="Search {{ $kind }} titles" maxlength="100">
<button class="btn primary" type="submit">Search</button>
</form>
</div>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Title</th><th>Publication</th><th>SEO</th><th>Last updated</th><th>Action</th></tr></thead><tbody>
@forelse($items as $item)
<tr>
<td>{{ $items->firstItem()+$loop->index }}</td>
<td class="title-cell">{{ $item->title }}</td>
<td><span class="pill {{ $item->status }}">{{ ucfirst($item->status) }}</span></td>
<td>{{ $item->seo?->seo_title ? 'Customized' : 'Automatic fallback' }}</td>
<td>{{ $item->updated_at?->format('d M Y') }}</td>
<td><a class="btn small" href="{{ route('dashboard.seo.edit',['kind'=>$kind,'id'=>$item->id]) }}">Edit SEO</a></td>
</tr>
@empty
<tr><td colspan="6" class="empty">No records found.</td></tr>
@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$items])
<div class="notice" style="margin-top:16px">Search metadata does not guarantee rankings. Prompt products are indexed only when published; no unverified offers, ratings or reviews are generated.</div>
@endsection
