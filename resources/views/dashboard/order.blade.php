@extends('layouts.workspace')
@section('title','Order '.$order->number)
@section('breadcrumb','Orders / '.$order->number)
@section('heading','Order '.$order->number)
@section('subtitle','Customer: '.$order->user->name.' · '.$order->created_at->format('d M Y'))
@section('toolbar')<a class="btn" href="{{ auth()->user()->isAdmin() ? route('dashboard.finance') : route('account.orders') }}">← Orders</a>@endsection
@section('content')
<div class="stats" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:20px">
<div class="stat"><p>Status</p><b class="pill {{ $order->status }}">{{ ucfirst($order->status) }}</b></div>
<div class="stat"><p>Total amount</p><b>{{ $order->currency }} {{ number_format((float)$order->total,2) }}</b></div>
<div class="stat"><p>Prompts</p><b>{{ $items->total() }}</b></div></div>
<div class="panel"><h2>Order items</h2>
<div class="table-scroll"><table><thead><tr><th>Prompt</th><th>License</th><th>Unit price</th><th>Action</th></tr></thead><tbody>
@foreach($items as $item)
<tr><td>{{ $item->title }}</td><td>{{ ucfirst($item->license_type) }}</td><td>USD {{ number_format((float)$item->unit_price,2) }}</td>
<td>@if($item->prompt && (auth()->user()->isAdmin() || auth()->user()->entitlements()->where('prompt_id',$item->prompt_id)->exists()))
<a class="btn small" href="{{ route('account.library.show',$item->prompt) }}">Open prompt</a>@else<span class="muted">Unavailable</span>@endif</td></tr>
@endforeach
</tbody></table></div>
@include('partials.pagination',['paginator'=>$items])
<div class="notice" style="margin-top:20px">This page is an order record, not a tax invoice or payment receipt. Formal tax and processor verification remain release requirements.</div></div>
@endsection
