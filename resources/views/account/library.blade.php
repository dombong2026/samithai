@extends('layouts.workspace')
@section('title','My library')
@section('breadcrumb','Account / Library')
@section('heading','My prompt library')
@section('subtitle','Prompts you are licensed to access.')
@section('toolbar')<a class="btn primary" href="{{ route('prompts.index') }}">Browse marketplace</a>@endsection
@section('content')
<form class="filter-bar" method="get" action="{{ route('account.library') }}">
<label class="sr-only" for="library-q">Search</label><input id="library-q" name="q" value="{{ request('q') }}" placeholder="Search your library">
<button class="btn primary" type="submit">Search</button><a class="btn" href="{{ route('account.library') }}">Clear</a>
</form>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Prompt</th><th>Category</th><th>License</th><th>Added</th><th>Action</th></tr></thead><tbody>
@forelse($entitlements as $item)
<tr><td>{{ $entitlements->firstItem() + $loop->index }}</td><td class="title-cell">{{ $item->prompt->title }}</td><td>{{ $item->prompt->category->name }}</td><td>{{ ucfirst($item->license_type) }}</td><td>{{ $item->created_at->format('d M Y') }}</td><td><a class="btn small" href="{{ route('account.library.show', $item->prompt) }}">Open prompt</a></td></tr>
@empty<tr><td colspan="6" class="empty">No licensed prompts found.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$entitlements])
@endsection
