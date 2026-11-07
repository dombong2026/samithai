@if($paginator->hasPages())
<div class="table-foot">
<span class="muted tiny">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} records</span>
<nav class="pages" aria-label="Pagination">
@if($paginator->onFirstPage())<span aria-disabled="true">Previous</span>@else<a href="{{ $paginator->previousPageUrl() }}">Previous</a>@endif
@for($n = max(1, $paginator->currentPage()-2); $n <= min($paginator->lastPage(), $paginator->currentPage()+2); $n++)
@if($n === $paginator->currentPage())<span class="current" aria-current="page">{{ $n }}</span>@else<a href="{{ $paginator->url($n) }}">{{ $n }}</a>@endif
@endfor
@if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">Next</a>@else<span aria-disabled="true">Next</span>@endif
</nav>
<form class="goto" action="{{ url()->current() }}" method="get">
@foreach(request()->except('page') as $key => $value)
@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
@endforeach
<label class="tiny" for="jump-{{ $paginator->getPageName() }}">Go to</label><input class="field" type="number" min="1" max="{{ $paginator->lastPage() }}" id="jump-{{ $paginator->getPageName() }}" name="page" value="{{ $paginator->currentPage() }}" required><button class="btn small" type="submit">Go</button>
</form>
</div>
@endif
