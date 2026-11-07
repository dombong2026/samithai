<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\PromptEntitlement;
use Illuminate\Support\Facades\DB;
use LogicException;

final class VerifiedPaymentFulfillment
{
    private function cents(string $amount): int
    {
        if (! preg_match('/^(0|[1-9][0-9]{0,9})(\.[0-9]{1,2})?$/D', $amount)) {
            throw new LogicException('Invalid provider amount format.');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    /**
     * Internal, transactional fulfillment. No route calls this service.
     * A real provider-specific verified adapter must be implemented, reviewed
     * and onboarded before its use on live paid orders.
     */
    public function fulfill(Order $order, VerifiedProviderReceipt $receipt): void
    {
        $provider = (string) config('payments.provider');
        if (! config('payments.enabled')
            || ! in_array($provider, config('payments.supported_providers', []), true)
            || $receipt->provider !== $provider) {
            throw new LogicException('Payment provider not approved or disabled.');
        }
        if ($receipt->reference === '' || strlen($receipt->reference) > 160) {
            throw new LogicException('Invalid provider reference.');
        }

        DB::transaction(function () use ($order, $receipt): void {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($receipt->orderNumber !== $locked->number
                || $receipt->currency !== $locked->currency
                || $this->cents($receipt->amount) !== $this->cents((string) $locked->total)
                || $this->cents($receipt->amount) <= 0) {
                throw new LogicException('Payment confirmation does not match the immutable order amount and currency.');
            }

            $existing = DB::table('payment_transactions')
                ->where('provider', $receipt->provider)
                ->where('provider_reference', $receipt->reference)
                ->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->order_id === $locked->id && $existing->status === 'verified'
                    && $locked->status === 'completed') {
                    return; // Exact replay has no side effects.
                }
                throw new LogicException('Provider reference reused or transaction state inconsistent.');
            }
            if ($locked->status !== 'pending') {
                throw new LogicException('Only pending paid orders can be fulfilled.');
            }

            $items = $locked->items()->with('prompt')->lockForUpdate()->get();
            if ($items->isEmpty()) throw new LogicException('Order has no items.');

            // Total is validated against the sum of immutable quoted line prices.
            $sum = $items->sum(fn ($item) => $this->cents((string) $item->unit_price));
            if ($sum !== $this->cents((string) $locked->total)) {
                throw new LogicException('Order total does not reconcile with line items.');
            }
            foreach ($items as $item) {
                if (! $item->prompt || $item->content_snapshot === null) {
                    throw new LogicException('Missing immutable licensed content snapshot.');
                }
            }

            DB::table('payment_transactions')->insert([
                'order_id' => $locked->id,
                'provider' => $receipt->provider,
                'provider_reference' => $receipt->reference,
                'status' => 'verified',
                'currency' => $receipt->currency,
                'amount' => $receipt->amount,
                'verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($items as $item) {
                PromptEntitlement::firstOrCreate(
                    ['user_id'=>$locked->user_id,'prompt_id'=>$item->prompt_id],
                    [
                        'order_id'=>$locked->id,
                        'license_type'=>$item->license_type,
                        'content_snapshot'=>$item->content_snapshot,
                    ]
                );
            }
            $locked->update(['status'=>'completed']);
        }, 3);
    }
}
