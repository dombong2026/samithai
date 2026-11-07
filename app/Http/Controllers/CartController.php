<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Support\AuditTrail;
use App\Models\Order;
use App\Models\Prompt;
use App\Models\PromptEntitlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $items = $request->user()->cartItems()->with('prompt.category')->latest()->get();

        return view('marketplace.cart', [
            'items' => $items,
            'total' => $items->sum(fn ($item) => (float) $item->prompt->price),
            'hasPaid' => $items->contains(fn ($item) => (float) $item->prompt->price > 0),
        ]);
    }

    public function add(Request $request, Prompt $prompt): RedirectResponse
    {
        abort_unless($prompt->status === 'published', 404);
        if ($request->user()->entitlements()->where('prompt_id', $prompt->id)->exists()) {
            return redirect()->route('account.library')->with('success', 'You already own this prompt.');
        }
        CartItem::firstOrCreate(['user_id' => $request->user()->id, 'prompt_id' => $prompt->id]);

        return redirect()->route('cart.index')->with('success', 'Prompt added to your cart.');
    }

    public function remove(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);
        $cartItem->delete();

        return back()->with('success', 'Prompt removed from your cart.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $user = $request->user();

        $order = DB::transaction(function () use ($user): Order {
            $items = CartItem::query()->where('user_id', $user->id)
                ->with('prompt')->lockForUpdate()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }
            foreach ($items as $item) {
                if ($item->prompt->status !== 'published') {
                    throw ValidationException::withMessages(['cart' => 'A prompt is no longer available. Remove it before checkout.']);
                }
                if (! $item->prompt->isFree()) {
                    throw ValidationException::withMessages(['cart' => 'Paid checkout is unavailable until a verified payment provider is enabled. No charge was made.']);
                }
            }

            $order = Order::create([
                'user_id' => $user->id,
                'number' => 'SA-'.strtoupper(Str::random(14)),
                'status' => 'completed',
                'currency' => 'USD',
                'total' => 0,
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'prompt_id' => $item->prompt_id,
                    'title' => $item->prompt->title,
                    'unit_price' => 0,
                    'license_type' => $item->prompt->license_type,
                    'content_snapshot' => $item->prompt->content,
                ]);
                PromptEntitlement::firstOrCreate(
                    ['user_id' => $user->id, 'prompt_id' => $item->prompt_id],
                    ['order_id' => $order->id, 'license_type' => $item->prompt->license_type, 'content_snapshot' => $item->prompt->content]
                );
            }
            CartItem::where('user_id', $user->id)->delete();

            return $order;
        }, 3);

        AuditTrail::record($request,'order.free_checkout',$order,['amount'=>'0.00','currency'=>'USD']);
        return redirect()->route('account.library')
            ->with('success', 'Free checkout complete. Order '.$order->number.' is in your library.');
    }
}
