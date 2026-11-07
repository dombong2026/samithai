<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $request->routeIs('dashboard.finance');
        abort_if($admin && ! $request->user()->isAdmin(), 403);
        $data = $request->validate([
            'q' => ['nullable','string','max:100'],
            'status' => ['nullable','in:completed,pending,cancelled'],
        ]);
        $query = Order::with('user')->withCount('items')->withCount('transactions');
        if (! $admin) $query->where('user_id',$request->user()->id);
        if (filled($data['q'] ?? null)) $query->where('number','like','%'.$data['q'].'%');
        if (filled($data['status'] ?? null)) $query->where('status',$data['status']);

        return view('dashboard.finance', [
            'orders' => $query->latest()->paginate(10)->withQueryString(),
            'admin' => $admin,
            'transactions' => $admin ? PaymentTransaction::with('order')->latest()->limit(10)->get() : collect(),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($request->user()->isAdmin() || $order->user_id === $request->user()->id, 403);

        return view('dashboard.order', [
            'order' => $order->load(['user','transactions']),
            'items' => $order->items()->with('prompt')->paginate(10),
        ]);
    }
}
