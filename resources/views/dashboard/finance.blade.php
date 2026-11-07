@extends('layouts.workspace')
@section('title','Finance Manager')
@section('breadcrumb',$admin ? 'Administration / Finance' : 'Account / Orders')
@section('heading',$admin ? 'Finance Manager' : 'My orders')
@section('subtitle','Real order records and verified transaction status.')
@section('toolbar')<a href="{{ route('prompts.index') }}" class="btn primary">Browse marketplace</a>@endsection
@section('content')
@if($admin)
<div class="panel" style="margin-bottom:18px"><h2>Payment release checks</h2>
<p>Release status is derived from configured, reviewed capabilities—not sample payments.</p>
@foreach($paymentReadiness as $name => $passed)
<div class="flex between" style="padding:7px 0;border-bottom:1px solid var(--line)"><span>{{ $name }}</span><strong class="{{ $passed ? 'status-pass' : 'status-fail' }}">{{ $passed ? 'Ready' : 'Not ready' }}</strong></div>
@endforeach</div>@endif
<div class="notice warning">Paid checkout, payment verification, refunds and payouts are disabled. No financial transactions can be created from this workspace. Only completed free orders are currently supported.</div>
<form class="filter-bar" action="{{ $admin ? route('dashboard.finance') : route('account.orders') }}" method="get">
<label class="sr-only" for="order-q">Search orders</label><input id="order-q" name="q" placeholder="Search order number" value="{{ request('q') }}">
<label class="sr-only" for="order-status">Order status</label><select id="order-status" name="status">
<option value="">All statuses</option>@foreach(['completed','pending','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach
</select><button class="btn primary" type="submit">Search & filter</button><a class="btn" href="{{ $admin ? route('dashboard.finance') : route('account.orders') }}">Clear</a></form>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Order</th><th>Customer</th><th>Products</th><th>Total</th><th>Transactions</th><th>Status</th><th>Created</th><th>Action</th></tr></thead><tbody>
@forelse($orders as $order)
<tr><td>{{ $orders->firstItem()+$loop->index }}</td><td class="title-cell">{{ $order->number }}</td><td>{{ $order->user->name }}</td><td>{{ $order->items_count }}</td>
<td>{{ $order->currency }} {{ number_format((float)$order->total,2) }}</td><td>{{ $order->transactions_count }}</td>
<td><span class="pill {{ $order->status }}">{{ ucfirst($order->status) }}</span></td><td>{{ $order->created_at->format('d M Y') }}</td>
<td><button class="icon-btn" type="button" data-action-toggle="order-{{ $order->id }}" aria-label="Actions for {{ $order->number }}">⋮</button><template id="order-{{ $order->id }}">
<a href="{{ route('orders.show',$order) }}">View order details</a>
</template></td></tr>
@empty<tr><td colspan="9" class="empty">No orders match these filters.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$orders])
@if($admin)
<div class="panel" style="margin-top:24px"><h2>Payment transaction audit</h2><p>Only verified payment provider integrations may add transaction records.</p>
<div class="table-scroll"><table><thead><tr><th>Provider</th><th>Reference</th><th>Order</th><th>Amount</th><th>Status</th><th>Verification</th></tr></thead><tbody>
@forelse($transactions as $txn)
<tr><td>{{ $txn->provider }}</td><td>{{ $txn->provider_reference }}</td><td><a href="{{ route('orders.show',$txn->order) }}">{{ $txn->order->number }}</a></td><td>{{ $txn->currency }} {{ $txn->amount }}</td><td>{{ $txn->status }}</td><td>{{ $txn->verified_at?->format('d M Y H:i') ?? 'Not verified' }}</td></tr>
@empty<tr><td class="empty" colspan="6">No payment-provider transactions have been received or verified.</td></tr>@endforelse
</tbody></table></div></div>
@endif
@endsection
