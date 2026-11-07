@extends('layouts.workspace')
@section('title','My cart')
@section('breadcrumb','Marketplace / Cart')
@section('heading','Shopping cart')
@section('subtitle','Review your selected AI prompts.')
@section('toolbar')<a href="{{ route('prompts.index') }}" class="btn primary">Explore prompts</a>@endsection
@section('content')
@if($items->isEmpty())<div class="panel empty"><h3>Your cart is empty</h3><p>Browse the marketplace to find useful prompts.</p><a class="btn primary" href="{{ route('prompts.index') }}">Browse prompts</a></div>
@else
<div class="panel stack">
@foreach($items as $item)
<div class="flex between wrap" style="padding:12px 0;border-bottom:1px solid var(--line)">
<div><strong>{{ $item->prompt->title }}</strong><div class="muted tiny">{{ $item->prompt->category?->name }} · {{ ucfirst($item->prompt->license_type) }}</div></div>
<div class="flex"><b>{{ $item->prompt->isFree() ? 'Free' : '$'.number_format((float)$item->prompt->price,2) }}</b><form method="post" action="{{ route('cart.remove', $item) }}">@csrf @method('DELETE')<button class="btn small danger" type="submit">Remove</button></form></div></div>
@endforeach
<div class="flex between"><strong>Total</strong><b class="price">USD {{ number_format($total,2) }}</b></div>
@if($hasPaid)<div class="notice warning">Paid checkout is not yet enabled. Remove paid prompts to complete a free checkout. Your cart has not been charged.</div>
@else<form method="post" action="{{ route('checkout') }}">@csrf<button type="submit" class="btn primary">Complete free checkout</button></form>@endif
</div>
@endif
@endsection
